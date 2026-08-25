<?php
// api/services/AuthHelper.php
//
// Shared auth resolution for API services. The website's own pages call this
// API over a loopback request with no Authorization header, so they keep
// working exactly as before via the $_SESSION fallback. A standalone client
// (the Ionic app) sends "Authorization: Bearer <token>" instead.

class AuthHelper {
    // Resolve the current user id from a Bearer token, falling back to the
    // PHP session (website behavior, unchanged).
    public static function resolveUserId($conn, $headers) {
        $token = self::extractBearerToken($headers);

        if ($token) {
            $escaped = $conn->real_escape_string($token);
            $result = $conn->query(
                "SELECT user_id FROM auth_tokens WHERE token = '$escaped' AND expires_at > CURRENT_TIMESTAMP"
            );
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return (int) $row['user_id'];
            }
            return null;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function resolveIsAdmin($conn, $headers) {
        $user_id = self::resolveUserId($conn, $headers);
        if (!$user_id) {
            return false;
        }
        $result = $conn->query("SELECT role FROM users WHERE id = $user_id");
        if (!$result || $result->num_rows == 0) {
            return false;
        }
        $row = $result->fetch_assoc();
        return $row['role'] === 'admin';
    }

    // Issues a new token for a user, valid for 30 days.
    public static function issueToken($conn, $user_id) {
        $token = bin2hex(random_bytes(32));
        $conn->query(
            "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES ($user_id, '$token', CURRENT_TIMESTAMP + INTERVAL '30 days')"
        );
        return $token;
    }

    // Revokes the token in the Authorization header, if any (mobile logout).
    public static function revokeToken($conn, $headers) {
        $token = self::extractBearerToken($headers);
        if ($token) {
            $escaped = $conn->real_escape_string($token);
            $conn->query("DELETE FROM auth_tokens WHERE token = '$escaped'");
        }
    }

    private static function extractBearerToken($headers) {
        $auth = $headers['Authorization'] ?? '';
        if (stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        return null;
    }
}
