<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$userName = $_SESSION['user_nama'] ?? 'Wisatawan';
$userEmail = $_SESSION['user_email'] ?? 'user@wisata.com';
$initial = strtoupper(substr($userName, 0, 1));
$userId = $_SESSION['user_id'] ?? 0;

// Hitung Counter Tiket Aktif & Menunggu Pembayaran
$cntTiketAktif = 0;
$cntPendingBayar = 0;
if (isset($pdo) && $pdo && $userId > 0) {
    try {
        $stmtA = $pdo->prepare("SELECT COUNT(*) FROM pemesanan WHERE user_id = ? AND status_bayar = 'lunas' AND status_kunjungan = 'belum_digunakan' AND tanggal_kunjungan >= CURDATE()");
        $stmtA->execute([$userId]);
        $cntTiketAktif = (int)$stmtA->fetchColumn();

        $stmtP = $pdo->prepare("SELECT COUNT(*) FROM pemesanan WHERE user_id = ? AND status_bayar = 'pending'");
        $stmtP->execute([$userId]);
        $cntPendingBayar = (int)$stmtP->fetchColumn();
    } catch (Exception $e) {}
}
?>
<aside class="admin-sidebar" style="background: linear-gradient(180deg, #0b1329 0%, #0f172a 100%);">
  
  <!-- Brand Header -->
  <div class="admin-sidebar-brand" style="padding: 1.25rem 1.2rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
    <div class="admin-brand-icon" style="background: linear-gradient(135deg, #0284c7 0%, #38bdf8 100%); box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4); width: 42px; height: 42px; border-radius: 0.75rem;">
      <i class="fa-solid fa-compass text-white" style="font-size: 1.25rem;"></i>
    </div>
    <div style="overflow: hidden; flex: 1;">
      <span style="display: block; font-weight: 800; font-size: 0.95rem; color: #ffffff; letter-spacing: -0.01em; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; font-family: 'Outfit', sans-serif;">
        <?= htmlspecialchars($settings['nama_sistem'] ?? 'Pesona Nusantara') ?>
      </span>
      <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.72rem; color: #38bdf8; font-weight: 700;">
        <i class="fa-solid fa-crown text-amber-400" style="font-size: 0.68rem;"></i> Member Wisatawan
      </span>
    </div>
  </div>

  <!-- Profile Card -->
  <?php 
    $sessFoto = $_SESSION['user_foto'] ?? 'default_avatar.png';
    $hasSessFoto = (!empty($sessFoto) && $sessFoto !== 'default_avatar.png' && file_exists(__DIR__ . '/../../assets/uploads/users/' . $sessFoto));
  ?>
  <div class="admin-profile-pill" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); margin: 1rem 1rem 0.5rem 1rem; border-radius: 0.85rem; padding: 0.75rem;">
    <?php if ($hasSessFoto): ?>
      <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($sessFoto) ?>" alt="Avatar" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #38bdf8; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
    <?php else: ?>
      <div class="admin-avatar" style="background: linear-gradient(135deg, #0284c7, #6366f1); width: 38px; height: 38px; font-weight: 800; font-size: 1rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
        <?= $initial ?>
      </div>
    <?php endif; ?>
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
      Aktivitas Wisata
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>pengunjung/index.php" class="admin-nav-link <?= ($currentFile == 'index.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-house-chimney-window"></i>
        <span style="flex: 1;">Dashboard Saya</span>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>pengunjung/tiket_saya.php" class="admin-nav-link <?= ($currentFile == 'tiket_saya.php' || $currentFile == 'detail_tiket.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-ticket"></i>
        <span style="flex: 1;">E-Ticket & Tiket Saya</span>
        <?php if ($cntTiketAktif > 0): ?>
          <span style="background: #0284c7; color: #ffffff; font-size: 0.68rem; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 9999px;">
            <?= $cntTiketAktif ?> Aktif
          </span>
        <?php endif; ?>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" class="admin-nav-link <?= ($currentFile == 'pesan_tiket.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-cart-plus"></i>
        <span style="flex: 1;">Pesan Tiket Wisata</span>
        <span style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.65rem; font-weight: 800; padding: 0.1rem 0.4rem; border-radius: 4px;">
          PROMO
        </span>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>pengunjung/riwayat_transaksi.php" class="admin-nav-link <?= ($currentFile == 'riwayat_transaksi.php' || $currentFile == 'pembayaran.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-receipt"></i>
        <span style="flex: 1;">Riwayat Transaksi</span>
        <?php if ($cntPendingBayar > 0): ?>
          <span style="background: #f59e0b; color: #000000; font-size: 0.68rem; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 9999px;">
            <?= $cntPendingBayar ?> Bayar
          </span>
        <?php endif; ?>
      </a>
    </li>

    <li style="padding: 1.25rem 0.5rem 0.25rem 0.5rem; font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
      Akun & Pengalaman
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>pengunjung/ulasan_saya.php" class="admin-nav-link <?= ($currentFile == 'ulasan_saya.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-star-half-stroke"></i>
        <span style="flex: 1;">Ulasan & Review Saya</span>
      </a>
    </li>

    <li class="admin-nav-item">
      <a href="<?= BASE_URL ?>pengunjung/profil.php" class="admin-nav-link <?= ($currentFile == 'profil.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-user-gear"></i>
        <span style="flex: 1;">Pengaturan Profil</span>
      </a>
    </li>

  </ul>

  <!-- Sidebar Footer -->
  <div class="admin-sidebar-footer" style="padding: 1rem; border-top: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; gap: 0.5rem;">
    <a href="<?= BASE_URL ?>destinasi.php" target="_blank" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.06); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.1); justify-content: center; width: 100%; border-radius: 0.65rem; font-weight: 700;">
      <i class="fa-solid fa-compass"></i> Jelajahi Destinasi
    </a>
    <a href="<?= BASE_URL ?>logout.php" class="btn btn-danger btn-sm" style="justify-content: center; width: 100%; border-radius: 0.65rem; font-weight: 700;" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
      <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar Akun
    </a>
  </div>

</aside>
