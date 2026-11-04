<?php
/** User accounts. Password hashes never leave this class. Accounts with history are deactivated, never deleted. */
final class UserService
{
    public static function roles()
    {
        return array('student', 'staff', 'admin');
    }

    public static function find($id)
    {
        return db_one('SELECT id, full_name, email, role, is_active, created_at FROM users WHERE id = ?', array((int) $id));
    }

    public static function activeAdminCount()
    {
        return db_count("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
    }

    public static function search($f, $page, $perPage)
    {
        $where = array('1 = 1');
        $params = array();
        if (!empty($f['q'])) {
            $like = like_pattern($f['q']);
            $where[] = "(u.full_name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')";
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($f['role']) && in_array($f['role'], self::roles(), true)) {
            $where[] = 'u.role = ?';
            $params[] = $f['role'];
        }
        if (isset($f['active']) && ($f['active'] === '0' || $f['active'] === '1')) {
            $where[] = 'u.is_active = ?';
            $params[] = (int) $f['active'];
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = db_count('SELECT COUNT(*) FROM users u ' . $whereSql, $params);
        $pg = paginate($total, $perPage, $page);
        $rows = db_all('SELECT u.id, u.full_name, u.email, u.role, u.is_active, u.created_at, '
            . '(SELECT COUNT(*) FROM item_reports r WHERE r.user_id = u.id) AS report_count, '
            . '(SELECT COUNT(*) FROM claim_requests q WHERE q.claimant_id = u.id) AS claim_count '
            . 'FROM users u ' . $whereSql . ' ORDER BY u.created_at DESC, u.id DESC LIMIT ' . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset'], $params);
        return array('rows' => $rows, 'pg' => $pg);
    }

    /** Validates and inserts an account. Used by public registration (role fixed to student) and by administrators. */
    public static function insert($name, $email, $password, $role)
    {
        $name = clean_text($name);
        $email = strtolower(trim((string) $email));
        $errors = validate_person_name($name);
        if (!valid_email($email)) {
            $errors[] = 'Enter a valid email address.';
        }
        $errors = array_merge($errors, validate_password($password));
        if (!in_array($role, self::roles(), true)) {
            $errors[] = 'Choose a valid role.';
        }
        if (count($errors) === 0 && db_count('SELECT COUNT(*) FROM users WHERE email = ?', array($email)) > 0) {
            $errors[] = 'That email address is already registered. Try logging in instead.';
        }
        if (count($errors) > 0) {
            return array('ok' => false, 'errors' => $errors);
        }
        try {
            db()->prepare('INSERT INTO users (full_name, email, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, ?)')
                ->execute(array($name, $email, password_hash((string) $password, PASSWORD_DEFAULT), $role, now_utc()));
        } catch (PDOException $e) {
            return array('ok' => false, 'errors' => array('That email address is already registered. Try logging in instead.'));
        }
        return array('ok' => true, 'errors' => array(), 'id' => (int) db()->lastInsertId());
    }

    private static function guard($actor)
    {
        if (!is_admin_user($actor)) {
            throw new BusinessRuleException('Only administrators can manage user accounts.');
        }
    }

    private static function target($id)
    {
        $t = self::find($id);
        if (!$t) {
            throw new BusinessRuleException('That user no longer exists.');
        }
        return $t;
    }

    public static function create($actor, $name, $email, $password, $role)
    {
        try {
            self::guard($actor);
        } catch (BusinessRuleException $e) {
            return array('ok' => false, 'errors' => array($e->getMessage()));
        }
        $r = self::insert($name, $email, $password, $role);
        if ($r['ok']) {
            audit_log($actor['id'], 'user.create', 'id=' . $r['id'] . ' role=' . $role);
        }
        return $r;
    }

    public static function setRole($actor, $id, $role)
    {
        try {
            self::guard($actor);
            if (!in_array($role, self::roles(), true)) {
                throw new BusinessRuleException('Choose a valid role.');
            }
            $t = self::target($id);
            if ((int) $t['id'] === (int) $actor['id']) {
                throw new BusinessRuleException('You cannot change your own role. Ask another administrator.');
            }
            if ($t['role'] === $role) {
                return array('ok' => true, 'errors' => array(), 'changed' => false);
            }
            if ($t['role'] === 'admin' && (int) $t['is_active'] === 1 && self::activeAdminCount() <= 1) {
                throw new BusinessRuleException('At least one active administrator must remain.');
            }
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute(array($role, (int) $t['id']));
            audit_log($actor['id'], 'user.role', 'id=' . $t['id'] . ' ' . $t['role'] . '->' . $role);
            return array('ok' => true, 'errors' => array(), 'changed' => true);
        } catch (BusinessRuleException $e) {
            return array('ok' => false, 'errors' => array($e->getMessage()));
        }
    }

    public static function setActive($actor, $id, $active)
    {
        try {
            self::guard($actor);
            $t = self::target($id);
            $active = $active ? 1 : 0;
            if ((int) $t['id'] === (int) $actor['id']) {
                throw new BusinessRuleException('You cannot deactivate your own account.');
            }
            if ((int) $t['is_active'] === $active) {
                return array('ok' => true, 'errors' => array(), 'changed' => false);
            }
            if ($active === 0 && $t['role'] === 'admin' && self::activeAdminCount() <= 1) {
                throw new BusinessRuleException('At least one active administrator must remain.');
            }
            db()->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute(array($active, (int) $t['id']));
            audit_log($actor['id'], $active === 1 ? 'user.activate' : 'user.deactivate', 'id=' . $t['id']);
            return array('ok' => true, 'errors' => array(), 'changed' => true);
        } catch (BusinessRuleException $e) {
            return array('ok' => false, 'errors' => array($e->getMessage()));
        }
    }

    public static function resetPassword($actor, $id, $password)
    {
        try {
            self::guard($actor);
            $t = self::target($id);
            $errors = validate_password($password);
            if (count($errors) > 0) {
                return array('ok' => false, 'errors' => $errors);
            }
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute(array(password_hash((string) $password, PASSWORD_DEFAULT), (int) $t['id']));
            audit_log($actor['id'], 'user.password_reset', 'id=' . $t['id']);
            return array('ok' => true, 'errors' => array());
        } catch (BusinessRuleException $e) {
            return array('ok' => false, 'errors' => array($e->getMessage()));
        }
    }
}
