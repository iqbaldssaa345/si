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
    <div class="admin-brand-icon">
      <i class="fa-solid fa-crown text-amber"></i>
    </div>
    <div style="overflow: hidden; flex: 1;">
      <span style="display: block; font-weight: 800; font-size: 0.95rem; color: #ffffff; letter-spacing: -0.01em; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
        <?= htmlspecialchars($settings['nama_sistem'] ?? 'Pesona Nusantara') ?>
      </span>
      <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.72rem; color: #38bdf8; font-weight: 600;">
        <span style="width: 6px; height: 6px; border-radius: 50%; background: #22c55e; display: inline-block; box-shadow: 0 0 8px #22c55e;"></span>
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
      <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($sessFoto) ?>" alt="Avatar" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #2dd4bf; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
    <?php else: ?>
      <div class="admin-avatar">
        <?= $initial ?>
      </div>
    <?php endif; ?>
    <div style="overflow: hidden; flex: 1;">
      <strong style="display: block; font-size: 0.875rem; color: #f8fafc; font-weight: 700; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
        <?= htmlspecialchars($userName) ?>
      </strong>
      <span style="font-size: 0.72rem; color: #94a3b8; display: block; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
        <?= htmlspecialchars($userEmail) ?>
      </span>
    </div>
  </div>

  <!-- Navigation Menu -->
  <ul class="admin-nav-list">
    
    <li style="padding: 0.6rem 0.5rem 0.25rem 0.5rem; font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
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
          <span style="font-size: 0.7rem; font-weight: 700; background: rgba(255,255,255,0.1); color: #94a3b8; padding: 0.15rem 0.5rem; border-radius: 9999px;">
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

    <li style="padding: 0.85rem 0.5rem 0.25rem 0.5rem; font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
      Transaksi & Layanan
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>admin/pemesanan.php" class="admin-nav-link <?= ($currentFile == 'pemesanan.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-ticket"></i>
        <span style="flex: 1;">Pemesanan & Tiket</span>
        <?php if ($cntPendingPesanan > 0): ?>
          <span style="font-size: 0.68rem; font-weight: 800; background: #ef4444; color: #ffffff; padding: 0.15rem 0.45rem; border-radius: 9999px; animation: pulse 2s infinite;">
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

    <li style="padding: 0.85rem 0.5rem 0.25rem 0.5rem; font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
      Konfigurasi Sistem
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>admin/users.php" class="admin-nav-link <?= ($currentFile == 'users.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-user-gear"></i>
        <span style="flex: 1;">Manajemen User</span>
        <?php if ($cntUsers > 0): ?>
          <span style="font-size: 0.7rem; font-weight: 700; background: rgba(56, 189, 248, 0.15); color: #38bdf8; padding: 0.15rem 0.5rem; border-radius: 9999px;">
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

  <!-- Sidebar Footer -->
  <div class="admin-sidebar-footer">
    <a href="<?= BASE_URL ?>index.php" target="_blank" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.06); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.1); justify-content: center; width: 100%; border-radius: 0.65rem;">
      <i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat Portal Wisata
    </a>
    <a href="<?= BASE_URL ?>logout.php" class="btn btn-danger btn-sm" style="justify-content: center; width: 100%; border-radius: 0.65rem;" onclick="return confirm('Apakah Anda yakin ingin keluar dari panel admin?')">
      <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
    </a>
  </div>

</aside>
