<?php
/** Shared "needs attention" counts for the admin sidebar badges + top-bar bell. */

if (!function_exists('admin_notif_counts')) {

    function admin_notif_counts($conn) {
        static $cache = null;
        if ($cache !== null) return $cache;

        $q = function ($sql) use ($conn) {
            $res = @$conn->query($sql);
            return $res ? (int) ($res->fetch_assoc()['c'] ?? 0) : 0;
        };

        $messages = $q("SELECT COUNT(*) AS c FROM contact_messages WHERE is_read = FALSE AND archived = FALSE");
        $cancels  = $q("SELECT COUNT(*) AS c FROM orders WHERE cancel_status = 'requested'");
        $unpaid   = $q("SELECT COUNT(*) AS c FROM orders
                        WHERE payment_status = 'unpaid' AND status = 'pending'
                          AND payment_method NOT IN ('cod')");

        $cache = [
            'messages' => $messages,
            'cancels'  => $cancels,
            'unpaid'   => $unpaid,
            'orders'   => $cancels + $unpaid,
            'total'    => $messages + $cancels + $unpaid,
        ];
        return $cache;
    }
}
