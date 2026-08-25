<?php
// api/services/ProfileService.php
// Mirrors profile_settings.php for standalone clients.

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';

class ProfileService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // GET profile
    public function getProfile($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $user = $this->conn->query(
            "SELECT name, email, phone, profile_pic FROM users WHERE id = $user_id"
        )->fetch_assoc();

        $nameParts = explode(' ', $user['name'], 2);
        $firstname = $nameParts[0];
        $lastname = $nameParts[1] ?? '';

        $orderCount = $this->conn->query("SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id")->fetch_assoc()['total'];
        $addressCount = $this->conn->query("SELECT COUNT(*) as total FROM addresses WHERE user_id = $user_id")->fetch_assoc()['total'];

        sendSuccess([
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $user['email'],
            'phone' => $user['phone'],
            'profile_pic' => $user['profile_pic'],
            'order_count' => intval($orderCount),
            'address_count' => intval($addressCount)
        ]);
    }

    // PUT profile { firstname, lastname, email, phone, current_password?, new_password? }
    public function updateProfile($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $user = $this->conn->query("SELECT name, email, phone, password FROM users WHERE id = $user_id")->fetch_assoc();
        $nameParts = explode(' ', $user['name'], 2);

        $firstname = !empty($input['firstname']) ? $this->conn->real_escape_string($input['firstname']) : $nameParts[0];
        $lastname = !empty($input['lastname']) ? $this->conn->real_escape_string($input['lastname']) : ($nameParts[1] ?? '');
        $email = !empty($input['email']) ? $this->conn->real_escape_string($input['email']) : $user['email'];
        $phone = !empty($input['phone']) ? $this->conn->real_escape_string($input['phone']) : $user['phone'];
        $fullname = trim($firstname . ' ' . $lastname);

        $new_pass = $input['new_password'] ?? '';
        if (!empty($new_pass)) {
            $current_pass = $input['current_password'] ?? '';
            if (!password_verify($current_pass, $user['password'])) {
                sendError('Current password is incorrect');
                return;
            }
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $this->conn->query(
                "UPDATE users SET name = '$fullname', email = '$email', phone = '$phone', password = '$hashed' WHERE id = $user_id"
            );
        } else {
            $this->conn->query(
                "UPDATE users SET name = '$fullname', email = '$email', phone = '$phone' WHERE id = $user_id"
            );
        }

        sendSuccess(null, 'Profile updated successfully');
    }

    // POST profile/picture (multipart, field name "profile_pic")
    public function uploadPicture($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] != 0) {
            sendError('No image uploaded');
            return;
        }

        $target_dir = __DIR__ . '/../../uploads/profile_pics/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
            sendError('Failed to upload image');
            return;
        }

        $this->conn->query("UPDATE users SET profile_pic = '$new_filename' WHERE id = $user_id");
        sendSuccess(['profile_pic' => $new_filename], 'Profile picture updated');
    }

    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }
}
