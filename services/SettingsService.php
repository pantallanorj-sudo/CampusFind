<?php
/** Reads and validates rows of system_settings. Missing or invalid rows fall back to safe defaults. */
final class SettingsService
{
    private static $cache = null;

    public static function definitions()
    {
        return array(
            'unclaimed_retention_days' => array('label' => 'Unclaimed item retention (days)', 'type' => 'int', 'min' => 1, 'max' => 3650, 'default' => '30',
                'help' => 'Found items still unclaimed after this many days become eligible for archiving.'),
            'app_name' => array('label' => 'Application name', 'type' => 'text', 'min' => 2, 'max' => 40, 'default' => 'CampusFind',
                'help' => 'Shown in the page header, browser title and footer.'),
            'items_per_page' => array('label' => 'Items per page', 'type' => 'int', 'min' => 5, 'max' => 50, 'default' => '12',
                'help' => 'Page size for item, claim and history lists.'),
            'notify_staff_new_claims' => array('label' => 'Notify staff about new claims', 'type' => 'bool', 'default' => '1',
                'help' => 'Send an in-system notification to staff whenever a claim is submitted.'),
        );
    }

    /** Validated string value, or null if the value is not acceptable for that setting. */
    private static function valid($key, $value)
    {
        $defs = self::definitions();
        if (!isset($defs[$key])) {
            return null;
        }
        $d = $defs[$key];
        $value = trim((string) $value);
        if ($d['type'] === 'int') {
            if (preg_match('/^\d{1,5}$/', $value) !== 1) {
                return null;
            }
            $n = (int) $value;
            return ($n < $d['min'] || $n > $d['max']) ? null : (string) $n;
        }
        if ($d['type'] === 'bool') {
            return ($value === '1' || $value === '0') ? $value : null;
        }
        $len = text_len($value);
        if ($len < $d['min'] || $len > $d['max'] || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return null;
        }
        return $value;
    }

    private static function describe($key)
    {
        $defs = self::definitions();
        $d = $defs[$key];
        if ($d['type'] === 'int') {
            return $d['label'] . ' must be a whole number between ' . $d['min'] . ' and ' . $d['max'] . '.';
        }
        if ($d['type'] === 'bool') {
            return $d['label'] . ' must be on or off.';
        }
        return $d['label'] . ' must be ' . $d['min'] . '–' . $d['max'] . ' characters with no control characters.';
    }

    public static function all()
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $stored = array();
        foreach (db_all('SELECT setting_key, setting_value FROM system_settings') as $row) {
            $stored[$row['setting_key']] = $row['setting_value'];
        }
        $out = array();
        foreach (self::definitions() as $key => $d) {
            $v = isset($stored[$key]) ? self::valid($key, $stored[$key]) : null;
            $out[$key] = ($v === null) ? (string) $d['default'] : $v;
        }
        self::$cache = $out;
        return $out;
    }

    public static function get($key)
    {
        $all = self::all();
        return isset($all[$key]) ? $all[$key] : '';
    }

    public static function retentionDays()
    {
        return (int) self::get('unclaimed_retention_days');
    }

    public static function perPage()
    {
        return (int) self::get('items_per_page');
    }

    /** $input: key => raw submitted value. Returns array('ok'=>bool, 'errors'=>[], 'changed'=>[keys]). */
    public static function save($actorId, $input)
    {
        $errors = array();
        $clean = array();
        foreach (self::definitions() as $key => $d) {
            $raw = (isset($input[$key]) && is_string($input[$key])) ? $input[$key] : ($d['type'] === 'bool' ? '0' : null);
            $v = ($raw === null) ? null : self::valid($key, $raw);
            if ($v === null) {
                $errors[] = self::describe($key);
            } else {
                $clean[$key] = $v;
            }
        }
        if (count($errors) > 0) {
            return array('ok' => false, 'errors' => $errors, 'changed' => array());
        }
        $stored = array();
        foreach (db_all('SELECT setting_key, setting_value FROM system_settings') as $row) {
            $stored[$row['setting_key']] = $row['setting_value'];
        }
        $now = now_utc();
        $changed = db_transaction(function ($pdo) use ($clean, $stored, $now) {
            $changed = array();
            $update = $pdo->prepare('UPDATE system_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?');
            $insert = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)');
            foreach ($clean as $key => $value) {
                if (!isset($stored[$key]) || $stored[$key] !== $value) {
                    $update->execute(array($value, $now, $key));
                    if ($update->rowCount() === 0) {          // row missing (older database): create it
                        $insert->execute(array($key, $value, $now));
                    }
                    $changed[] = $key;
                }
            }
            return $changed;
        });
        self::$cache = null;
        if (count($changed) > 0) {
            audit_log($actorId, 'settings.save', 'changed=' . implode(',', $changed));
        }
        return array('ok' => true, 'errors' => array(), 'changed' => $changed);
    }
}
