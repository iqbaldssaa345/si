<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$userName = $_SESSION['user_nama'] ?? 'Administrator';
$userEmail = $_SESSION['user_email'] ?? 'admin@wisata.com';
$initial = strtoupper(substr($userName, 0, 1));

// Hitung counter dinamis jika koneksi database aktif
$cntPendingPesanan = 0;
$cntDestinasi = 0;
$cntUsers = 0;
if (isset($pdo) && $pdo) {
    try {
        $cntPendingPesanan = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status_bayar = 'pending'")->fetchColumn();
        $cntDestinasi = (int)$pdo->query("SELECT COUNT(*) FROM destinasi")->fetchColumn();
        $cntUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    } catch (Exception $e) {}
}
?>
<aside class="admin-sidebar">
  
  <!-- Brand Header -->
  <div class="admin-sidebar-brand">
    <div class="admin-brand-icon-luxury admin-icon-admin">
      <span class="luxury-emote-brand">👑</span>
    </div>
    <div style="overflow: hidden; flex: 1; min-width: 0;">
      <span style="display: block; font-weight: 800; font-size: 0.88rem; color: #ffffff; letter-spacing: -0.01em; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; line-height: 1.2;">
        <?= htmlspecialchars($settings['nama_sistem'] ?? 'Pesona Nusantara') ?>
      </span>
      <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.68rem; color: #38bdf8; font-weight: 600; line-height: 1.2;">
        <span style="width: 5px; height: 5px; border-radius: 50%; background: #22c55e; display: inline-block; box-shadow: 0 0 6px #22c55e;"></span>
        Super Admin Control
      </span>
    </div>
  </div>

  <!-- Profile Card -->
  <?php 
    $sessFoto = $_SESSION['user_foto'] ?? 'default_avatar.png';
    $hasSessFoto = (!empty($sessFoto) && $sessFoto !== 'default_avatar.png' && file_exists(__DIR__ . '/../../assets/uploads/users/' . $sessFoto));
  ?>
  <div class="admin-profile-pill">
    <?php if ($hasSessFoto): ?>
      <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($sessFoto) ?>" alt="Avatar" style="width: 28px; height: 28px; min-width: 28px; border-radius: 50%; object-fit: cover; border: 2px solid #2dd4bf;">
    <?php else: ?>
      <div class="admin-avatar">
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
  <ul class="admin-nav-list">
    
    <li class="admin-nav-section-title">
      Menu Utama
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>admin/index.php" class="admin-nav-link <?= ($currentFile == 'index.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-pie"></i>
        <span style="flex: 1;">Dashboard</span>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>admin/destinasi.php" class="admin-nav-link <?= ($currentFile == 'destinasi.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-mountain-sun"></i>
        <span style="flex: 1;">Destinasi Wisata</span>
        <?php if ($cntDestinasi > 0): ?>
          <span class="admin-nav-badge">
            <?= $cntDestinasi ?>
          </span>
        <?php endif; ?>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>admin/kategori.php" class="admin-nav-link <?= ($currentFile == 'kategori.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-layer-group"></i>
        <span style="flex: 1;">Kategori Wisata</span>
      </a>
    </li>

    <li class="admin-nav-section-title">
      Transaksi & Layanan
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>admin/pemesanan.php" class="admin-nav-link <?= ($currentFile == 'pemesanan.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-ticket"></i>
        <span style="flex: 1;">Pemesanan & Tiket</span>
        <?php if ($cntPendingPesanan > 0): ?>
          <span class="admin-nav-badge admin-nav-badge-danger">
            <?= $cntPendingPesanan ?> Baru
          </span>
        <?php endif; ?>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>admin/kunjungan.php" class="admin-nav-link <?= ($currentFile == 'kunjungan.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-users"></i>
        <span style="flex: 1;">Rekap Kunjungan</span>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>admin/laporan.php" class="admin-nav-link <?= ($currentFile == 'laporan.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <span style="flex: 1;">Laporan & Keuangan</span>
      </a>
    </li>

    <li class="admin-nav-section-title">
      Konfigurasi Sistem
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>admin/users.php" class="admin-nav-link <?= ($currentFile == 'users.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-user-gear"></i>
        <span style="flex: 1;">Manajemen User</span>
        <?php if ($cntUsers > 0): ?>
          <span class="admin-nav-badge admin-nav-badge-info">
            <?= $cntUsers ?>
          </span>
        <?php endif; ?>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>admin/pengaturan.php" class="admin-nav-link <?= ($currentFile == 'pengaturan.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-sliders"></i>
        <span style="flex: 1;">Pengaturan Web</span>
      </a>
    </li>

  </ul>

  <!-- Sidebar Footer - Single Logout Docked to Bottom -->
  <div class="admin-sidebar-footer">
    <a href="<?= BASE_URL ?>logout.php" class="admin-footer-logout-single" onclick="return confirm('Apakah Anda yakin ingin keluar dari panel admin?')" title="Keluar dari Panel Admin">
      <span class="admin-footer-logout-icon">🚪</span>
      <span>Keluar Panel Admin</span>
    </a>
  </div>

</aside>
