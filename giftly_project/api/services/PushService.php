<?php
// api/services/PushService.php
// Registers/unregisters this device's FCM token against the logged-in user,
// so push_lib.php's send_status_push() (called from admin_orders.php when an
// order's status changes) knows where to deliver it.

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../push_lib.php';

class PushService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        push_ensure_schema($conn);
    }

    // POST push/register { token, platform }
    public function register($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $token = trim((string) ($input['token'] ?? ''));
        if ($token === '') {
            sendError('Token is required');
            return;
        }
        $platform = trim((string) ($input['platform'] ?? 'android'));

        push_register_token($this->conn, $user_id, $token, $platform);
        sendSuccess(null, 'Token registered');
    }

    // POST push/unregister { token }
    public function unregister($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $token = trim((string) ($input['token'] ?? ''));
        if ($token === '') {
            sendError('Token is required');
            return;
        }

        push_unregister_token($this->conn, $token);
        sendSuccess(null, 'Token unregistered');
    }

    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }
}
