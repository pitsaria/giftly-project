<?php
// api/services/ReviewService.php
//
// Product reviews for the mobile app. Ports submit_review.php and
// get_product_reviews.php (data only, no HTML) onto the token-authenticated API.

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../reviews_lib.php';

class ReviewService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        reviews_ensure_schema($conn);
    }

    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }

    // GET reviews?product_id=
    public function get($product_id, $headers) {
        $pid = intval($product_id);
        if ($pid <= 0) {
            sendError('Missing product ID', 400);
        }

        $summary = reviews_summary($this->conn, $pid);
        $list = reviews_list($this->conn, $pid, 50);

        $user_id = $this->getUserId($headers);
        $my_review = null;
        $can_review = false;
        if ($user_id) {
            $mine = reviews_user_review($this->conn, $user_id, $pid);
            if ($mine) {
                $my_review = $this->shape($mine);
            } else {
                $can_review = reviews_eligible_order($this->conn, $user_id, $pid) > 0;
            }
        }

        $reviews = [];
        foreach ($list as $r) {
            $reviews[] = $this->shape($r);
        }

        sendSuccess([
            'avg'        => (float) $summary['avg'],
            'count'      => (int) $summary['count'],
            'reviews'    => $reviews,
            'my_review'  => $my_review,
            'can_review' => $can_review,
        ]);
    }

    // POST reviews  { product_id, rating, comment }
    public function create($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }

        $product_id = intval($input['product_id'] ?? 0);
        $rating     = intval($input['rating'] ?? 0);
        $comment    = trim($input['comment'] ?? '');

        if ($product_id <= 0 || $rating < 1 || $rating > 5) {
            sendError('Please give a star rating.');
        }
        $comment = mb_substr($comment, 0, 1500);

        if (reviews_user_review($this->conn, $user_id, $product_id)) {
            sendError("You've already reviewed this item.");
        }

        $order_id = reviews_eligible_order($this->conn, $user_id, $product_id);
        if ($order_id === 0) {
            sendError("You can only review items from an order you've received.");
        }

        $comment_esc = $this->conn->real_escape_string($comment);
        $this->conn->query("
            INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment, status)
            VALUES ($product_id, $user_id, $order_id, $rating, '$comment_esc', 'published')
            ON CONFLICT (product_id, user_id) DO NOTHING
        ");

        $summary = reviews_summary($this->conn, $product_id);
        sendSuccess([
            'avg'   => (float) $summary['avg'],
            'count' => (int) $summary['count'],
        ], 'Thanks for your review!');
    }

    private function shape($r) {
        return [
            'id'         => intval($r['id']),
            'user_name'  => $r['user_name'] ?? '',
            'rating'     => intval($r['rating']),
            'comment'    => $r['comment'] ?? '',
            'created_at' => $r['created_at'],
        ];
    }
}
