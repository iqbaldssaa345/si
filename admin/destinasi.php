<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('admin');

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$msg = '';
$msgType = '';

// Ambil Kategori untuk Form Dropdown
$kategoriList = $pdo->query("SELECT * FROM kategori_wisata ORDER BY nama_kategori ASC")->fetchAll();

// Hapus Destinasi
if ($action === 'delete' && $id > 0) {
    $stmtCek = $pdo->prepare("SELECT foto_utama FROM destinasi WHERE id = ?");
    $stmtCek->execute([$id]);
    $dest = $stmtCek->fetch();

    if ($dest && !empty($dest['foto_utama']) && strpos($dest['foto_utama'], 'http') !== 0) {
        $path = __DIR__ . '/../assets/uploads/destinasi/' . $dest['foto_utama'];
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    $del = $pdo->prepare("DELETE FROM destinasi WHERE id = ?");
    if ($del->execute([$id])) {
        setFlash('success', 'Destinasi wisata berhasil dihapus.');
    } else {
        setFlash('danger', 'Gagal menghapus destinasi wisata.');
    }
    header("Location: " . BASE_URL . "admin/destinasi.php");
    exit;
}

// Tambah atau Edit Destinasi
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($action === 'add' || $action === 'edit')) {
    $nama_destinasi = trim($_POST['nama_destinasi'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if (empty($slug)) {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $nama_destinasi));
    }
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $maps_embed = trim($_POST['maps_embed'] ?? '');
    $harga_tiket = (float)($_POST['harga_tiket'] ?? 0);
    $diskon_rombongan = (int)($_POST['diskon_rombongan'] ?? 15);
    $min_rombongan = (int)($_POST['min_rombongan'] ?? 10);
    $jam_buka = $_POST['jam_buka'] ?? '08:00:00';
    $jam_tutup = $_POST['jam_tutup'] ?? '17:00:00';
    $hari_operasional = trim($_POST['hari_operasional'] ?? 'Setiap Hari (Senin - Minggu)');
    $fasilitas = trim($_POST['fasilitas'] ?? '');
    $status = $_POST['status'] ?? 'buka';
    $rating = (float)($_POST['rating'] ?? 4.80);

    if (empty($nama_destinasi) || $kategori_id <= 0 || empty($lokasi)) {
        $msg = "Nama wisata, kategori, dan lokasi wajib diisi.";
        $msgType = "danger";
    } else {
        // Handle Upload Gambar Utama
        $gambarName = $_POST['foto_utama_lama'] ?? '';
        if (isset($_FILES['foto_utama']) && $_FILES['foto_utama']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['foto_utama']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['foto_utama']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowed) && $_FILES['foto_utama']['size'] <= 8 * 1024 * 1024) {
                $gambarName = 'dest_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $uploadDir = __DIR__ . '/../assets/uploads/destinasi/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Hapus foto lama jika bukan URL http
                if (!empty($_POST['foto_utama_lama']) && strpos($_POST['foto_utama_lama'], 'http') !== 0) {
                    $oldPath = $uploadDir . $_POST['foto_utama_lama'];
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                move_uploaded_file($tmp, $uploadDir . $gambarName);
            }
        }

        if (empty($gambarName)) {
            $gambarName = 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800';
        }

        if ($action === 'add') {
            $ins = $pdo->prepare("INSERT INTO destinasi 
                (kategori_id, nama_destinasi, slug, deskripsi, lokasi, maps_embed, harga_tiket, diskon_rombongan, min_rombongan, jam_buka, jam_tutup, hari_operasional, fasilitas, status, rating, foto_utama, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $success = $ins->execute([
                $kategori_id, $nama_destinasi, $slug, $deskripsi, $lokasi, $maps_embed, $harga_tiket, 
                $diskon_rombongan, $min_rombongan, $jam_buka, $jam_tutup, 
                $hari_operasional, $fasilitas, $status, $rating, $gambarName
            ]);
            if ($success) {
                setFlash('success', 'Destinasi wisata baru berhasil ditambahkan dengan galeri foto!');
                header("Location: " . BASE_URL . "admin/destinasi.php");
                exit;
            } else {
                $msg = "Gagal menambahkan destinasi.";
                $msgType = "danger";
            }
        } else {
            // Edit
            $upd = $pdo->prepare("UPDATE destinasi SET 
                kategori_id = ?, nama_destinasi = ?, slug = ?, deskripsi = ?, lokasi = ?, maps_embed = ?, harga_tiket = ?, 
                diskon_rombongan = ?, min_rombongan = ?, jam_buka = ?, jam_tutup = ?, 
                hari_operasional = ?, fasilitas = ?, status = ?, rating = ?, foto_utama = ? 
                WHERE id = ?");
            $success = $upd->execute([
                $kategori_id, $nama_destinasi, $slug, $deskripsi, $lokasi, $maps_embed, $harga_tiket, 
                $diskon_rombongan, $min_rombongan, $jam_buka, $jam_tutup, 
                $hari_operasional, $fasilitas, $status, $rating, $gambarName, $id
            ]);
            if ($success) {
                setFlash('success', 'Perubahan destinasi wisata & foto berhasil disimpan.');
                header("Location: " . BASE_URL . "admin/destinasi.php");
                exit;
            } else {
                $msg = "Gagal mengubah destinasi.";
                $msgType = "danger";
            }
        }
    }
}

// Data Destinasi untuk Edit
$destinasiData = [];
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM destinasi WHERE id = ?");
    $stmt->execute([$id]);
    $destinasiData = $stmt->fetch();
    if (!$destinasiData) {
        setFlash('danger', 'Destinasi tidak ditemukan.');
        header("Location: " . BASE_URL . "admin/destinasi.php");
        exit;
    }
}

// List Destinasi
$destinasiList = $pdo->query("SELECT d.*, k.nama_kategori 
                             FROM destinasi d 
                             LEFT JOIN kategori_wisata k ON d.kategori_id = k.id 
                             ORDER BY d.id DESC")->fetchAll();

$flash = getFlash();
$pageTitle = "Kelola Destinasi & Foto Wisata";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="admin-main">
  
  <div class="admin-topbar-luxury">
    <div>
      <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0;">Destinasi Objek Wisata</h1>
      <p style="font-size: 0.85rem; color: #64748b; margin: 0.2rem 0 0 0;">Manajemen data lokasi, jam operasional, galeri foto, dan tarif tiket</p>
    </div>
    <?php if ($action === 'list'): ?>
      <a href="<?= BASE_URL ?>admin/destinasi.php?action=add" class="btn btn-primary btn-sm" style="box-shadow: 0 4px 12px rgba(13, 148, 136, 0.35); font-weight: 800;">
        <i class="fa-solid fa-plus"></i> Tambah Destinasi Baru
      </a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>admin/destinasi.php" class="btn btn-secondary btn-sm" style="font-weight: 700;">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar
      </a>
    <?php endif; ?>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-6" style="border-radius: 0.75rem;">
      <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flash['message']) ?>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mb-6" style="border-radius: 0.75rem;">
      <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <?php if ($action === 'list'): ?>
    <!-- LIST VIEW -->
    <div class="card-table-luxury">
      <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
        <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0;">
          Daftar Objek Wisata Terdaftar (<?= count($destinasiList) ?>)
        </h3>
        <span class="badge-luxury badge-luxury-success" style="font-size: 0.75rem;">
          <i class="fa-solid fa-mountain-sun"></i> Data Aktif
        </span>
      </div>
      <div class="overflow-x-auto">
        <table class="table-luxury">
          <thead>
            <tr>
              <th style="width: 10%;">Foto Wisata</th>
              <th style="width: 30%;">Nama Destinasi & Lokasi</th>
              <th style="width: 15%;">Kategori</th>
              <th style="width: 14%;">Harga Tiket</th>
              <th style="width: 13%;">Diskon Grup</th>
              <th style="width: 8%;">Rating</th>
              <th style="width: 10%; text-align: right;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($destinasiList as $d): 
              $foto = !empty($d['foto_utama']) ? (strpos($d['foto_utama'], 'http') === 0 ? $d['foto_utama'] : BASE_URL . 'assets/uploads/destinasi/' . $d['foto_utama']) : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=200&q=80';
            ?>
              <tr>
                <td>
                  <img src="<?= htmlspecialchars($foto) ?>" alt="<?= htmlspecialchars($d['nama_destinasi']) ?>" style="width: 68px; height: 50px; object-fit: cover; border-radius: 10px; border: 1.5px solid #cbd5e1; box-shadow: 0 4px 10px rgba(0,0,0,0.08);">
                </td>
                <td>
                  <strong class="text-dark text-sm block" style="font-weight: 800; font-size: 0.95rem;"><?= htmlspecialchars($d['nama_destinasi']) ?></strong>
                  <span class="text-xs text-muted"><i class="fa-solid fa-location-dot text-primary"></i> <?= htmlspecialchars($d['lokasi']) ?></span>
                </td>
                <td><span class="badge badge-light" style="font-weight: 700;"><?= htmlspecialchars($d['nama_kategori']) ?></span></td>
                <td><strong class="text-primary" style="font-family: 'Outfit', sans-serif; font-size: 1rem;"><?= formatRupiah($d['harga_tiket']) ?></strong></td>
                <td>
                  <?php if ($d['diskon_rombongan'] > 0): ?>
                    <span class="badge badge-accent" style="font-weight: 800;"><?= $d['diskon_rombongan'] ?>% (Min <?= $d['min_rombongan'] ?>)</span>
                  <?php else: ?>
                    <span class="text-xs text-muted">Reguler</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="flex items-center gap-1 text-xs text-amber font-bold">
                    <i class="fa-solid fa-star"></i> <?= number_format($d['rating'], 1) ?>
                  </div>
                </td>
                <td style="text-align: right;">
                  <div class="flex gap-2 justify-end">
                    <a href="<?= BASE_URL ?>admin/destinasi.php?action=edit&id=<?= $d['id'] ?>" class="btn btn-secondary btn-sm" title="Edit Data & Foto" style="border-radius: 0.5rem;">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <a href="<?= BASE_URL ?>admin/destinasi.php?action=delete&id=<?= $d['id'] ?>" class="btn btn-danger btn-sm" title="Hapus" style="border-radius: 0.5rem;" onclick="return confirm('Apakah Anda yakin ingin menghapus destinasi ini?')">
                      <i class="fa-solid fa-trash"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php else: ?>
    <!-- FORM ADD / EDIT VIEW -->
    <div class="card p-8 shadow-md bg-white max-w-4xl" style="border-radius: 1.25rem; border: 1px solid #e2e8f0;">
      
      <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid #e2e8f0;">
        <div style="width: 42px; height: 42px; border-radius: 12px; background: #ccfbf1; color: #0d9488; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
          <i class="fa-solid <?= $action === 'add' ? 'fa-plus' : 'fa-pen-to-square' ?>"></i>
        </div>
        <div>
          <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0;">
            <?= $action === 'add' ? 'Tambah Destinasi Wisata Baru' : 'Edit Destinasi: ' . htmlspecialchars($destinasiData['nama_destinasi']) ?>
          </h3>
          <p style="font-size: 0.8rem; color: #64748b; margin: 0;">Lengkapi data detail objek wisata, tarif, dan unggah foto utama beresolusi tinggi</p>
        </div>
      </div>

      <form action="<?= BASE_URL ?>admin/destinasi.php?action=<?= $action ?><?= $id > 0 ? '&id=' . $id : '' ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="foto_utama_lama" value="<?= htmlspecialchars($destinasiData['foto_utama'] ?? '') ?>">

        <!-- Foto Utama Preview & Upload Dropzone -->
        <div style="margin-bottom: 1.5rem; padding: 1.25rem; background: #f8fafc; border-radius: 1rem; border: 1px solid #e2e8f0;">
          <label class="form-label font-bold text-xs uppercase text-slate-600 mb-2 block">
            <i class="fa-solid fa-camera text-primary"></i> Foto Utama Destinasi Wisata
          </label>
          
          <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
            <?php 
              $currentFoto = !empty($destinasiData['foto_utama']) ? (strpos($destinasiData['foto_utama'], 'http') === 0 ? $destinasiData['foto_utama'] : BASE_URL . 'assets/uploads/destinasi/' . $destinasiData['foto_utama']) : '';
            ?>
            <img src="<?= $currentFoto ?>" alt="Preview" id="destinasiPhotoPreview" style="display: <?= !empty($currentFoto) ? 'block' : 'none' ?>; width: 140px; height: 90px; object-fit: cover; border-radius: 12px; border: 2px solid #0d9488; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">

            <div style="flex: 1;">
              <input type="file" name="foto_utama" accept="image/*" class="form-control" style="font-size: 0.85rem; padding: 0.45rem 0.75rem;" onchange="previewDestinasiPhoto(this)">
              <small style="color: #94a3b8; font-size: 0.75rem; margin-top: 0.35rem; display: block;">Format JPG, PNG, WEBP (Maksimal 8MB). Gambar ini akan menjadi sampul utama kartu wisata.</small>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Nama Tempat Wisata <span class="text-danger">*</span></label>
            <input type="text" name="nama_destinasi" class="form-control" value="<?= htmlspecialchars($destinasiData['nama_destinasi'] ?? '') ?>" placeholder="Cth: Candi Prambanan" required style="font-weight: 600;">
          </div>

          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Kategori Wisata <span class="text-danger">*</span></label>
            <select name="kategori_id" class="form-control" required style="font-weight: 600;">
              <option value="">-- Pilih Kategori --</option>
              <?php foreach ($kategoriList as $k): ?>
                <option value="<?= $k['id'] ?>" <?= (($destinasiData['kategori_id'] ?? 0) == $k['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($k['nama_kategori']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Lokasi Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="lokasi" class="form-control" value="<?= htmlspecialchars($destinasiData['lokasi'] ?? '') ?>" placeholder="Cth: Sleman, D.I. Yogyakarta" required style="font-weight: 600;">
          </div>

          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Slug URL</label>
            <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($destinasiData['slug'] ?? '') ?>" placeholder="candi-prambanan">
          </div>
        </div>

        <div class="form-group mb-4">
          <label class="form-label font-bold text-xs uppercase text-muted">Deskripsi Lengkap Wisata</label>
          <textarea name="deskripsi" class="form-control" rows="4" placeholder="Jelaskan daya tarik, keindahan, dan keunikan destinasi ini..."><?= htmlspecialchars($destinasiData['deskripsi'] ?? '') ?></textarea>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-4">
          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Harga Tiket Satuan (Rp) <span class="text-danger">*</span></label>
            <input type="number" name="harga_tiket" class="form-control font-bold" value="<?= $destinasiData['harga_tiket'] ?? 25000 ?>" min="0" required>
          </div>

          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Diskon Rombongan (%)</label>
            <input type="number" name="diskon_rombongan" class="form-control" value="<?= $destinasiData['diskon_rombongan'] ?? 15 ?>" min="0" max="100">
          </div>

          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Min. Tiket Rombongan</label>
            <input type="number" name="min_rombongan" class="form-control" value="<?= $destinasiData['min_rombongan'] ?? 10 ?>" min="1">
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-4">
          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Jam Buka</label>
            <input type="time" name="jam_buka" class="form-control" value="<?= $destinasiData['jam_buka'] ?? '08:00:00' ?>">
          </div>

          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Jam Tutup</label>
            <input type="time" name="jam_tutup" class="form-control" value="<?= $destinasiData['jam_tutup'] ?? '17:00:00' ?>">
          </div>

          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Hari Operasional</label>
            <input type="text" name="hari_operasional" class="form-control" value="<?= htmlspecialchars($destinasiData['hari_operasional'] ?? 'Setiap Hari (Senin - Minggu)') ?>">
          </div>
        </div>

        <div class="form-group mb-4">
          <label class="form-label font-bold text-xs uppercase text-muted">Fasilitas (Pisahkan dengan koma)</label>
          <input type="text" name="fasilitas" class="form-control" value="<?= htmlspecialchars($destinasiData['fasilitas'] ?? 'Parkir Luas,Toilet,Musholla,Restoran,Spot Foto') ?>" placeholder="Parkir Luas,Toilet,Musholla,Restoran,Spot Foto">
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6">
          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Status Operasional</label>
            <select name="status" class="form-control mt-1" style="font-weight: 700;">
              <option value="buka" <?= (($destinasiData['status'] ?? 'buka') === 'buka') ? 'selected' : '' ?>>🟢 Buka (Aktif)</option>
              <option value="tutup" <?= (($destinasiData['status'] ?? '') === 'tutup') ? 'selected' : '' ?>>🔴 Tutup</option>
              <option value="pemeliharaan" <?= (($destinasiData['status'] ?? '') === 'pemeliharaan') ? 'selected' : '' ?>>🟡 Pemeliharaan (Maintenance)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label font-bold text-xs uppercase text-muted">Google Maps Embed iframe (Opsional)</label>
            <textarea name="maps_embed" class="form-control" rows="2" placeholder='<iframe src="https://..."></iframe>'><?= htmlspecialchars($destinasiData['maps_embed'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="flex gap-3 justify-end border-t pt-4">
          <a href="<?= BASE_URL ?>admin/destinasi.php" class="btn btn-secondary btn-lg" style="font-weight: 700;">
            Batal
          </a>
          <button type="submit" class="btn btn-primary btn-lg shadow-glow" style="font-weight: 800;">
            <i class="fa-solid fa-save"></i> Simpan Destinasi & Foto
          </button>
        </div>

      </form>
    </div>
  <?php endif; ?>

</main>

<script>
function previewDestinasiPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const previewImg = document.getElementById('destinasiPhotoPreview');
      if (previewImg) {
        previewImg.src = e.target.result;
        previewImg.style.display = 'block';
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
