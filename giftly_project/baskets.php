<?php
include 'db_connect.php';
include 'catalog_lib.php';
catalog_ensure_schema($conn);
include 'header.php';

$cat_type      = 'basket';
$cat_title     = 'Baskets';
$cat_subtitle  = 'Pre-made gift baskets, ready to send.';
$cat_empty_msg = 'No baskets available yet — check back soon!';
$cat_limit     = 12;

include 'catalog_grid.php';
include 'footer.php';
