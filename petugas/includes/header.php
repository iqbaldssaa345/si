<?php
require_once __DIR__ . '/../../config/database.php';
checkAuth('petugas'); // Wajib role petugas (atau admin)

$settings = getSettings($pdo);
$pageTitle = isset($pageTitle) ? $pageTitle . ' - Portal Petugas Loket' : 'Panel Petugas Loket & Gate';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <!-- Google Fonts: Inter & Outfit -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">

  <!-- Font Awesome 6.5 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <!-- HTML5 QR Code Scanner Library for Web Cameras -->
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

  <!-- App Style -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">

  <style>
    :root {
      --font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      --font-heading: 'Outfit', 'Inter', sans-serif;
      --petugas-primary: #0d9488;
      --petugas-secondary: #0284c7;
      --petugas-dark: #0f172a;
    }
    
    body.admin-body {
      font-family: var(--font-body);
      background-color: #f1f5f9;
      color: #1e293b;
      margin: 0;
      padding: 0;
      font-size: 0.875rem;
      line-height: 1.5;
    }

    h1, h2, h3, h4, .font-heading {
      font-family: var(--font-heading);
      letter-spacing: -0.02em;
    }

    /* Layout Geometry Fit Zoom 100% */
    .admin-layout {
      display: flex;
      min-height: 100vh;
      background: #f1f5f9;
    }

    .admin-sidebar {
      width: 225px;
      min-width: 225px;
      background: linear-gradient(180deg, #090d16 0%, #0f172a 100%);
      color: #ffffff;
      display: flex;
      flex-direction: column;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
      z-index: 50;
      border-right: 1px solid rgba(255,255,255,0.08);
    }

    .admin-main {
      flex: 1;
      padding: 0.85rem 1.25rem;
      min-width: 0;
      max-width: 1600px;
      margin: 0 auto;
      width: 100%;
    }

    /* Compact & Luxury Topbar */
    .admin-topbar-luxury {
      background: #ffffff;
      padding: 0.85rem 1.25rem;
      border-radius: 1rem;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 6px rgba(0,0,0,0.02);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1.15rem;
    }

    /* Luxury Badges */
    .badge-luxury {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.2rem 0.55rem;
      border-radius: 9999px;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.02em;
    }
    .badge-luxury-primary {
      background: #ccfbf1;
      color: #0f766e;
      border: 1px solid #99f6e4;
    }
    .badge-luxury-success {
      background: #dcfce7;
      color: #15803d;
      border: 1px solid #bbf7d0;
    }
    .badge-luxury-warning {
      background: #fef3c7;
      color: #b45309;
      border: 1px solid #fde68a;
    }
    .badge-luxury-danger {
      background: #fee2e2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }

    /* KPI Cards Fitted Proportions */
    .kpi-card-luxury {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 1rem;
      padding: 1.1rem;
      position: relative;
      overflow: hidden;
      box-shadow: 0 2px 6px rgba(0,0,0,0.02);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-card-luxury:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 16px -4px rgba(15, 23, 42, 0.06);
    }
    .kpi-icon-wrap {
      width: 40px;
      height: 40px;
      border-radius: 0.65rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
    }

    /* Button Luxury Pulse */
    .btn-luxury-pulse {
      position: relative;
      overflow: hidden;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-luxury-pulse:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 16px -4px rgba(13, 148, 136, 0.4);
    }

    /* Tables */
    .card-table-luxury {
      background: #ffffff;
      border-radius: 1rem;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 6px rgba(0,0,0,0.02);
      overflow: hidden;
    }
    .table-luxury {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
    }
    .table-luxury th {
      background: #f8fafc;
      padding: 0.75rem 1rem;
      font-size: 0.72rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748b;
      border-bottom: 1px solid #e2e8f0;
      text-align: left;
    }
    .table-luxury td {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
      color: #334155;
    }
    .table-luxury tr:last-child td {
      border-bottom: none;
    }
    .table-luxury tr:hover td {
      background-color: #f8fafc;
    }

    /* ======================================================== */
    /* LUXURY MODAL BACKDROP & BOX POPUP SYSTEM                 */
    /* ======================================================== */
    .luxury-modal-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(15, 23, 42, 0.72);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      z-index: 99999;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.25rem;
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .luxury-modal-backdrop.active {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }
    .luxury-modal-box {
      background: #ffffff;
      border-radius: 1.25rem;
      width: 100%;
      max-width: 540px;
      box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.4);
      border: 1px solid rgba(255, 255, 255, 0.6);
      transform: scale(0.92) translateY(10px);
      transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      overflow: hidden;
      position: relative;
    }
    .luxury-modal-backdrop.active .luxury-modal-box {
      transform: scale(1) translateY(0);
    }

    /* Laser Scanner Animation */
    @keyframes scanLaser {
      0% { top: 10%; opacity: 0.4; }
      50% { opacity: 1; }
      100% { top: 88%; opacity: 0.4; }
    }
  </style>
</head>
<body class="admin-body">
  <div class="admin-layout">
