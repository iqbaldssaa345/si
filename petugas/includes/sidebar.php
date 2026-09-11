<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$userName = $_SESSION['user_nama'] ?? 'Petugas Loket';
$userEmail = $_SESSION['user_email'] ?? 'petugas@wisata.com';
$initial = strtoupper(substr($userName, 0, 1));

// Counter Hari Ini
$todayCntPengunjung = 0;
$todayCntValidasi = 0;
if (isset($pdo) && $pdo) {
    try {
        $today = date('Y-m-d');
        $uid = $_SESSION['user_id'] ?? 0;
        $stmtP = $pdo->prepare("SELECT COALESCE(SUM(jumlah_pengunjung), 0) FROM presensi_kunjungan WHERE tanggal_kunjungan = ? AND petugas_id = ?");
        $stmtP->execute([$today, $uid]);
        $todayCntPengunjung = (int)$stmtP->fetchColumn();

        $todayCntValidasi = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status_kunjungan = 'sudah_digunakan' AND DATE(waktu_checkin) = CURDATE()")->fetchColumn();
    } catch (Exception $e) {}
}
?>
<aside class="admin-sidebar">
  
  <!-- Brand Header -->
  <div class="admin-sidebar-brand" style="padding: 1.25rem 1.2rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
    <div class="admin-brand-icon" style="background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%); box-shadow: 0 4px 14px rgba(13, 148, 136, 0.4); width: 42px; height: 42px; border-radius: 0.75rem;">
      <i class="fa-solid fa-id-badge text-white" style="font-size: 1.15rem;"></i>
    </div>
    <div style="overflow: hidden; flex: 1;">
      <span style="display: block; font-weight: 800; font-size: 0.95rem; color: #ffffff; letter-spacing: -0.01em; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; font-family: 'Outfit', sans-serif;">
        <?= htmlspecialchars($settings['nama_sistem'] ?? 'Pesona Nusantara') ?>
      </span>
      <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.72rem; color: #2dd4bf; font-weight: 700;">
        <span style="width: 7px; height: 7px; border-radius: 50%; background: #22c55e; display: inline-block; box-shadow: 0 0 10px #22c55e; animation: pulse 2s infinite;"></span>
        Petugas Loket & Gate
      </span>
    </div>
  </div>

  <!-- Profile Card -->
  <div class="admin-profile-pill" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); margin: 1rem 1rem 0.5rem 1rem; border-radius: 0.85rem; padding: 0.75rem;">
    <div class="admin-avatar" style="background: linear-gradient(135deg, #0d9488, #0284c7); width: 38px; height: 38px; font-weight: 800; font-size: 1rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
      <?= $initial ?>
    </div>
    <div style="overflow: hidden; flex: 1;">
      <strong style="display: block; font-size: 0.875rem; color: #f8fafc; font-weight: 800; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
        <?= htmlspecialchars($userName) ?>
      </strong>
      <span style="font-size: 0.72rem; color: #94a3b8; display: block; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
        <?= htmlspecialchars($userEmail) ?>
      </span>
    </div>
  </div>

  <!-- Navigation Menu -->
  <ul class="admin-nav-list" style="padding: 0.75rem 1rem;">
    
    <li style="padding: 0.6rem 0.5rem 0.25rem 0.5rem; font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
      Operasional Gate
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>petugas/index.php" class="admin-nav-link <?= ($currentFile == 'index.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-gauge-high"></i>
        <span style="flex: 1;">Dashboard Petugas</span>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>petugas/validasi_tiket.php" class="admin-nav-link <?= ($currentFile == 'validasi_tiket.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-qrcode"></i>
        <span style="flex: 1;">Scan & Validasi Tiket</span>
        <?php if ($todayCntValidasi > 0): ?>
          <span style="background: #059669; color: #ffffff; font-size: 0.68rem; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 9999px;">
            <?= $todayCntValidasi ?> Scan
          </span>
        <?php endif; ?>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>petugas/input_kunjungan.php" class="admin-nav-link <?= ($currentFile == 'input_kunjungan.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-pen-to-square"></i>
        <span style="flex: 1;">Input Presensi Loket</span>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>petugas/riwayat_input.php" class="admin-nav-link <?= ($currentFile == 'riwayat_input.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-clipboard-list"></i>
        <span style="flex: 1;">Riwayat Input Saya</span>
        <?php if ($todayCntPengunjung > 0): ?>
          <span style="background: #0284c7; color: #ffffff; font-size: 0.68rem; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 9999px;">
            <?= number_format($todayCntPengunjung) ?> Org
          </span>
        <?php endif; ?>
      </a>
    </li>

  </ul>

  <!-- Sidebar Footer -->
  <div class="admin-sidebar-footer" style="padding: 1rem; border-top: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; gap: 0.5rem;">
    <a href="<?= BASE_URL ?>index.php" target="_blank" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.06); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.1); justify-content: center; width: 100%; border-radius: 0.65rem; font-weight: 700;">
      <i class="fa-solid fa-globe"></i> Buka Portal Wisata
    </a>
    <a href="<?= BASE_URL ?>logout.php" class="btn btn-danger btn-sm" style="justify-content: center; width: 100%; border-radius: 0.65rem; font-weight: 700;" onclick="return confirm('Apakah Anda yakin ingin keluar dari sesi petugas?')">
      <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar Shift
    </a>
  </div>

</aside>
