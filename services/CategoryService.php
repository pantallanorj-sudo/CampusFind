<?php
/** Category CRUD. Deleting is blocked while reports use a category (history must stay intact). */
final class CategoryService
{
    public static function all()
    {
        return db_all('SELECT c.*, (SELECT COUNT(*) FROM item_reports r WHERE r.category_id = c.id) AS report_count FROM categories c ORDER BY c.name COLLATE NOCASE ASC');
    }

    /** id => name, for <select> lists. */
    public static function options()
    {
        $out = array();
        foreach (db_all('SELECT id, name FROM categories ORDER BY name COLLATE NOCASE ASC') as $r) {
            $out[(int) $r['id']] = $r['name'];
        }
        return $out;
    }

    public static function find($id)
    {
        return db_one('SELECT * FROM categories WHERE id = ?', array((int) $id));
    }

    public static function exists($id)
    {
        return db_count('SELECT COUNT(*) FROM categories WHERE id = ?', array((int) $id)) > 0;
    }

    private static function normalize($name)
    {
        return trim((string) preg_replace('/\s+/', ' ', clean_text($name)));
    }

    private static function check($name, $description, $excludeId)
    {
        $errors = array();
        $len = text_len($name);
        if ($len < 2 || $len > 60) {
            $errors[] = 'Category name must be 2–60 characters.';
        } elseif (db_count('SELECT COUNT(*) FROM categories WHERE name = ? COLLATE NOCASE AND id <> ?', array($name, (int) $excludeId)) > 0) {
            $errors[] = 'A category named "' . $name . '" already exists (names are not case-sensitive).';
        }
        if (text_len($description) > 200) {
            $errors[] = 'Description must be 200 characters or fewer.';
        }
        return $errors;
    }

    public static function create($actorId, $name, $description)
    {
        $name = self::normalize($name);
        $description = clean_text($description);
        $errors = self::check($name, $description, 0);
        if (count($errors) > 0) {
            return array('ok' => false, 'errors' => $errors);
        }
        try {
            db()->prepare('INSERT INTO categories (name, description, created_at) VALUES (?, ?, ?)')
                ->execute(array($name, $description === '' ? null : $description, now_utc()));
        } catch (PDOException $e) {
            return array('ok' => false, 'errors' => array('A category with that name already exists.'));
        }
        $id = (int) db()->lastInsertId();
        audit_log($actorId, 'category.create', 'id=' . $id);
        return array('ok' => true, 'errors' => array(), 'id' => $id);
    }

    public static function update($actorId, $id, $name, $description)
    {
        if (!self::find($id)) {
            return array('ok' => false, 'errors' => array('That category no longer exists.'));
        }
        $name = self::normalize($name);
        $description = clean_text($description);
        $errors = self::check($name, $description, $id);
        if (count($errors) > 0) {
            return array('ok' => false, 'errors' => $errors);
        }
        try {
            db()->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?')
                ->execute(array($name, $description === '' ? null : $description, (int) $id));
        } catch (PDOException $e) {
            return array('ok' => false, 'errors' => array('A category with that name already exists.'));
        }
        audit_log($actorId, 'category.update', 'id=' . (int) $id);
        return array('ok' => true, 'errors' => array());
    }

    public static function delete($actorId, $id)
    {
        $cat = self::find($id);
        if (!$cat) {
            return array('ok' => false, 'errors' => array('That category no longer exists.'));
        }
        $used = db_count('SELECT COUNT(*) FROM item_reports WHERE category_id = ?', array((int) $id));
        if ($used > 0) {
            return array('ok' => false, 'errors' => array('"' . $cat['name'] . '" is used by ' . plural($used, 'report') . ', so it cannot be deleted. Rename it instead; reports keep their history.'));
        }
        if (db_count('SELECT COUNT(*) FROM categories') <= 1) {
            return array('ok' => false, 'errors' => array('At least one category must remain.'));
        }
        try {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute(array((int) $id));
        } catch (PDOException $e) {
            return array('ok' => false, 'errors' => array('This category could not be deleted because other records still depend on it.'));
        }
        audit_log($actorId, 'category.delete', 'id=' . (int) $id);
        return array('ok' => true, 'errors' => array());
    }
}
