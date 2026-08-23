<?php
/**
 * User Logout (logout.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

session_unset();
session_destroy();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

setFlash('info', 'คุณได้ออกจากระบบเรียบร้อยแล้ว');
header('Location: login.php');
exit;
