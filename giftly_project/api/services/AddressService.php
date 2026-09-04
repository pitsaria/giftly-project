<?php
// api/services/AddressService.php
// Mirrors profile_addresses.php for standalone clients — labels (Home/Office/
// Other), House/Unit + Barangay parts, and a per-user default address.

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../address_lib.php';

class AddressService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        addr_ensure_schema($conn);
    }

    // GET addresses
    public function getAll($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $result = $this->conn->query("SELECT * FROM addresses WHERE user_id = $user_id
                                      ORDER BY is_default DESC, id DESC");
        $addresses = [];
        while ($row = $result->fetch_assoc()) {
            $row['is_default'] = addr_is_default($row['is_default'] ?? false);
            $addresses[] = $row;
        }
        sendSuccess(['addresses' => $addresses]);
    }

    // POST addresses
    //   { label_choice, label_other, house_no, address, barangay, city,
    //     province, zip, make_default }
    // Also accepts the old flat { label, address, city, province, zip }.
    public function create($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        // --- label ---
        if (isset($input['label_choice'])) {
            $choice = $input['label_choice'];
            if ($choice === 'Other') {
                $label_raw = trim($input['label_other'] ?? '');
                if ($label_raw === '') $label_raw = 'Other';
            } else {
                $label_raw = in_array($choice, addr_labels(), true) ? $choice : 'Home';
            }
        } else {
            $label_raw = trim($input['label'] ?? 'Home');
        }
        $label = $this->conn->real_escape_string(mb_substr($label_raw, 0, 50));

        // --- compose the street line from the detailed parts (same as profile_addresses.php) ---
        $house_no = trim($input['house_no'] ?? '');
        $street   = trim($input['address'] ?? '');
        $barangay = trim($input['barangay'] ?? '');
        $line = trim($house_no . ' ' . $street);
        if ($barangay !== '') {
            $line .= ($line !== '' ? ', ' : '')
                   . (preg_match('/^(brgy|barangay|bgy)\b/i', $barangay) ? $barangay : 'Brgy. ' . $barangay);
        }
        $address  = $this->conn->real_escape_string(mb_substr($line, 0, 255));
        $city     = $this->conn->real_escape_string($input['city'] ?? '');
        $province = $this->conn->real_escape_string($input['province'] ?? '');
        $zip      = $this->conn->real_escape_string($input['zip'] ?? '');

        if ($address === '' || $city === '' || $province === '' || $zip === '') {
            sendError('Street address, city, province, and ZIP are required');
            return;
        }

        $existing = (int) ($this->conn->query("SELECT COUNT(*) AS c FROM addresses WHERE user_id = $user_id")
                          ->fetch_assoc()['c'] ?? 0);
        $make_default = ($existing === 0) || !empty($input['make_default']);

        $ok = $this->conn->query("INSERT INTO addresses (user_id, label, address, city, province, zip)
                                  VALUES ($user_id, '$label', '$address', '$city', '$province', '$zip')");
        if (!$ok) {
            sendError('Failed to save address: ' . $this->conn->error);
            return;
        }

        $new_id = (int) $this->conn->insert_id;
        if ($new_id <= 0) {
            $new_id = (int) ($this->conn->query("SELECT id FROM addresses WHERE user_id = $user_id ORDER BY id DESC LIMIT 1")
                            ->fetch_assoc()['id'] ?? 0);
        }
        if ($make_default && $new_id > 0) {
            addr_set_default($this->conn, $user_id, $new_id);
        }

        sendSuccess(['id' => $new_id, 'is_default' => $make_default], 'Address saved successfully');
    }

    // PUT addresses/default?id=
    public function setDefault($id, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        if (addr_set_default($this->conn, $user_id, intval($id))) {
            sendSuccess(null, 'Default address updated');
        } else {
            sendError('Address not found', 404);
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
        $dr = $this->conn->query("SELECT is_default FROM addresses WHERE id = $id AND user_id = $user_id");
        $was_default = ($dr && $dr->num_rows) && addr_is_default($dr->fetch_assoc()['is_default']);

        $this->conn->query("DELETE FROM addresses WHERE id = $id AND user_id = $user_id");

        // Promote the newest remaining address if we removed the default.
        if ($was_default) {
            $nx = $this->conn->query("SELECT id FROM addresses WHERE user_id = $user_id ORDER BY id DESC LIMIT 1");
            if ($nx && $nx->num_rows) {
                addr_set_default($this->conn, $user_id, (int) $nx->fetch_assoc()['id']);
            }
        }

        sendSuccess(null, 'Address deleted successfully');
    }

    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }
}
