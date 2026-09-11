<?php
require_once __DIR__ . '/config/database.php';

session_unset();
session_destroy();

session_start();
setFlash('info', 'Anda telah berhasil keluar.');
header("Location: " . BASE_URL . "login.php");
exit;
