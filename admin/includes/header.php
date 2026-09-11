<?php
require_once __DIR__ . '/../../config/database.php';
checkAuth('admin'); // Wajib role admin

$settings = getSettings($pdo);
$pageTitle = isset($pageTitle) ? $pageTitle . ' - Admin Panel' : 'Administrator Panel';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Chart.js for Analytics -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <!-- App Style -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="admin-body">
  <div class="admin-layout">
