<?php
// api/services/AddressService.php
// Mirrors profile_addresses.php for standalone clients.

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';

class AddressService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // GET addresses
    public function getAll($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $result = $this->conn->query("SELECT * FROM addresses WHERE user_id = $user_id ORDER BY id DESC");
        $addresses = [];
        while ($row = $result->fetch_assoc()) {
            $addresses[] = $row;
        }
        sendSuccess(['addresses' => $addresses]);
    }

    // POST addresses { label, address, city, province, zip }
    public function create($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $label = $this->conn->real_escape_string($input['label'] ?? '');
        $address = $this->conn->real_escape_string($input['address'] ?? '');
        $city = $this->conn->real_escape_string($input['city'] ?? '');
        $province = $this->conn->real_escape_string($input['province'] ?? '');
        $zip = $this->conn->real_escape_string($input['zip'] ?? '');

        if (empty($address) || empty($city) || empty($province) || empty($zip)) {
            sendError('Address, city, province, and zip are required');
            return;
        }

        $sql = "INSERT INTO addresses (user_id, label, address, city, province, zip)
                VALUES ($user_id, '$label', '$address', '$city', '$province', '$zip')";

        if ($this->conn->query($sql)) {
            sendSuccess(['id' => $this->conn->insert_id], 'Address saved successfully');
        } else {
            sendError('Failed to save address: ' . $this->conn->error);
        }
    }

    // DELETE addresses/single?id=
    public function delete($id, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $id = intval($id);
        $this->conn->query("DELETE FROM addresses WHERE id = $id AND user_id = $user_id");
        sendSuccess(null, 'Address deleted successfully');
    }

    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }
}
