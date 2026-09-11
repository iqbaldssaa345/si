<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('admin');

$msg = '';
$msgType = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nama_sistem = trim($_POST['nama_sistem'] ?? '');
    $tagline = trim($_POST['tagline'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $kontak_telp = trim($_POST['kontak_telp'] ?? '');
    $kontak_email = trim($_POST['kontak_email'] ?? '');
    $sosmed_instagram = trim($_POST['sosmed_instagram'] ?? '');
    $sosmed_facebook = trim($_POST['sosmed_facebook'] ?? '');
    $sosmed_youtube = trim($_POST['sosmed_youtube'] ?? '');

    $cek = $pdo->query("SELECT id FROM pengaturan_web LIMIT 1")->fetch();

    if ($cek) {
        $upd = $pdo->prepare("UPDATE pengaturan_web SET 
            nama_sistem = ?, tagline = ?, deskripsi = ?, alamat = ?, kontak_telp = ?, kontak_email = ?, 
            sosmed_instagram = ?, sosmed_facebook = ?, sosmed_youtube = ?, updated_at = NOW() 
            WHERE id = ?");
        $upd->execute([
            $nama_sistem, $tagline, $deskripsi, $alamat, $kontak_telp, $kontak_email,
            $sosmed_instagram, $sosmed_facebook, $sosmed_youtube, $cek['id']
        ]);
    } else {
        $ins = $pdo->prepare("INSERT INTO pengaturan_web 
            (nama_sistem, tagline, deskripsi, alamat, kontak_telp, kontak_email, sosmed_instagram, sosmed_facebook, sosmed_youtube) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([
            $nama_sistem, $tagline, $deskripsi, $alamat, $kontak_telp, $kontak_email,
            $sosmed_instagram, $sosmed_facebook, $sosmed_youtube
        ]);
    }

    $msg = "Pengaturan website berhasil diperbarui!";
    $msgType = "success";
    $settings = getSettings($pdo); // refresh
}

$pageTitle = "Pengaturan Website";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="admin-main">
  
  <div class="admin-topbar-luxury">
    <div>
      <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0;">Pengaturan Sistem Informasi</h1>
      <p style="font-size: 0.85rem; color: #64748b; margin: 0.2rem 0 0 0;">Sesuaikan identitas website, kontak layanan, dan integrasi sosial media resmi</p>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mb-6" style="border-radius: 0.75rem;">
      <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <div class="card p-8 shadow-sm bg-white max-w-3xl" style="border-radius: 1.25rem; border: 1px solid #e2e8f0;">
    <form action="<?= BASE_URL ?>admin/pengaturan.php" method="POST">
      
      <h3 class="font-bold text-dark text-lg mb-4 pb-2 border-b">
        <i class="fa-solid fa-building text-primary"></i> Identitas & Branding
      </h3>

      <div class="grid grid-cols-2 gap-4 mb-4">
        <div class="form-group">
          <label class="form-label font-bold text-xs uppercase text-muted">Nama Website / Sistem <span class="text-danger">*</span></label>
          <input type="text" name="nama_sistem" class="form-control" value="<?= htmlspecialchars($settings['nama_sistem'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label font-bold text-xs uppercase text-muted">Tagline Slogan</label>
          <input type="text" name="tagline" class="form-control" value="<?= htmlspecialchars($settings['tagline'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group mb-6">
        <label class="form-label font-bold text-xs uppercase text-muted">Deskripsi Singkat Sistem</label>
        <textarea name="deskripsi" rows="3" class="form-control"><?= htmlspecialchars($settings['deskripsi'] ?? '') ?></textarea>
      </div>

      <h3 class="font-bold text-dark text-lg mb-4 pb-2 border-b">
        <i class="fa-solid fa-headset text-primary"></i> Informasi Kontak & Kantor
      </h3>

      <div class="form-group mb-4">
        <label class="form-label font-bold text-xs uppercase text-muted">Alamat Kantor</label>
        <input type="text" name="alamat" class="form-control" value="<?= htmlspecialchars($settings['alamat'] ?? '') ?>">
      </div>

      <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="form-group">
          <label class="form-label font-bold text-xs uppercase text-muted">Nomor Telepon / WhatsApp</label>
          <input type="text" name="kontak_telp" class="form-control" value="<?= htmlspecialchars($settings['kontak_telp'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label font-bold text-xs uppercase text-muted">Email Layanan</label>
          <input type="email" name="kontak_email" class="form-control" value="<?= htmlspecialchars($settings['kontak_email'] ?? '') ?>">
        </div>
      </div>

      <h3 class="font-bold text-dark text-lg mb-4 pb-2 border-b">
        <i class="fa-solid fa-share-nodes text-primary"></i> Media Sosial
      </h3>

      <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="form-group">
          <label class="form-label font-bold text-xs uppercase text-muted">Instagram Username</label>
          <div class="input-with-icon">
            <i class="fa-brands fa-instagram"></i>
            <input type="text" name="sosmed_instagram" class="form-control" value="<?= htmlspecialchars($settings['sosmed_instagram'] ?? '') ?>" placeholder="pesonanusantara">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label font-bold text-xs uppercase text-muted">Facebook Fanpage</label>
          <div class="input-with-icon">
            <i class="fa-brands fa-facebook"></i>
            <input type="text" name="sosmed_facebook" class="form-control" value="<?= htmlspecialchars($settings['sosmed_facebook'] ?? '') ?>" placeholder="pesonanusantara">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label font-bold text-xs uppercase text-muted">YouTube Channel</label>
          <div class="input-with-icon">
            <i class="fa-brands fa-youtube"></i>
            <input type="text" name="sosmed_youtube" class="form-control" value="<?= htmlspecialchars($settings['sosmed_youtube'] ?? '') ?>" placeholder="pesonanusantara">
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg shadow-glow">
        <i class="fa-solid fa-save"></i> Simpan Perubahan Pengaturan
      </button>

    </form>
  </div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
