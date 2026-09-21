<?php
// The old single About page was split into four (company-history.php,
// services.php, about-app.php, developers.php). Keep this URL alive for old
// links and bookmarks.
header('Location: company-history.php', true, 301);
exit();
