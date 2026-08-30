<?php
// api/services/ContactService.php
//
// Contact-form submissions for the mobile app. Ports contact.php's validation
// and insert into contact_messages (read by the admin on admin_messages.php).

require_once 'config/database.php';
require_once __DIR__ . '/../../contact_lib.php';

class ContactService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        contact_ensure_schema($conn);
    }

    // POST contact  { name, email, subject, message }
    public function create($input) {
        $name    = trim($input['name'] ?? '');
        $email   = trim($input['email'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $message = trim($input['message'] ?? '');

        $errors = [];
        if ($name === '') {
            $errors[] = 'Please tell us your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($message) < 5) {
            $errors[] = 'Your message is a little short.';
        }
        if ($errors) {
            sendError(implode(' ', $errors));
        }

        $n = $this->conn->real_escape_string(mb_substr($name, 0, 120));
        $e = $this->conn->real_escape_string(mb_substr($email, 0, 160));
        $s = $this->conn->real_escape_string(mb_substr($subject, 0, 160));
        $m = $this->conn->real_escape_string(mb_substr($message, 0, 4000));
        $now_utc = gmdate('Y-m-d H:i:s');

        $ok = $this->conn->query("INSERT INTO contact_messages (name, email, subject, message, created_at)
                                  VALUES ('$n', '$e', '$s', '$m', '$now_utc')");
        if ($ok) {
            sendSuccess(null, "Message sent! We'll get back to you within one business day.");
        } else {
            sendError('Could not send your message. Please try again.');
        }
    }
}
