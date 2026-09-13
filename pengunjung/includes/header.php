<?php
require_once __DIR__ . '/../../config/database.php';
checkAuth('pengunjung'); // Wajib role pengunjung (atau admin/petugas jika akses)

$settings = getSettings($pdo);
$pageTitle = isset($pageTitle) ? $pageTitle . ' - Member Area' : 'Dashboard Wisatawan - ' . ($settings['nama_sistem'] ?? 'Pesona Nusantara');
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

  <!-- App Style -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">

  <style>
    :root {
      --font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      --font-heading: 'Outfit', 'Inter', sans-serif;
      --brand-primary: #0284c7;
      --brand-teal: #0d9488;
      --brand-amber: #f59e0b;
      --brand-rose: #f43f5e;
      --brand-purple: #8b5cf6;
    }
    
    body.admin-body {
      font-family: var(--font-body);
      background-color: #f1f5f9;
      color: #1e293b;
      margin: 0;
      padding: 0;
      font-size: 0.85rem;
      line-height: 1.45;
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
      max-height: 100vh;
      overflow-y: auto;
      scrollbar-width: none;
      -ms-overflow-style: none;
      z-index: 50;
      border-right: 1px solid rgba(255,255,255,0.08);
    }
    .admin-sidebar::-webkit-scrollbar { display: none; width: 0; }

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
      padding: 0.65rem 1.1rem;
      border-radius: 0.75rem;
      border: 1px solid #e2e8f0;
      box-shadow: 0 1px 4px rgba(0,0,0,0.02);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.85rem;
      margin-bottom: 0.85rem;
    }

    /* VIP & Member Badges */
    .badge-member {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.18rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.02em;
    }
    .badge-member-gold {
      background: linear-gradient(135deg, #fef3c7, #fde68a);
      color: #92400e;
      border: 1px solid #fcd34d;
    }
    .badge-member-cyan {
      background: linear-gradient(135deg, #e0f2fe, #bae6fd);
      color: #0369a1;
      border: 1px solid #7dd3fc;
    }
    .badge-member-emerald {
      background: linear-gradient(135deg, #dcfce7, #bbf7d0);
      color: #15803d;
      border: 1px solid #86efac;
    }

    /* KPI Cards Fitted Proportions */
    .kpi-card-luxury {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 0.75rem;
      padding: 0.65rem 0.85rem;
      position: relative;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,0.02);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-card-luxury:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 14px -3px rgba(15, 23, 42, 0.07);
    }
    .kpi-icon-wrap {
      width: 34px;
      height: 34px;
      border-radius: 0.45rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.95rem;
    }

    /* Button Luxury Pulse */
    .btn-luxury-pulse {
      position: relative;
      overflow: hidden;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-luxury-pulse:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 14px -3px rgba(2, 132, 199, 0.4);
    }

    /* Boarding Pass Ticket Cards */
    .ticket-card-luxury {
      background: #ffffff;
      border-radius: 0.85rem;
      border: 1px solid #e2e8f0;
      position: relative;
      overflow: hidden;
      transition: all 0.2s ease;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }
    .ticket-card-luxury:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 18px -3px rgba(15, 23, 42, 0.08);
      border-color: #cbd5e1;
    }
    .ticket-notch-left, .ticket-notch-right {
      position: absolute;
      top: 50%;
      width: 16px;
      height: 16px;
      background: #f1f5f9;
      border-radius: 50%;
      transform: translateY(-50%);
      z-index: 2;
    }
    .ticket-notch-left { left: -8px; border-right: 1px solid #e2e8f0; }
    .ticket-notch-right { right: -8px; border-left: 1px solid #e2e8f0; }

    /* Tables */
    .card-table-luxury {
      background: #ffffff;
      border-radius: 0.75rem;
      border: 1px solid #e2e8f0;
      box-shadow: 0 1px 3px rgba(0,0,0,0.02);
      overflow: hidden;
    }
    .table-luxury {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.82rem;
    }
    .table-luxury th {
      background: #f8fafc;
      padding: 0.55rem 0.85rem;
      font-size: 0.68rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748b;
      border-bottom: 1px solid #e2e8f0;
      text-align: left;
    }
    .table-luxury td {
      padding: 0.55rem 0.85rem;
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

    /* Modal System */
    .luxury-modal-backdrop {
      position: fixed !important;
      inset: 0 !important;
      background: rgba(15, 23, 42, 0.75) !important;
      backdrop-filter: blur(6px) !important;
      -webkit-backdrop-filter: blur(6px) !important;
      z-index: 99999 !important;
      display: none;
      align-items: center !important;
      justify-content: center !important;
      padding: 1rem !important;
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity 0.2s ease, visibility 0.2s ease !important;
    }
    .luxury-modal-backdrop.active {
      display: flex !important;
      opacity: 1 !important;
      visibility: visible !important;
      pointer-events: auto !important;
    }
    .luxury-modal-box {
      background: #ffffff !important;
      border-radius: 1rem !important;
      width: 100% !important;
      max-width: 520px !important;
      max-height: 90vh !important;
      overflow-y: auto !important;
      box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.45) !important;
      border: 1px solid rgba(255, 255, 255, 0.6) !important;
      transform: scale(0.94) translateY(10px) !important;
      transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
      position: relative !important;
    }
    .luxury-modal-backdrop.active .luxury-modal-box {
      transform: scale(1) translateY(0) !important;
    }

    /* Star rating interact */
    .star-rating-select {
      display: inline-flex;
      flex-direction: row-reverse;
      gap: 0.3rem;
    }
    .star-rating-select input {
      display: none;
    }
    .star-rating-select label {
      font-size: 1.6rem;
      color: #cbd5e1;
      cursor: pointer;
      transition: color 0.15s ease;
    }
    .star-rating-select label:hover,
    .star-rating-select label:hover ~ label,
    .star-rating-select input:checked ~ label {
      color: #f59e0b;
    }

    /* Toast Notification */
    #luxury-toast {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: #0f172a;
      color: #ffffff;
      padding: 0.65rem 1rem;
      border-radius: 0.5rem;
      box-shadow: 0 10px 25px rgba(0,0,0,0.25);
      display: flex;
      align-items: center;
      gap: 0.6rem;
      font-size: 0.82rem;
      font-weight: 600;
      z-index: 100000;
      opacity: 0;
      transform: translateY(15px);
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      pointer-events: none;
    }
    #luxury-toast.show {
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
    }

    /* Destinasi Card Grid */
    .dest-card-luxury {
      background: #ffffff;
      border-radius: 0.85rem;
      border: 1px solid #e2e8f0;
      overflow: hidden;
      transition: all 0.25s ease;
      display: flex;
      flex-direction: column;
    }
    .dest-card-luxury:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 24px -4px rgba(15, 23, 42, 0.08);
      border-color: #cbd5e1;
    }

    /* HP / Mobile Scanning Optimized QR Frame */
    .qr-phone-frame {
      background: #ffffff !important;
      padding: 0.85rem !important;
      border-radius: 0.85rem !important;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06) !important;
      border: 2px solid #e2e8f0 !important;
      display: inline-block !important;
      margin: 0.35rem auto 0.65rem auto !important;
      max-width: 100% !important;
      text-align: center !important;
    }
    .qr-phone-frame img {
      width: 220px;
      max-width: 65vw;
      height: auto;
      aspect-ratio: 1 / 1;
      display: block;
      margin: 0 auto;
      image-rendering: -webkit-optimize-contrast;
      image-rendering: crisp-edges;
    }

    /* Mobile / HP Responsiveness */
    @media (max-width: 768px) {
      .admin-layout {
        flex-direction: column !important;
      }
      .admin-sidebar {
        width: 100% !important;
        min-width: 100% !important;
        height: auto !important;
        position: relative !important;
        top: 0 !important;
      }
      .admin-main {
        padding: 0.75rem 0.65rem !important;
      }
      .ticket-grid-responsive {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
        text-align: center !important;
      }
      .ticket-grid-responsive > div:last-child {
        border-left: none !important;
        border-top: 1px dashed #e2e8f0 !important;
        padding-left: 0 !important;
        padding-top: 0.75rem !important;
      }
      .upcoming-banner-grid {
        grid-template-columns: 1fr !important;
        text-align: center !important;
      }
      .upcoming-banner-grid .qr-preview-box {
        margin: 0.5rem auto 0 auto !important;
        max-width: 150px !important;
      }
      .grid.grid-cols-4, .grid.grid-cols-3, .grid.grid-cols-2 {
        grid-template-columns: 1fr !important;
      }
      .grid.grid-cols-12 > div {
        grid-column: span 12 / span 12 !important;
      }
      .luxury-modal-box {
        max-width: 94vw !important;
        padding: 1rem 0.85rem !important;
        margin: 0 auto !important;
      }
    }
  </style>
</head>
<body class="admin-body">
  <div class="admin-layout">
