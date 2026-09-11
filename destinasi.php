<?php
require_once __DIR__ . '/config/database.php';

$q = trim($_GET['q'] ?? '');
$kategoriId = (int)($_GET['kategori'] ?? 0);
$sort = $_GET['sort'] ?? 'populer';

// Fetch all categories for filter chips/dropdown
$kategoriList = $pdo->query("SELECT * FROM kategori_wisata ORDER BY nama_kategori ASC")->fetchAll();

// Build Query
$sql = "SELECT d.*, k.nama_kategori 
        FROM destinasi d 
        JOIN kategori_wisata k ON d.kategori_id = k.id 
        WHERE d.status = 'buka'";
$params = [];

if (!empty($q)) {
    $sql .= " AND (d.nama_destinasi LIKE ? OR d.lokasi LIKE ? OR d.deskripsi LIKE ?)";
    $term = "%{$q}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($kategoriId > 0) {
    $sql .= " AND d.kategori_id = ?";
    $params[] = $kategoriId;
}

// Order by
switch ($sort) {
    case 'termurah':
        $sql .= " ORDER BY d.harga_tiket ASC";
        break;
    case 'termahal':
        $sql .= " ORDER BY d.harga_tiket DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY d.rating DESC";
        break;
    case 'terbaru':
        $sql .= " ORDER BY d.id DESC";
        break;
    case 'populer':
    default:
        $sql .= " ORDER BY d.rating DESC, d.id DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$destinasiList = $stmt->fetchAll();

$pageTitle = "Katalog Destinasi Wisata";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Header Banner -->
<div class="destinasi-hero-banner" style="background: linear-gradient(135deg, #0d9488 0%, #0369a1 100%); padding: 3.5rem 0; color: white; margin-bottom: 2.5rem; text-align: center;">
  <div class="container">
    <span class="badge badge-glass" style="background: rgba(255,255,255,0.2); color: #fff; margin-bottom: 0.75rem; display: inline-block;">Eksplorasi Keindahan Nusantara</span>
    <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem; color: #fff;">Destinasi Wisata Pilihan</h1>
    <p style="color: rgba(255,255,255,0.85); max-width: 600px; margin: 0 auto;">Temukan beragam pilihan objek wisata terbaik, informasi harga tiket, dan promo diskon rombongan.</p>
  </div>
</div>

<div class="container" style="margin-bottom: 4rem;">
  <!-- Filter & Search Bar -->
  <div class="card p-4 shadow-sm" style="margin-bottom: 2rem; border-radius: var(--radius-lg);">
    <form action="<?= BASE_URL ?>destinasi.php" method="GET" class="grid grid-cols-4 gap-4 items-center">
      <!-- Keyword search -->
      <div style="grid-column: span 1.5;">
        <label class="form-label text-xs font-bold text-muted uppercase">Pencarian Wisata</label>
        <div class="input-with-icon">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Nama wisata, lokasi...">
        </div>
      </div>

      <!-- Kategori -->
      <div>
        <label class="form-label text-xs font-bold text-muted uppercase">Kategori</label>
        <div class="input-with-icon">
          <i class="fa-solid fa-layer-group"></i>
          <select name="kategori" class="form-control">
            <option value="">Semua Kategori</option>
            <?php foreach ($kategoriList as $kat): ?>
              <option value="<?= $kat['id'] ?>" <?= $kategoriId == $kat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($kat['nama_kategori']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Urutkan -->
      <div>
        <label class="form-label text-xs font-bold text-muted uppercase">Urutkan Berdasarkan</label>
        <div class="input-with-icon">
          <i class="fa-solid fa-arrow-down-short-wide"></i>
          <select name="sort" class="form-control">
            <option value="populer" <?= $sort == 'populer' ? 'selected' : '' ?>>Paling Populer</option>
            <option value="rating" <?= $sort == 'rating' ? 'selected' : '' ?>>Rating Tertinggi</option>
            <option value="termurah" <?= $sort == 'termurah' ? 'selected' : '' ?>>Harga Tiket: Rendah ke Tinggi</option>
            <option value="termahal" <?= $sort == 'termahal' ? 'selected' : '' ?>>Harga Tiket: Tinggi ke Rendah</option>
            <option value="terbaru" <?= $sort == 'terbaru' ? 'selected' : '' ?>>Terbaru Ditambahkan</option>
          </select>
        </div>
      </div>

      <!-- Submit & Reset Button -->
      <div class="flex items-center gap-2" style="align-self: flex-end;">
        <button type="submit" class="btn btn-primary btn-block">
          <i class="fa-solid fa-filter"></i> Terapkan
        </button>
        <?php if (!empty($q) || $kategoriId > 0 || $sort !== 'populer'): ?>
          <a href="<?= BASE_URL ?>destinasi.php" class="btn btn-secondary" title="Reset Filter">
            <i class="fa-solid fa-rotate-left"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Header Hasil -->
  <div class="flex items-center justify-between" style="margin-bottom: 1.5rem;">
    <p class="text-muted" style="margin: 0;">
      Menampilkan <strong><?= count($destinasiList) ?></strong> destinasi wisata
      <?php if (!empty($q)): ?> untuk pencarian "<em><?= htmlspecialchars($q) ?></em>"<?php endif; ?>
    </p>
  </div>

  <!-- Grid Destinasi -->
  <?php if (empty($destinasiList)): ?>
    <div class="card p-8 text-center" style="border-radius: var(--radius-lg);">
      <i class="fa-solid fa-mountain-sun fa-4x text-muted" style="margin-bottom: 1rem; opacity: 0.5;"></i>
      <h3 class="text-xl font-bold text-dark">Destinasi Tidak Ditemukan</h3>
      <p class="text-muted" style="max-width: 450px; margin: 0.5rem auto 1.5rem;">
        Maaf, tidak ada destinasi yang sesuai dengan kriteria filter pencarian Anda. Coba kata kunci lain atau reset filter.
      </p>
      <div>
        <a href="<?= BASE_URL ?>destinasi.php" class="btn btn-primary">
          <i class="fa-solid fa-arrows-rotate"></i> Tampilkan Semua Wisata
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="destinasi-grid">
      <?php foreach ($destinasiList as $wisata): 
        $foto = !empty($wisata['foto_utama']) ? (strpos($wisata['foto_utama'], 'http') === 0 ? $wisata['foto_utama'] : BASE_URL . 'assets/uploads/destinasi/' . $wisata['foto_utama']) : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80';
      ?>
        <div class="destinasi-card">
          <div class="card-img-wrapper">
            <img src="<?= htmlspecialchars($foto) ?>" alt="<?= htmlspecialchars($wisata['nama_destinasi']) ?>" class="card-img" loading="lazy">
            <span class="card-badge"><?= htmlspecialchars($wisata['nama_kategori']) ?></span>
            <?php if ($wisata['diskon_rombongan'] > 0): ?>
              <span class="card-discount-badge">Hemat <?= $wisata['diskon_rombongan'] ?>% Rombongan</span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div class="flex items-center justify-between" style="margin-bottom: 0.5rem;">
              <div class="flex items-center gap-1 text-sm text-amber">
                <i class="fa-solid fa-star"></i>
                <span class="font-bold text-dark"><?= number_format($wisata['rating'], 1) ?></span>
              </div>
              <div class="text-xs text-muted">
                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars(substr($wisata['lokasi'], 0, 28)) ?>
              </div>
            </div>

            <h3 class="card-title">
              <a href="<?= BASE_URL ?>detail.php?id=<?= $wisata['id'] ?>"><?= htmlspecialchars($wisata['nama_destinasi']) ?></a>
            </h3>
            
            <p class="card-desc">
              <?= htmlspecialchars(substr($wisata['deskripsi'], 0, 95)) . '...' ?>
            </p>

            <div class="card-footer">
              <div class="card-price">
                <span class="price-label">Tiket Masuk:</span>
                <span class="price-value"><?= formatRupiah($wisata['harga_tiket']) ?></span>
              </div>
              <div class="flex gap-2">
                <a href="<?= BASE_URL ?>detail.php?id=<?= $wisata['id'] ?>" class="btn btn-sm btn-secondary" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </a>
                <a href="<?= BASE_URL ?>booking.php?id=<?= $wisata['id'] ?>" class="btn btn-sm btn-primary">
                  <i class="fa-solid fa-ticket"></i> Pesan
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
