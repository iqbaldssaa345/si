<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/database.php';
}
$settings = getSettings($pdo);
$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . $settings['nama_sistem'] : $settings['nama_sistem'] . ' - ' . $settings['tagline'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($settings['deskripsi'] ?? 'Sistem Informasi dan Pemesanan Tiket Wisata') ?>">
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="https://img.icons8.com/fluency/48/mountain.png">
  
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Custom CSS Design System -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
