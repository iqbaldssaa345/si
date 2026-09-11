<?php
$currentUser = $_SESSION['user_nama'] ?? null;
$currentRole = $_SESSION['user_role'] ?? null;
$flash = getFlash();
?>
<header class="site-navbar">
  <div class="container navbar-container">
    <a href="<?= BASE_URL ?>index.php" class="nav-brand">
      <div class="nav-brand-icon">
        <i class="fa-solid fa-compass"></i>
      </div>
      <span><?= htmlspecialchars($settings['nama_sistem']) ?></span>
    </a>

    <ul class="nav-links" id="navLinks">
      <li><a href="<?= BASE_URL ?>index.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>">Beranda</a></li>
      <li><a href="<?= BASE_URL ?>destinasi.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'destinasi.php' || basename($_SERVER['PHP_SELF']) == 'detail.php') ? 'active' : '' ?>">Destinasi</a></li>
      <?php if ($currentUser && $currentRole === 'pengunjung'): ?>
        <li><a href="<?= BASE_URL ?>pengunjung/tiket_saya.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'tiket_saya.php' || basename($_SERVER['PHP_SELF']) == 'riwayat.php') ? 'active' : '' ?>">E-Ticket Saya</a></li>
        <li><a href="<?= BASE_URL ?>pengunjung/index.php" class="nav-link"><i class="fa-solid fa-crown text-amber-400"></i> Member Area</a></li>
      <?php endif; ?>
      <li><a href="<?= BASE_URL ?>index.php#tentang" class="nav-link">Tentang Kami</a></li>
      <li><a href="<?= BASE_URL ?>index.php#kontak" class="nav-link">Kontak</a></li>
    </ul>

    <div class="nav-actions">
      <?php if ($currentUser): ?>
        <?php if ($currentRole === 'admin'): ?>
          <a href="<?= BASE_URL ?>admin/index.php" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-gauge-high"></i> Panel Admin
          </a>
        <?php elseif ($currentRole === 'petugas'): ?>
          <a href="<?= BASE_URL ?>petugas/index.php" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-id-badge"></i> Panel Petugas
          </a>
        <?php else: ?>
          <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>pengunjung/index.php" class="user-menu-btn" title="Buka Dashboard Wisatawan">
              <?php 
                $sessFoto = $_SESSION['user_foto'] ?? 'default_avatar.png';
                $hasSessFoto = (!empty($sessFoto) && $sessFoto !== 'default_avatar.png' && file_exists(__DIR__ . '/../assets/uploads/users/' . $sessFoto));
              ?>
              <?php if ($hasSessFoto): ?>
                <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($sessFoto) ?>" alt="Avatar" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #38bdf8;">
              <?php else: ?>
                <div class="avatar" style="background: linear-gradient(135deg, #0284c7, #6366f1);"><?= strtoupper(substr($currentUser, 0, 1)) ?></div>
              <?php endif; ?>
              <span><?= htmlspecialchars($currentUser) ?></span>
            </a>
            <a href="<?= BASE_URL ?>logout.php" class="btn btn-sm btn-secondary" title="Keluar">
              <i class="fa-solid fa-right-from-bracket"></i>
            </a>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <a href="<?= BASE_URL ?>login.php" class="btn btn-sm btn-secondary">Masuk</a>
        <a href="<?= BASE_URL ?>register.php" class="btn btn-sm btn-primary">Daftar</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php if ($flash): ?>
<div class="container" style="margin-top: 1.5rem;">
  <div class="alert alert-<?= $flash['type'] ?> alert-dismissible">
    <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : ($flash['type'] === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-info') ?>"></i>
    <div><?= htmlspecialchars($flash['message']) ?></div>
  </div>
</div>
<?php endif; ?>
