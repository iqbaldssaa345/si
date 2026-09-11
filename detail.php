<?php
require_once __DIR__ . '/config/database.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: " . BASE_URL . "destinasi.php");
    exit;
}

// Ambil Detail Destinasi
$stmt = $pdo->prepare("SELECT d.*, k.nama_kategori 
                       FROM destinasi d 
                       JOIN kategori_wisata k ON d.kategori_id = k.id 
                       WHERE d.id = ? LIMIT 1");
$stmt->execute([$id]);
$wisata = $stmt->fetch();

if (!$wisata) {
    header("Location: " . BASE_URL . "destinasi.php");
    exit;
}

// Ambil Galeri Destinasi
$galeriStmt = $pdo->prepare("SELECT * FROM destinasi_foto WHERE destinasi_id = ? ORDER BY id ASC");
$galeriStmt->execute([$id]);
$galeri = $galeriStmt->fetchAll();

// Ambil Ulasan
$ulasanStmt = $pdo->prepare("SELECT u.*, us.nama as nama_user, us.foto as user_foto 
                             FROM ulasan u 
                             JOIN users us ON u.user_id = us.id 
                             WHERE u.destinasi_id = ? 
                             ORDER BY u.created_at DESC");
$ulasanStmt->execute([$id]);
$ulasanList = $ulasanStmt->fetchAll();

// Handle submit ulasan baru (hanya bagi yang sudah login)
$msg = '';
$msgType = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['submit_ulasan'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "login.php?redirect=" . urlencode("detail.php?id=" . $id));
        exit;
    }

    $userId = $_SESSION['user_id'];
    $rating = (int)($_POST['rating'] ?? 5);
    $komentar = trim($_POST['komentar'] ?? '');

    if ($rating < 1 || $rating > 5) $rating = 5;

    if (empty($komentar)) {
        $msg = "Silakan tulis pengalaman/ulasan Anda.";
        $msgType = "danger";
    } else {
        // Cek apakah sudah pernah mengulas
        $cek = $pdo->prepare("SELECT id FROM ulasan WHERE destinasi_id = ? AND user_id = ?");
        $cek->execute([$id, $userId]);
        if ($cek->fetch()) {
            $msg = "Anda sudah pernah memberikan ulasan untuk destinasi ini.";
            $msgType = "warning";
        } else {
            $insertUlasan = $pdo->prepare("INSERT INTO ulasan (destinasi_id, user_id, rating, komentar, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($insertUlasan->execute([$id, $userId, $rating, $komentar])) {
                // Update average rating di destinasi
                $stat = $pdo->prepare("SELECT AVG(rating) as avg_r FROM ulasan WHERE destinasi_id = ?");
                $stat->execute([$id]);
                $avgR = (float)$stat->fetchColumn();

                $updateDest = $pdo->prepare("UPDATE destinasi SET rating = ? WHERE id = ?");
                $updateDest->execute([$avgR, $id]);

                $msg = "Terima kasih! Ulasan Anda berhasil ditambahkan.";
                $msgType = "success";

                // Refresh destinasi data
                $stmt->execute([$id]);
                $wisata = $stmt->fetch();
                $ulasanStmt->execute([$id]);
                $ulasanList = $ulasanStmt->fetchAll();
            } else {
                $msg = "Gagal menyimpan ulasan. Coba lagi.";
                $msgType = "danger";
            }
        }
    }
}

$pageTitle = $wisata['nama_destinasi'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$fotoUtama = !empty($wisata['foto_utama']) ? (strpos($wisata['foto_utama'], 'http') === 0 ? $wisata['foto_utama'] : BASE_URL . 'assets/uploads/destinasi/' . $wisata['foto_utama']) : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80';
$fasilitasArr = !empty($wisata['fasilitas']) ? explode(',', $wisata['fasilitas']) : ['Area Parkir Luas', 'Musholla', 'Toilet Bersih', 'Spot Foto Instagramable', 'Kantin & Kuliner'];
?>

<div class="container" style="padding-top: 2rem; padding-bottom: 4rem;">
  
  <!-- Breadcrumb -->
  <div class="breadcrumb" style="margin-bottom: 1.5rem; font-size: 0.875rem; color: var(--slate-400);">
    <a href="<?= BASE_URL ?>index.php" class="text-primary">Beranda</a> &gt; 
    <a href="<?= BASE_URL ?>destinasi.php?kategori=<?= $wisata['kategori_id'] ?>" class="text-primary"><?= htmlspecialchars($wisata['nama_kategori']) ?></a> &gt; 
    <span><?= htmlspecialchars($wisata['nama_destinasi']) ?></span>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 1.5rem;">
      <i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <!-- Destination Hero Banner & Quick Info -->
  <div class="card shadow-md overflow-hidden" style="border-radius: var(--radius-xl); margin-bottom: 2.5rem;">
    <div style="position: relative; height: 420px; width: 100%; background: #1e293b;">
      <img src="<?= htmlspecialchars($fotoUtama) ?>" alt="<?= htmlspecialchars($wisata['nama_destinasi']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
      <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(15, 23, 42, 0.9) 0%, rgba(15, 23, 42, 0.2) 60%, transparent 100%);"></div>
      
      <div style="position: absolute; bottom: 2rem; left: 2rem; right: 2rem; color: white;">
        <span class="badge badge-primary" style="margin-bottom: 0.75rem;"><?= htmlspecialchars($wisata['nama_kategori']) ?></span>
        <h1 style="font-size: 2.5rem; font-weight: 800; color: #fff; margin-bottom: 0.5rem; text-shadow: 0 2px 10px rgba(0,0,0,0.5);">
          <?= htmlspecialchars($wisata['nama_destinasi']) ?>
        </h1>
        <div class="flex items-center gap-6" style="flex-wrap: wrap; font-size: 0.95rem; color: #e2e8f0;">
          <span><i class="fa-solid fa-location-dot text-primary"></i> <?= htmlspecialchars($wisata['lokasi']) ?></span>
          <span><i class="fa-solid fa-clock text-primary"></i> <?= htmlspecialchars($wisata['jam_buka']) ?> - <?= htmlspecialchars($wisata['jam_tutup']) ?></span>
          <span><i class="fa-solid fa-calendar-days text-primary"></i> <?= htmlspecialchars($wisata['hari_operasional']) ?></span>
          <span class="flex items-center gap-1 text-amber">
            <i class="fa-solid fa-star"></i> <strong><?= number_format($wisata['rating'], 1) ?></strong> (<?= count($ulasanList) ?> Ulasan)
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Content Layout: Left Column (Details) & Right Column (Booking Card) -->
  <div class="grid grid-cols-3 gap-8 items-start">
    
    <!-- Left Column (Span 2) -->
    <div style="grid-column: span 2;">
      
      <!-- Deskripsi Wisata -->
      <div class="card p-6 shadow-sm" style="border-radius: var(--radius-lg); margin-bottom: 2rem;">
        <h3 class="text-xl font-bold text-dark" style="margin-bottom: 1rem; border-bottom: 2px solid var(--slate-100); padding-bottom: 0.75rem;">
          <i class="fa-solid fa-circle-info text-primary"></i> Tentang Destinasi
        </h3>
        <div class="text-slate-600" style="line-height: 1.8; font-size: 1.05rem; white-space: pre-line;">
          <?= nl2br(htmlspecialchars($wisata['deskripsi'])) ?>
        </div>
      </div>

      <!-- Fasilitas -->
      <div class="card p-6 shadow-sm" style="border-radius: var(--radius-lg); margin-bottom: 2rem;">
        <h3 class="text-xl font-bold text-dark" style="margin-bottom: 1.25rem; border-bottom: 2px solid var(--slate-100); padding-bottom: 0.75rem;">
          <i class="fa-solid fa-list-check text-primary"></i> Fasilitas & Layanan
        </h3>
        <div class="grid grid-cols-2 gap-3">
          <?php foreach ($fasilitasArr as $fas): ?>
            <div class="flex items-center gap-3 p-3 bg-light rounded" style="border: 1px solid var(--slate-200);">
              <i class="fa-solid fa-circle-check text-primary"></i>
              <span class="text-dark font-medium"><?= htmlspecialchars(trim($fas)) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Galeri Foto (Jika ada) -->
      <?php if (!empty($galeri)): ?>
      <div class="card p-6 shadow-sm" style="border-radius: var(--radius-lg); margin-bottom: 2rem;">
        <h3 class="text-xl font-bold text-dark" style="margin-bottom: 1.25rem; border-bottom: 2px solid var(--slate-100); padding-bottom: 0.75rem;">
          <i class="fa-solid fa-images text-primary"></i> Galeri Foto Suasana
        </h3>
        <div class="grid grid-cols-3 gap-3">
          <?php foreach ($galeri as $g): 
            $fUrl = (strpos($g['foto_url'], 'http') === 0) ? $g['foto_url'] : BASE_URL . 'assets/uploads/destinasi/' . $g['foto_url'];
          ?>
            <div style="height: 140px; border-radius: var(--radius-md); overflow: hidden;">
              <img src="<?= htmlspecialchars($fUrl) ?>" alt="<?= htmlspecialchars($g['caption'] ?? $wisata['nama_destinasi']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Peta Lokasi (Embed) -->
      <?php if (!empty($wisata['maps_embed'])): ?>
      <div class="card p-6 shadow-sm" style="border-radius: var(--radius-lg); margin-bottom: 2rem;">
        <h3 class="text-xl font-bold text-dark" style="margin-bottom: 1.25rem; border-bottom: 2px solid var(--slate-100); padding-bottom: 0.75rem;">
          <i class="fa-solid fa-map-location-dot text-primary"></i> Lokasi & Peta Petunjuk
        </h3>
        <div class="rounded overflow-hidden" style="border: 1px solid var(--slate-200);">
          <?= $wisata['maps_embed'] ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Ulasan & Rating Section -->
      <div class="card p-6 shadow-sm" style="border-radius: var(--radius-lg);">
        <div class="flex items-center justify-between" style="margin-bottom: 1.5rem; border-bottom: 2px solid var(--slate-100); padding-bottom: 0.75rem;">
          <h3 class="text-xl font-bold text-dark" style="margin: 0;">
            <i class="fa-solid fa-comments text-primary"></i> Ulasan Wisatawan (<?= count($ulasanList) ?>)
          </h3>
          <div class="flex items-center gap-2 text-amber font-bold">
            <i class="fa-solid fa-star"></i> <?= number_format($wisata['rating'], 1) ?> / 5.0
          </div>
        </div>

        <!-- Form Tambah Ulasan -->
        <?php if (isset($_SESSION['user_id'])): ?>
          <form action="<?= BASE_URL ?>detail.php?id=<?= $wisata['id'] ?>" method="POST" class="bg-light p-4 rounded shadow-xs" style="margin-bottom: 2rem; border: 1px dashed var(--primary);">
            <h4 class="font-bold text-dark mb-2">Tulis Pengalaman Anda</h4>
            
            <div class="form-group mb-3">
              <label class="form-label text-xs uppercase font-bold text-muted">Beri Rating Bintang</label>
              <select name="rating" class="form-control" style="max-width: 200px;">
                <option value="5">⭐⭐⭐⭐⭐ 5 - Sangat Puas</option>
                <option value="4">⭐⭐⭐⭐ 4 - Bagus Sekali</option>
                <option value="3">⭐⭐⭐ 3 - Cukup Baik</option>
                <option value="2">⭐⭐ 2 - Kurang Memuaskan</option>
                <option value="1">⭐ 1 - Sangat Kecewa</option>
              </select>
            </div>

            <div class="form-group mb-3">
              <label class="form-label text-xs uppercase font-bold text-muted">Ulasan Singkat</label>
              <textarea name="komentar" rows="3" class="form-control" placeholder="Ceritakan bagaimana keindahan, kebersihan, atau pelayanan di tempat ini..." required></textarea>
            </div>

            <button type="submit" name="submit_ulasan" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-paper-plane"></i> Kirim Ulasan
            </button>
          </form>
        <?php else: ?>
          <div class="alert alert-info" style="margin-bottom: 2rem;">
            <i class="fa-solid fa-lock"></i> <a href="<?= BASE_URL ?>login.php?redirect=<?= urlencode('detail.php?id=' . $wisata['id']) ?>" class="font-bold underline text-primary">Masuk ke Akun Anda</a> untuk dapat menulis ulasan & memberikan rating.
          </div>
        <?php endif; ?>

        <!-- List Ulasan -->
        <?php if (empty($ulasanList)): ?>
          <p class="text-muted text-center py-4">Belum ada ulasan untuk wisata ini. Jadilah yang pertama memberikan ulasan!</p>
        <?php else: ?>
          <div class="flex flex-col gap-4">
            <?php foreach ($ulasanList as $u): ?>
              <div class="p-4 bg-light rounded" style="border: 1px solid var(--slate-200);">
                <div class="flex items-center justify-between" style="margin-bottom: 0.5rem;">
                  <div class="flex items-center gap-3">
                    <div class="avatar" style="width: 38px; height: 38px; font-size: 0.9rem; background: var(--secondary);">
                      <?= strtoupper(substr($u['nama_user'], 0, 1)) ?>
                    </div>
                    <div>
                      <strong class="text-dark"><?= htmlspecialchars($u['nama_user']) ?></strong>
                      <div class="text-xs text-muted"><?= formatTanggalIndo($u['created_at']) ?></div>
                    </div>
                  </div>
                  <div class="text-amber text-sm">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="fa-<?= $i <= $u['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                    <?php endfor; ?>
                  </div>
                </div>
                <p class="text-slate-600 text-sm" style="margin: 0; line-height: 1.5;">
                  <?= htmlspecialchars($u['komentar']) ?>
                </p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right Column: Booking Card (Sticky) -->
    <div style="position: sticky; top: 90px;">
      <div class="card p-6 shadow-lg" style="border-radius: var(--radius-xl); border: 2px solid var(--primary-light);">
        <div class="text-xs font-bold uppercase text-primary tracking-wide mb-1">Tiket Masuk Resmi</div>
        <div class="flex items-baseline gap-2 mb-4">
          <span style="font-size: 2rem; font-weight: 800; color: var(--dark-900);"><?= formatRupiah($wisata['harga_tiket']) ?></span>
          <span class="text-xs text-muted">/ orang</span>
        </div>

        <?php if ($wisata['diskon_rombongan'] > 0): ?>
          <div class="p-3 bg-light rounded mb-4" style="border-left: 4px solid var(--accent);">
            <div class="flex items-center gap-2 text-xs font-bold text-dark">
              <i class="fa-solid fa-tag text-accent"></i> Diskon Rombongan <?= $wisata['diskon_rombongan'] ?>%
            </div>
            <p class="text-xs text-muted mt-1" style="margin: 0;">
              Minimal pembelian <strong><?= $wisata['min_rombongan'] ?> tiket</strong>. Dapatkan harga super hemat!
            </p>
          </div>
        <?php endif; ?>

        <div class="flex flex-col gap-3 mb-6 text-sm text-slate-600">
          <div class="flex items-center justify-between">
            <span><i class="fa-solid fa-clock text-primary"></i> Jam Operasional</span>
            <strong><?= htmlspecialchars($wisata['jam_buka']) ?> - <?= htmlspecialchars($wisata['jam_tutup']) ?></strong>
          </div>
          <div class="flex items-center justify-between">
            <span><i class="fa-solid fa-calendar-check text-primary"></i> Hari Operasional</span>
            <strong><?= htmlspecialchars($wisata['hari_operasional']) ?></strong>
          </div>
          <div class="flex items-center justify-between">
            <span><i class="fa-solid fa-qrcode text-primary"></i> Tipe Tiket</span>
            <strong>E-Ticket QR Code</strong>
          </div>
        </div>

        <a href="<?= BASE_URL ?>booking.php?id=<?= $wisata['id'] ?>" class="btn btn-primary btn-block btn-lg mb-3 shadow-glow">
          <i class="fa-solid fa-ticket"></i> Pesan Tiket Sekarang
        </a>

        <div class="text-center">
          <span class="text-xs text-muted"><i class="fa-solid fa-shield-halved text-success"></i> Transaksi Aman & Terverifikasi</span>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
