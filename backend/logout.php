<?php
// backend/logout.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
session_unset();
session_destroy();

redirect('/public/login.html?msg=' . urlencode('Anda telah logout.'));