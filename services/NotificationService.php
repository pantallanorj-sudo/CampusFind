<?php
/** Persistent in-system notifications (table notifications). Users can only ever touch their own rows. */
final class NotificationService
{
    /** Returns true when a row was inserted (identical notifications within 60 seconds are ignored). */
    public static function create($userId, $type, $message, $relatedType = null, $relatedId = null)
    {
        $pdo = db();
        $sql = 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ? AND message = ?';
        $params = array((int) $userId, (string) $type, (string) $message);
        if ($relatedType === null) {
            $sql .= ' AND related_type IS NULL';
        } else {
            $sql .= ' AND related_type = ?';
            $params[] = (string) $relatedType;
        }
        if ($relatedId === null) {
            $sql .= ' AND related_id IS NULL';
        } else {
            $sql .= ' AND related_id = ?';
            $params[] = (int) $relatedId;
        }
        $sql .= ' AND created_at >= ?';
        $params[] = gmdate('Y-m-d H:i:s', time() - 60);
        if (db_count($sql, $params) > 0) {
            return false;
        }
        $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_type, related_id, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(array((int) $userId, (string) $type, (string) $message, $relatedType, $relatedId === null ? null : (int) $relatedId, now_utc()));
        return true;
    }

    /** Notify every active staff member and administrator (optionally skipping one user). */
    public static function notifyStaff($type, $message, $relatedType = null, $relatedId = null, $exceptUserId = 0)
    {
        $count = 0;
        foreach (db_all("SELECT id FROM users WHERE role IN ('staff','admin') AND is_active = 1 ORDER BY id") as $u) {
            if ((int) $u['id'] !== (int) $exceptUserId && self::create((int) $u['id'], $type, $message, $relatedType, $relatedId)) {
                $count++;
            }
        }
        return $count;
    }

    public static function unreadCount($userId)
    {
        return db_count('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', array((int) $userId));
    }

    public static function countFor($userId, $unreadOnly)
    {
        return db_count('SELECT COUNT(*) FROM notifications WHERE user_id = ?' . ($unreadOnly ? ' AND read_at IS NULL' : ''), array((int) $userId));
    }

    public static function listFor($userId, $unreadOnly, $limit, $offset)
    {
        return db_all('SELECT * FROM notifications WHERE user_id = ?' . ($unreadOnly ? ' AND read_at IS NULL' : '')
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset, array((int) $userId));
    }

    /** Marks one notification read - only if it belongs to $userId. Returns true if a row changed. */
    public static function markRead($userId, $notificationId)
    {
        $stmt = db()->prepare('UPDATE notifications SET read_at = ? WHERE id = ? AND user_id = ? AND read_at IS NULL');
        $stmt->execute(array(now_utc(), (int) $notificationId, (int) $userId));
        return $stmt->rowCount() > 0;
    }

    public static function markAllRead($userId)
    {
        $stmt = db()->prepare('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL');
        $stmt->execute(array(now_utc(), (int) $userId));
        return $stmt->rowCount();
    }

    /** Where a notification should take the viewer (relative path) or null. */
    public static function linkFor($n, $role)
    {
        $type = isset($n['related_type']) ? $n['related_type'] : null;
        $id = isset($n['related_id']) ? (int) $n['related_id'] : 0;
        if ($id < 1 || $type === null || $type === '') {
            return null;
        }
        if ($type === 'claim') {
            return ($role === 'student') ? 'student/my-claims.php?id=' . $id : 'staff/verify-claims.php?id=' . $id;
        }
        if ($type === 'report') {
            return 'student/item-details.php?id=' . $id;
        }
        return null;
    }
}
