<?php
// api/services/RecipientService.php
// "My Relations" for the mobile app — mirrors profile_relations.php's core
// CRUD (recipients + their occasions) over recipients_lib.php. Photo upload
// isn't wired here yet; recipients show with an initials avatar in the app,
// same fallback the website uses when no photo is set.

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../recipients_lib.php';

class RecipientService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        recip_ensure_schema($conn);
    }

    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }

    // GET recipients — every saved person (with their occasions) + the
    // soonest-first upcoming-occasions strip.
    public function getAll($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        $recipients = recip_list_for_user($this->conn, $user_id);
        $upcoming = recip_upcoming_for_user($this->conn, $user_id, 8);
        sendSuccess([
            'recipients' => array_map([$this, 'shapeRecipient'], $recipients),
            'upcoming'   => array_map([$this, 'shapeUpcoming'], $upcoming),
        ]);
    }

    // POST recipients
    //   { name, relationship, phone, email, house_no, street, city_line, zip, notes,
    //     occasion_type?, occasion_label?, occasion_date? }  (occasion fields optional)
    public function create($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            sendError('Please enter their name.');
            return;
        }

        $new_id = recip_create($this->conn, $user_id, $input);

        $occ_date = trim($input['occasion_date'] ?? '');
        if ($new_id > 0 && $occ_date !== '') {
            recip_occasion_add($this->conn, $new_id, $user_id, [
                'occasion_type' => $input['occasion_type'] ?? 'birthday',
                'label'         => $input['occasion_label'] ?? '',
                'occasion_date' => $occ_date,
            ]);
        }

        sendSuccess(['id' => $new_id], $name . ' was added to My Relations.');
    }

    // PUT recipients/single?id=
    public function update($id, $input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        $id = intval($id);
        if (!recip_get($this->conn, $id, $user_id)) {
            sendError('That person could not be found.', 404);
            return;
        }
        recip_update($this->conn, $id, $user_id, $input);
        sendSuccess(null, 'Changes saved.');
    }

    // DELETE recipients/single?id=
    public function delete($id, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        recip_delete($this->conn, intval($id), $user_id);
        sendSuccess(null, 'Removed from My Relations.');
    }

    // POST recipients/occasions  { recipient_id, occasion_type, label, occasion_date }
    public function addOccasion($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        $recipient_id = intval($input['recipient_id'] ?? 0);
        $new_id = recip_occasion_add($this->conn, $recipient_id, $user_id, $input);
        if ($new_id <= 0) {
            sendError('Could not add that occasion. Check the date and try again.');
            return;
        }
        sendSuccess(['id' => $new_id], 'Occasion saved.');
    }

    // DELETE recipients/occasions?id=
    public function deleteOccasion($id, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        recip_occasion_delete($this->conn, intval($id), $user_id);
        sendSuccess(null, 'Occasion removed.');
    }

    private function shapeRecipient($r) {
        return [
            'id'           => (int) $r['id'],
            'name'         => $r['name'],
            'relationship' => $r['relationship'],
            'phone'        => $r['phone'],
            'email'        => $r['email'],
            'house_no'     => $r['house_no'],
            'street'       => $r['street'],
            'city_line'    => $r['city_line'],
            'zip'          => $r['zip'],
            'notes'        => $r['notes'],
            'photo'        => $r['photo'] ?? null,
            'occasions'    => array_map(function ($o) {
                return [
                    'id'            => (int) $o['id'],
                    'occasion_type' => $o['occasion_type'],
                    'label'         => recip_occasion_label($o),
                    'occasion_date' => $o['occasion_date'],
                    'days_until'    => $o['days_until'],
                ];
            }, $r['occasions']),
        ];
    }

    private function shapeUpcoming($u) {
        return [
            'occasion_id'    => (int) $u['id'],
            'recipient_id'   => (int) $u['recipient_id'],
            'recipient_name' => $u['recipient_name'],
            'relationship'   => $u['relationship'],
            'photo'          => $u['photo'] ?? null,
            'occasion_type'  => $u['occasion_type'],
            'label'          => recip_occasion_label($u),
            'occasion_date'  => $u['occasion_date'],
            'days_until'     => $u['days_until'],
        ];
    }
}
