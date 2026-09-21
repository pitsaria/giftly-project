<?php
// api/services/NotificationService.php
// Read-side of the in-app notification inbox — writes happen through
// notif_create() (see ../../notif_lib.php), called from order-status /
// promo / product triggers, not from here.

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../notif_lib.php';

class NotificationService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        notif_ensure_schema($conn);
    }

    // GET notifications?category=&unread_only=&limit=&offset=
    public function getAll($headers, $params) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $where = "user_id = $user_id";
        $category = $params['category'] ?? '';
        if (in_array($category, ['order', 'promo', 'product'], true)) {
            $where .= " AND category = '" . $this->conn->real_escape_string($category) . "'";
        }
        if (!empty($params['unread_only'])) {
            $where .= " AND read_at IS NULL";
        }

        $limit = isset($params['limit']) ? max(1, min(50, intval($params['limit']))) : 20;
        $offset = isset($params['offset']) ? max(0, intval($params['offset'])) : 0;

        $result = $this->conn->query("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
        $items = [];
        while ($result && $row = $result->fetch_assoc()) {
            $row['data'] = json_decode($row['data'] ?? '', true) ?: null;
            $row['is_read'] = !empty($row['read_at']);
            $items[] = $row;
        }
        sendSuccess(['notifications' => $items]);
    }

    // GET notifications/unread-count
    public function getUnreadCount($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        $row = $this->conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = $user_id AND read_at IS NULL")->fetch_assoc();
        sendSuccess(['count' => intval($row['total'])]);
    }

    // PUT notifications/read?id=123 — mark one of the caller's own notifications as read.
    public function markRead($id, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        $id = (int) $id;
        $this->conn->query("UPDATE notifications SET read_at = (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
                             WHERE id = $id AND user_id = $user_id AND read_at IS NULL");
        sendSuccess(null, 'Marked read');
    }

    // POST notifications/read-all — mark every unread notification as read.
    public function markAllRead($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        $this->conn->query("UPDATE notifications SET read_at = (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
                             WHERE user_id = $user_id AND read_at IS NULL");
        sendSuccess(null, 'All marked read');
    }

    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }
}
