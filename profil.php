<?php
require_once __DIR__ . '/config/database.php';
checkAuth();

$role = $_SESSION['user_role'] ?? 'pengunjung';
if ($role === 'admin') {
    header("Location: " . BASE_URL . "admin/pengaturan.php");
} elseif ($role === 'petugas') {
    header("Location: " . BASE_URL . "petugas/index.php");
} else {
    header("Location: " . BASE_URL . "pengunjung/profil.php");
}
exit;
