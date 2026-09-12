<?php
// api/services/ProfileService.php
// Mirrors profile_settings.php for standalone clients.

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../pwd_otp_lib.php';
require_once __DIR__ . '/../../supabase_storage.php';

class ProfileService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        pwd_otp_ensure_schema($conn);
    }

    // GET profile
    public function getProfile($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $user = $this->conn->query(
            "SELECT name, email, phone, profile_pic, google_id FROM users WHERE id = $user_id"
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
            'address_count' => intval($addressCount),
            // Google-linked accounts have a random password they never set —
            // mobile hides the change-password section for these entirely.
            'google_linked' => !empty($user['google_id']),
        ]);
    }

    // POST profile/send-pwd-code — email a 6-digit code to confirm a password
    // change (mirrors profile_send_pwd_code.php).
    public function sendPwdCode($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $u = $this->conn->query("SELECT email, google_id FROM users WHERE id = $user_id")->fetch_assoc();
        if (!empty($u['google_id'])) {
            sendError('This account signs in with Google and has no password to change.');
        }

        $wait = pwd_otp_seconds_until_resend($this->conn, $user_id, 'change');
        if ($wait > 0) {
            sendError("Please wait {$wait}s before requesting another code.");
        }

        [$sent, $err] = pwd_otp_send($this->conn, $user_id, $u['email'], 'change');
        if (!$sent) {
            sendError($err ?: "Couldn't send the code right now.");
        }
        sendSuccess(['cooldown' => pwd_otp_cooldown()], 'We emailed a 6-digit code to ' . $u['email'] . '.');
    }

    // PUT profile { firstname, lastname, email, phone, current_password?, new_password? }
    public function updateProfile($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $user = $this->conn->query("SELECT name, email, phone, password, google_id FROM users WHERE id = $user_id")->fetch_assoc();
        $nameParts = explode(' ', $user['name'], 2);

        $firstname = !empty($input['firstname']) ? $this->conn->real_escape_string($input['firstname']) : $nameParts[0];
        $lastname = !empty($input['lastname']) ? $this->conn->real_escape_string($input['lastname']) : ($nameParts[1] ?? '');
        $email = !empty($input['email']) ? $this->conn->real_escape_string($input['email']) : $user['email'];
        $phone = !empty($input['phone']) ? $this->conn->real_escape_string($input['phone']) : $user['phone'];
        $fullname = trim($firstname . ' ' . $lastname);

        $new_pass = $input['new_password'] ?? '';
        if (!empty($new_pass)) {
            if (!empty($user['google_id'])) {
                sendError('This account signs in with Google and has no password to change.');
                return;
            }
            $current_pass = $input['current_password'] ?? '';
            if (!password_verify($current_pass, $user['password'])) {
                sendError('Current password is incorrect');
                return;
            }
            [$strong, $strong_err] = pwd_strength_check($new_pass);
            if (!$strong) {
                sendError($strong_err);
                return;
            }
            [$code_ok, $code_err] = pwd_otp_verify($this->conn, $user_id, $input['pwd_code'] ?? '', 'change');
            if (!$code_ok) {
                sendError($code_err ?: 'Enter the code we emailed you.');
                return;
            }
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $this->conn->query(
                "UPDATE users SET name = '$fullname', email = '$email', phone = '$phone', password = '$hashed' WHERE id = $user_id"
            );
            pwd_otp_clear($this->conn, $user_id, 'change');
        } else {
            $this->conn->query(
                "UPDATE users SET name = '$fullname', email = '$email', phone = '$phone' WHERE id = $user_id"
            );
        }

        sendSuccess(null, 'Profile updated successfully');
    }

    // POST profile/picture (multipart, field name "profile_pic")
    // Uploaded to Supabase Storage (same as product images / recipient photos) —
    // Render's container filesystem is ephemeral, so a plain local upload
    // vanishes on the next deploy/restart. Mirrors profile_settings.php's fix.
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

        $uploaded_url = supabase_upload_image($_FILES['profile_pic']);
        if ($uploaded_url === null) {
            sendError("Couldn't upload the photo. Try a JPG or PNG under a few MB.");
            return;
        }

        $old = $this->conn->query("SELECT profile_pic FROM users WHERE id = $user_id")->fetch_assoc()['profile_pic'] ?? '';

        $escaped = $this->conn->real_escape_string($uploaded_url);
        $this->conn->query("UPDATE users SET profile_pic = '$escaped' WHERE id = $user_id");

        if (!empty($old)) {
            supabase_delete_image($old);
        }

        sendSuccess(['profile_pic' => $uploaded_url], 'Profile picture updated');
    }

    // DELETE profile/picture — remove the current profile picture.
    public function removePicture($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $old = $this->conn->query("SELECT profile_pic FROM users WHERE id = $user_id")->fetch_assoc()['profile_pic'] ?? '';
        if (!empty($old)) {
            supabase_delete_image($old);
        }
        $this->conn->query("UPDATE users SET profile_pic = NULL WHERE id = $user_id");
        sendSuccess(null, 'Profile picture removed');
    }

    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }
}
