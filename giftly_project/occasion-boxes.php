<?php
include 'db_connect.php';
include 'catalog_lib.php';
catalog_ensure_schema($conn);
include 'header.php';

$cat_type      = 'occasion_box';
$cat_title     = 'Occasion Boxes';
$cat_subtitle  = 'Ready-made gift boxes, curated for every celebration.';
$cat_empty_msg = 'No occasion boxes available yet — check back soon!';
$cat_limit     = 12;

include 'catalog_grid.php';
include 'footer.php';
