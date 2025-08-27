<?php
$sessionLifetime = 60 * 60 * 24 * 7; // 7 days
ini_set('session.gc_maxlifetime', $sessionLifetime);
session_set_cookie_params($sessionLifetime);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
