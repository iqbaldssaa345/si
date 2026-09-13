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

$sessFoto = $_SESSION['user_foto'] ?? 'default_avatar.png';
$hasSessFoto = (!empty($sessFoto) && $sessFoto !== 'default_avatar.png' && file_exists(__DIR__ . '/../../assets/uploads/users/' . $sessFoto));
?>
<aside class="admin-sidebar">
  
  <!-- Brand Header -->
  <div class="admin-sidebar-brand" style="padding: 0.6rem 0.8rem; display: flex; align-items: center; gap: 0.55rem; border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02);">
    <div class="admin-brand-icon-luxury admin-icon-petugas">
      <span class="luxury-emote-brand">🎫</span>
    </div>
    <div style="overflow: hidden; flex: 1; min-width: 0;">
      <span style="display: block; font-weight: 800; font-size: 0.88rem; color: #ffffff; letter-spacing: -0.01em; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; font-family: 'Outfit', sans-serif; line-height: 1.2;">
        <?= htmlspecialchars($settings['nama_sistem'] ?? 'Pesona Nusantara') ?>
      </span>
      <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.68rem; color: #2dd4bf; font-weight: 700; line-height: 1.2;">
        <span class="pulse-online" style="width: 5px; height: 5px; border-radius: 50%; background: #22c55e; display: inline-block; box-shadow: 0 0 6px #22c55e;"></span>
        Gate & Loket Terminal
      </span>
    </div>
  </div>

  <!-- Profile Card -->
  <div class="admin-profile-pill" style="padding: 0.35rem 0.6rem; margin: 0.3rem 0.65rem 0.2rem 0.65rem; background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 0.65rem; display: flex; align-items: center; gap: 0.5rem;">
    <?php if ($hasSessFoto): ?>
      <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($sessFoto) ?>" alt="Avatar" style="width: 28px; height: 28px; min-width: 28px; border-radius: 50%; object-fit: cover; border: 2px solid #2dd4bf; box-shadow: 0 2px 8px rgba(0,0,0,0.25);">
    <?php else: ?>
      <div class="admin-avatar" style="background: linear-gradient(135deg, #0d9488, #0284c7); width: 28px; height: 28px; min-width: 28px; font-weight: 800; font-size: 0.8rem; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; color: white; border: 1px solid rgba(255,255,255,0.2);">
        <?= $initial ?>
      </div>
    <?php endif; ?>
    <div style="overflow: hidden; flex: 1; min-width: 0;">
      <strong style="display: block; font-size: 0.8rem; color: #f8fafc; font-weight: 700; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; line-height: 1.2;">
        <?= htmlspecialchars($userName) ?>
      </strong>
      <span style="font-size: 0.68rem; color: #94a3b8; display: block; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; line-height: 1.2;">
        <?= htmlspecialchars($userEmail) ?>
      </span>
    </div>
  </div>

  <!-- Navigation Menu -->
  <ul class="admin-nav-list" style="padding: 0.75rem 0.85rem; list-style: none; display: flex; flex-direction: column; gap: 0.35rem; flex: 1; margin: 0;">
    
    <li style="padding: 0.65rem 0.6rem 0.25rem 0.6rem; font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
      Operasional Gate
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>petugas/index.php" class="admin-nav-link <?= ($currentFile == 'index.php') ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.85rem; border-radius: 0.7rem; font-size: 0.85rem; font-weight: 700; color: <?= ($currentFile == 'index.php') ? '#ffffff' : '#94a3b8' ?>; background: <?= ($currentFile == 'index.php') ? 'linear-gradient(90deg, rgba(13,148,136,0.3) 0%, rgba(13,148,136,0.1) 100%)' : 'transparent' ?>; border-left: 3px solid <?= ($currentFile == 'index.php') ? '#2dd4bf' : 'transparent' ?>; text-decoration: none; transition: all 0.2s ease;">
        <i class="fa-solid fa-gauge-high" style="font-size: 0.95rem; width: 20px; text-align: center; color: <?= ($currentFile == 'index.php') ? '#2dd4bf' : '#64748b' ?>;"></i>
        <span style="flex: 1;">Dashboard</span>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>petugas/validasi_tiket.php" class="admin-nav-link <?= ($currentFile == 'validasi_tiket.php') ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.85rem; border-radius: 0.7rem; font-size: 0.85rem; font-weight: 700; color: <?= ($currentFile == 'validasi_tiket.php') ? '#ffffff' : '#94a3b8' ?>; background: <?= ($currentFile == 'validasi_tiket.php') ? 'linear-gradient(90deg, rgba(13,148,136,0.3) 0%, rgba(13,148,136,0.1) 100%)' : 'transparent' ?>; border-left: 3px solid <?= ($currentFile == 'validasi_tiket.php') ? '#2dd4bf' : 'transparent' ?>; text-decoration: none; transition: all 0.2s ease;">
        <i class="fa-solid fa-qrcode" style="font-size: 0.95rem; width: 20px; text-align: center; color: <?= ($currentFile == 'validasi_tiket.php') ? '#2dd4bf' : '#64748b' ?>;"></i>
        <span style="flex: 1;">Scan E-Tiket Gate</span>
        <?php if ($todayCntValidasi > 0): ?>
          <span style="background: #059669; color: #ffffff; font-size: 0.68rem; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 9999px;">
            <?= $todayCntValidasi ?> Scan
          </span>
        <?php endif; ?>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>petugas/input_kunjungan.php" class="admin-nav-link <?= ($currentFile == 'input_kunjungan.php') ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.85rem; border-radius: 0.7rem; font-size: 0.85rem; font-weight: 700; color: <?= ($currentFile == 'input_kunjungan.php') ? '#ffffff' : '#94a3b8' ?>; background: <?= ($currentFile == 'input_kunjungan.php') ? 'linear-gradient(90deg, rgba(13,148,136,0.3) 0%, rgba(13,148,136,0.1) 100%)' : 'transparent' ?>; border-left: 3px solid <?= ($currentFile == 'input_kunjungan.php') ? '#2dd4bf' : 'transparent' ?>; text-decoration: none; transition: all 0.2s ease;">
        <i class="fa-solid fa-pen-to-square" style="font-size: 0.95rem; width: 20px; text-align: center; color: <?= ($currentFile == 'input_kunjungan.php') ? '#2dd4bf' : '#64748b' ?>;"></i>
        <span style="flex: 1;">Input Presensi Loket</span>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>petugas/riwayat_input.php" class="admin-nav-link <?= ($currentFile == 'riwayat_input.php') ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.85rem; border-radius: 0.7rem; font-size: 0.85rem; font-weight: 700; color: <?= ($currentFile == 'riwayat_input.php') ? '#ffffff' : '#94a3b8' ?>; background: <?= ($currentFile == 'riwayat_input.php') ? 'linear-gradient(90deg, rgba(13,148,136,0.3) 0%, rgba(13,148,136,0.1) 100%)' : 'transparent' ?>; border-left: 3px solid <?= ($currentFile == 'riwayat_input.php') ? '#2dd4bf' : 'transparent' ?>; text-decoration: none; transition: all 0.2s ease;">
        <i class="fa-solid fa-clipboard-list" style="font-size: 0.95rem; width: 20px; text-align: center; color: <?= ($currentFile == 'riwayat_input.php') ? '#2dd4bf' : '#64748b' ?>;"></i>
        <span style="flex: 1;">Riwayat Input Presensi</span>
        <?php if ($todayCntPengunjung > 0): ?>
          <span style="background: #0284c7; color: #ffffff; font-size: 0.68rem; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 9999px;">
            <?= number_format($todayCntPengunjung) ?> Org
          </span>
        <?php endif; ?>
      </a>
    </li>

  </ul>

  <!-- Sidebar Footer -->
  <div class="admin-sidebar-footer">
    <a href="<?= BASE_URL ?>logout.php" class="admin-footer-logout-single" onclick="return confirm('Apakah Anda yakin ingin menyelesaikan shift dan keluar?')" title="Selesai Shift & Keluar">
      <span class="admin-footer-logout-icon">🚪</span>
      <span>Selesai Shift & Keluar</span>
    </a>
  </div>

</aside>
