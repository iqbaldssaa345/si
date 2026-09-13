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
  
  <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Chart.js for Analytics -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <!-- App Style -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
  <style>
    .admin-sidebar { scrollbar-width: none !important; -ms-overflow-style: none !important; }
    .admin-sidebar::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; }
  </style>
</head>
<body class="admin-body">
  <div class="admin-layout">
