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
  
  <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Font Awesome 6.5 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <!-- HTML5 QR Code Scanner Library -->
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

  <!-- App Style -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">

  <style>
    :root {
      --font-body: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      --font-heading: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
      --petugas-primary: #0d9488;
      --petugas-primary-dark: #0f766e;
      --petugas-primary-light: #ccfbf1;
      --petugas-secondary: #0284c7;
      --petugas-dark: #090d16;
      --petugas-surface: #0f172a;
    }
    
    body.admin-body {
      font-family: var(--font-body);
      background-color: #f8fafc;
      color: #1e293b;
      margin: 0;
      padding: 0;
      font-size: 0.875rem;
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }

    h1, h2, h3, h4, h5, h6, .font-heading {
      font-family: var(--font-heading);
      letter-spacing: -0.02em;
    }

    /* Layout Geometry Fit */
    .admin-layout {
      display: flex;
      min-height: 100vh;
      background: #f8fafc;
    }

    .admin-sidebar {
      width: 250px;
      min-width: 250px;
      background: linear-gradient(180deg, #090d16 0%, #0f172a 100%);
      color: #ffffff;
      display: flex;
      flex-direction: column;
      position: sticky;
      top: 0;
      height: 100vh;
      max-height: 100vh;
      overflow-y: auto;
      scrollbar-width: none;
      -ms-overflow-style: none;
      z-index: 50;
      border-right: 1px solid rgba(255,255,255,0.08);
      box-shadow: 4px 0 24px rgba(0, 0, 0, 0.25);
    }
    .admin-sidebar::-webkit-scrollbar { display: none; width: 0; }

    .admin-main {
      flex: 1;
      padding: 1.25rem 1.75rem;
      min-width: 0;
      max-width: 1600px;
      margin: 0 auto;
      width: 100%;
      box-sizing: border-box;
    }

    /* Luxury Glass Topbar */
    .admin-topbar-luxury {
      background: #ffffff;
      padding: 1rem 1.5rem;
      border-radius: 1.15rem;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 15px -3px rgba(15, 23, 42, 0.04);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1.25rem;
      margin-bottom: 1.35rem;
      position: relative;
    }

    /* Luxury Badges */
    .badge-luxury {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.25rem 0.65rem;
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
    .badge-luxury-info {
      background: #e0f2fe;
      color: #0369a1;
      border: 1px solid #bae6fd;
    }

    /* Luxury KPI Stat Cards */
    .kpi-card-luxury {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 1.15rem;
      padding: 1.25rem;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.03);
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .kpi-card-luxury:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 24px -4px rgba(13, 148, 136, 0.12);
      border-color: #99f6e4;
    }
    .kpi-icon-wrap {
      width: 44px;
      height: 44px;
      border-radius: 0.75rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.15rem;
      transition: transform 0.2s ease;
    }
    .kpi-card-luxury:hover .kpi-icon-wrap {
      transform: scale(1.08);
    }

    /* Button Luxury Pulse & Glow */
    .btn-luxury-pulse {
      position: relative;
      overflow: hidden;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-luxury-pulse:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px -4px rgba(13, 148, 136, 0.45);
    }

    /* Tables */
    .card-table-luxury {
      background: #ffffff;
      border-radius: 1.15rem;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 15px -3px rgba(15, 23, 42, 0.04);
      overflow: hidden;
    }
    .table-luxury {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
    }
    .table-luxury th {
      background: #f8fafc;
      padding: 0.85rem 1.15rem;
      font-size: 0.72rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #64748b;
      border-bottom: 1px solid #e2e8f0;
      text-align: left;
    }
    .table-luxury td {
      padding: 0.85rem 1.15rem;
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

    /* Luxury Boarding Pass Ticket */
    .ticket-pass {
      background: #ffffff;
      border-radius: 1.5rem;
      border: 1.5px solid #e2e8f0;
      box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.08);
      position: relative;
      overflow: hidden;
    }
    .ticket-pass-perforation {
      position: relative;
      height: 20px;
      margin: 0 -20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .ticket-pass-perforation::before, .ticket-pass-perforation::after {
      content: '';
      width: 24px;
      height: 24px;
      background: #f8fafc;
      border-radius: 50%;
      border: 1px solid #cbd5e1;
      display: block;
    }
    .ticket-pass-perforation::before {
      margin-left: -12px;
    }
    .ticket-pass-perforation::after {
      margin-right: -12px;
    }
    .ticket-pass-line {
      flex: 1;
      border-top: 2px dashed #cbd5e1;
      margin: 0 10px;
    }

    /* Modal Backdrop & Popup System */
    .luxury-modal-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(15, 23, 42, 0.75);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
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
      border-radius: 1.35rem;
      width: 100%;
      max-width: 540px;
      box-shadow: 0 30px 70px -15px rgba(0, 0, 0, 0.45);
      border: 1px solid rgba(255, 255, 255, 0.8);
      transform: scale(0.94) translateY(12px);
      transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      overflow: hidden;
      position: relative;
    }
    .luxury-modal-backdrop.active .luxury-modal-box {
      transform: scale(1) translateY(0);
    }

    /* Laser Scanner Animation */
    @keyframes scanLaser {
      0% { top: 8%; opacity: 0.3; }
      50% { opacity: 1; }
      100% { top: 88%; opacity: 0.3; }
    }

    /* Live Pulse Dot */
    @keyframes pulseGlow {
      0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
      70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
      100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
    .pulse-online {
      animation: pulseGlow 2s infinite;
    }

    /* Print styling */
    @media print {
      .admin-sidebar, .admin-topbar-luxury, .btn, .kpi-card-luxury, form, .no-print {
        display: none !important;
      }
      .admin-layout {
        display: block !important;
        background: #ffffff !important;
      }
      .admin-main {
        padding: 0 !important;
        max-width: 100% !important;
      }
      .card-table-luxury {
        border: none !important;
        box-shadow: none !important;
      }
      .table-luxury th, .table-luxury td {
        padding: 6px 8px !important;
        font-size: 11px !important;
      }
    }
  </style>
</head>
<body class="admin-body">
  <div class="admin-layout">
