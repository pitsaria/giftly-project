<?php
// api/services/AddonService.php
// Gift wrapping & checkout add-ons for the mobile app — thin wrapper over
// addons_lib.php (same library checkout_selected.php / box_checkout.php use).

require_once 'config/database.php';
require_once __DIR__ . '/../../addons_lib.php';

class AddonService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        addons_ensure_schema($conn);
    }

    // GET addons — active add-ons available at checkout, cheapest first.
    public function getAll() {
        $rows = addons_list($this->conn);
        $addons = [];
        foreach ($rows as $r) {
            $addons[] = [
                'id'          => (int) $r['id'],
                'name'        => $r['name'],
                'description' => $r['description'],
                'price'       => (float) $r['price'],
                'image'       => $r['image'],
            ];
        }
        sendSuccess(['addons' => $addons]);
    }
}
