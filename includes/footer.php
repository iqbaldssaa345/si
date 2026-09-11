<?php
if (!isset($settings)) {
    $settings = getSettings($pdo ?? null);
}
?>
<footer class="site-footer">
  <div class="container">
    <div class="grid grid-cols-4 gap-8">
      <!-- Col 1: Brand & Tagline -->
      <div style="grid-column: span 1.5;">
        <div class="footer-brand flex items-center gap-2">
          <i class="fa-solid fa-compass text-primary"></i>
          <span><?= htmlspecialchars($settings['nama_sistem']) ?></span>
        </div>
        <p class="text-sm text-muted" style="margin-bottom: 1.5rem; max-width: 320px;">
          <?= htmlspecialchars($settings['tagline']) ?>. Temukan destinasi terbaik, nikmati promo tiket rombongan, dan rasakan kemudahan liburan seru bersama kami.
        </p>
        <div class="flex gap-3">
          <?php if (!empty($settings['sosmed_instagram'])): ?>
            <a href="https://instagram.com/<?= htmlspecialchars($settings['sosmed_instagram']) ?>" target="_blank" class="btn btn-sm btn-secondary" style="border-radius: 50%; width: 38px; height: 38px; padding: 0;">
              <i class="fa-brands fa-instagram"></i>
            </a>
          <?php endif; ?>
          <?php if (!empty($settings['sosmed_facebook'])): ?>
            <a href="https://facebook.com/<?= htmlspecialchars($settings['sosmed_facebook']) ?>" target="_blank" class="btn btn-sm btn-secondary" style="border-radius: 50%; width: 38px; height: 38px; padding: 0;">
              <i class="fa-brands fa-facebook-f"></i>
            </a>
          <?php endif; ?>
          <?php if (!empty($settings['sosmed_youtube'])): ?>
            <a href="https://youtube.com/<?= htmlspecialchars($settings['sosmed_youtube']) ?>" target="_blank" class="btn btn-sm btn-secondary" style="border-radius: 50%; width: 38px; height: 38px; padding: 0;">
              <i class="fa-brands fa-youtube"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Col 2: Quick Links -->
      <div>
        <h4 class="text-white text-md font-bold" style="margin-bottom: 1.25rem;">Eksplorasi</h4>
        <ul class="footer-links">
          <li><a href="<?= BASE_URL ?>index.php"><i class="fa-solid fa-angle-right text-xs"></i> Beranda</a></li>
          <li><a href="<?= BASE_URL ?>destinasi.php"><i class="fa-solid fa-angle-right text-xs"></i> Semua Destinasi</a></li>
          <li><a href="<?= BASE_URL ?>destinasi.php?kategori=1"><i class="fa-solid fa-angle-right text-xs"></i> Wisata Alam</a></li>
          <li><a href="<?= BASE_URL ?>destinasi.php?kategori=2"><i class="fa-solid fa-angle-right text-xs"></i> Wisata Bahari</a></li>
        </ul>
      </div>

      <!-- Col 3: Bantuan & Layanan -->
      <div>
        <h4 class="text-white text-md font-bold" style="margin-bottom: 1.25rem;">Layanan</h4>
        <ul class="footer-links">
          <li><a href="<?= BASE_URL ?>login.php"><i class="fa-solid fa-angle-right text-xs"></i> Portal Pengguna</a></li>
          <li><a href="<?= BASE_URL ?>petugas/index.php"><i class="fa-solid fa-angle-right text-xs"></i> Portal Petugas</a></li>
          <li><a href="<?= BASE_URL ?>admin/index.php"><i class="fa-solid fa-angle-right text-xs"></i> Portal Admin</a></li>
          <li><a href="<?= BASE_URL ?>index.php#faq"><i class="fa-solid fa-angle-right text-xs"></i> Syarat & Ketentuan</a></li>
        </ul>
      </div>

      <!-- Col 4: Kontak Info -->
      <div id="kontak">
        <h4 class="text-white text-md font-bold" style="margin-bottom: 1.25rem;">Hubungi Kami</h4>
        <ul class="footer-links">
          <li class="flex items-center gap-2 text-sm text-muted">
            <i class="fa-solid fa-location-dot text-primary"></i>
            <span><?= htmlspecialchars($settings['alamat']) ?></span>
          </li>
          <li class="flex items-center gap-2 text-sm text-muted">
            <i class="fa-solid fa-phone text-primary"></i>
            <span><?= htmlspecialchars($settings['kontak_telp']) ?></span>
          </li>
          <li class="flex items-center gap-2 text-sm text-muted">
            <i class="fa-solid fa-envelope text-primary"></i>
            <span><?= htmlspecialchars($settings['kontak_email']) ?></span>
          </li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom flex justify-between items-center" style="flex-wrap: wrap; gap: 1rem;">
      <p>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars($settings['nama_sistem']) ?></strong>. All rights reserved.</p>
      <p class="text-muted text-xs">Dikembangkan untuk Pengembangan Sistem Informasi Wisata Modern & Terintegrasi</p>
    </div>
  </div>
</footer>

<!-- Scripts -->
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
