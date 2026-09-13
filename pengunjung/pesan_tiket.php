<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('pengunjung');

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_nama'] ?? 'Wisatawan';
$userEmail = $_SESSION['user_email'] ?? '';
$userTelp = $_SESSION['user_telp'] ?? '';

$msg = '';
$msgType = '';

// Handle Booking Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_booking'])) {
    $destinasiId = (int)($_POST['destinasi_id'] ?? 0);
    $tanggalKunjungan = trim($_POST['tanggal_kunjungan'] ?? '');
    $jumlahTiket = (int)($_POST['jumlah_tiket'] ?? 1);
    $namaPemesan = trim($_POST['nama_pemesan'] ?? $userName);
    $noTelp = trim($_POST['no_telp'] ?? $userTelp);
    $catatan = trim($_POST['catatan'] ?? '');

    // Cek destinasi
    $stmtDest = $pdo->prepare("SELECT * FROM destinasi WHERE id = ? AND status = 'buka' LIMIT 1");
    $stmtDest->execute([$destinasiId]);
    $dest = $stmtDest->fetch();

    if (!$dest) {
        $msg = "Destinasi wisata yang dipilih tidak valid atau sedang tutup.";
        $msgType = "danger";
    } elseif (empty($tanggalKunjungan) || strtotime($tanggalKunjungan) < strtotime(date('Y-m-d'))) {
        $msg = "Tanggal kunjungan tidak valid. Silakan pilih tanggal hari ini atau masa mendatang.";
        $msgType = "danger";
    } elseif ($jumlahTiket < 1) {
        $msg = "Jumlah tiket minimal pemesanan adalah 1 orang.";
        $msgType = "danger";
    } else {
        // Hitung total harga & diskon rombongan
        $hargaSatuan = (float)$dest['harga_tiket'];
        $diskonRombongan = (int)$dest['diskon_rombongan'];
        $minRombongan = (int)$dest['min_rombongan'];

        $subtotal = $hargaSatuan * $jumlahTiket;
        $tipeRombongan = ($jumlahTiket >= $minRombongan && $minRombongan > 0) ? 'rombongan' : 'sendiri';
        $diskonNominal = ($tipeRombongan === 'rombongan' && $diskonRombongan > 0) ? (($subtotal * $diskonRombongan) / 100) : 0;
        $totalBayar = $subtotal - $diskonNominal;

        // Generate Unique Booking Code (BK-YYYYMMDD-XXXX)
        $prefix = 'BK-' . date('Ymd') . '-';
        $randCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
        $kodeBooking = $prefix . $randCode;

        // Cek keunikan kode booking
        $stmtCek = $pdo->prepare("SELECT id FROM pemesanan WHERE kode_booking = ?");
        $stmtCek->execute([$kodeBooking]);
        if ($stmtCek->fetch()) {
            $kodeBooking = $prefix . rand(1000, 9999);
        }

        $qrCode = 'QR-' . $kodeBooking;

        $ins = $pdo->prepare("INSERT INTO pemesanan 
            (user_id, destinasi_id, kode_booking, nama_pemesan, no_telp, tanggal_kunjungan, jumlah_tiket, tipe_rombongan, harga_satuan, diskon_didapat, total_bayar, status_bayar, qr_code, status_kunjungan, catatan, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'belum_digunakan', ?, NOW())");
        
        if ($ins->execute([$userId, $destinasiId, $kodeBooking, $namaPemesan, $noTelp, $tanggalKunjungan, $jumlahTiket, $tipeRombongan, $hargaSatuan, $diskonNominal, $totalBayar, $qrCode, $catatan])) {
            $newBookingId = $pdo->lastInsertId();
            setFlash('success', "Pesanan tiket untuk <strong>" . htmlspecialchars($dest['nama_destinasi']) . "</strong> berhasil dibuat dengan Kode: <strong>$kodeBooking</strong>. Silakan selesaikan pembayaran!");
            header("Location: " . BASE_URL . "pembayaran.php?id=" . $newBookingId);
            exit;
        } else {
            $msg = "Terjadi kesalahan saat memproses pemesanan tiket. Silakan coba kembali.";
            $msgType = "danger";
        }
    }
}

// Fetch Kategori for Filter (Alphabetical)
$kategoriList = $pdo->query("SELECT * FROM kategori_wisata ORDER BY nama_kategori ASC")->fetchAll();

// Filter Query
$katFilter = (int)($_GET['kategori'] ?? 0);
$searchFilter = trim($_GET['q'] ?? '');

$sql = "SELECT d.*, k.nama_kategori, 
        COALESCE(AVG(u.rating), d.rating, 4.8) as avg_rating,
        COUNT(u.id) as total_ulasan
        FROM destinasi d 
        LEFT JOIN kategori_wisata k ON d.kategori_id = k.id 
        LEFT JOIN ulasan u ON d.id = u.destinasi_id 
        WHERE d.status = 'buka'";
$params = [];

if ($katFilter > 0) {
    $sql .= " AND d.kategori_id = ?";
    $params[] = $katFilter;
}

if (!empty($searchFilter)) {
    $sql .= " AND (d.nama_destinasi LIKE ? OR d.lokasi LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
}

// Order by id DESC to match the exact order shown in the reference photo (Dieng, Labuan Bajo, Prambanan, Raja Ampat, etc.)
$sql .= " GROUP BY d.id ORDER BY d.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$destinasiList = $stmt->fetchAll();

$pageTitle = "Pesan & Booking Tiket Wisata";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<style>
  /* =========================================================
     CUSTOM STYLES FOR BERKELAS TICKET BOOKING CATALOG
     Matching Reference Photo & High-End UX
     ========================================================= */
  .ticket-catalog-container {
    padding: 0.25rem 0.1rem 2rem 0.1rem;
  }

  /* Category Filter Bar */
  .filter-pills-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    align-items: center;
    margin-bottom: 0.85rem;
  }

  .pill-berkelas {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 1.05rem;
    border-radius: 0.5rem;
    font-weight: 700;
    font-size: 0.82rem;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    background: #e2e8f0;
    color: #1e293b;
    border: none;
    cursor: pointer;
    line-height: 1.3;
  }

  .pill-berkelas:hover {
    background: #cbd5e1;
    color: #0f172a;
  }

  .pill-berkelas.active {
    background: #0f766e;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(15, 118, 110, 0.35);
  }

  /* Search Bar */
  .search-bar-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
  }

  .search-input-box {
    position: relative;
    width: 250px;
    max-width: 100%;
  }

  .search-input-box i {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.82rem;
    pointer-events: none;
  }

  .search-input-berkelas {
    width: 100%;
    height: 36px;
    padding: 0.35rem 0.85rem 0.35rem 2.25rem;
    border-radius: 0.5rem;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 0.82rem;
    color: #1e293b;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    font-family: inherit;
  }

  .search-input-berkelas:focus {
    border-color: #0f766e;
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
  }

  .btn-cari-berkelas {
    height: 36px;
    padding: 0 1.25rem;
    background: #e2e8f0;
    color: #1e293b;
    border: none;
    border-radius: 0.5rem;
    font-weight: 700;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .btn-cari-berkelas:hover {
    background: #cbd5e1;
    color: #0f172a;
  }

  .btn-reset-berkelas {
    height: 36px;
    padding: 0 0.85rem;
    background: transparent;
    color: #64748b;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.78rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.2s;
  }

  .btn-reset-berkelas:hover {
    background: #f1f5f9;
    color: #0f172a;
  }

  /* 3-Column Grid matching reference */
  .dest-grid-photo {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.15rem;
  }

  @media (max-width: 1024px) {
    .dest-grid-photo {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 640px) {
    .dest-grid-photo {
      grid-template-columns: 1fr;
    }
    .search-input-box {
      width: 100%;
    }
  }

  /* Card Luxury Styling */
  .card-wisata-berkelas {
    background: #ffffff;
    border-radius: 0.85rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
  }

  .card-wisata-berkelas:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px -3px rgba(0, 0, 0, 0.08), 0 4px 8px -2px rgba(0, 0, 0, 0.04);
    border-color: #cbd5e1;
  }

  /* Card Image Area */
  .card-photo-area {
    position: relative;
    height: 180px;
    background: #0f172a;
    overflow: hidden;
  }

  .card-photo-area img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.45s ease;
  }

  .card-wisata-berkelas:hover .card-photo-area img {
    transform: scale(1.04);
  }

  .card-photo-gradient {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(15, 23, 42, 0.85) 0%, rgba(15, 23, 42, 0.25) 45%, transparent 70%);
    pointer-events: none;
  }

  /* Badges Over Image */
  .badge-category-pill {
    position: absolute;
    top: 0.65rem;
    left: 0.65rem;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(4px);
    color: #1e293b;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.25rem 0.7rem;
    border-radius: 9999px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
    z-index: 2;
  }

  .badge-rating-pill {
    position: absolute;
    top: 0.65rem;
    right: 0.65rem;
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(4px);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 800;
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    z-index: 2;
  }

  .badge-rating-pill i {
    color: #f59e0b;
    font-size: 0.72rem;
  }

  .card-location-overlay {
    position: absolute;
    bottom: 0.55rem;
    left: 0.65rem;
    right: 0.65rem;
    color: #cbd5e1;
    font-size: 0.75rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    z-index: 2;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.7);
  }

  .card-location-overlay i {
    color: #94a3b8;
    font-size: 0.72rem;
    flex-shrink: 0;
  }

  /* Card Body Area */
  .card-body-berkelas {
    padding: 0.95rem 1.05rem 1rem 1.05rem;
    display: flex;
    flex-direction: column;
    flex: 1;
    justify-content: space-between;
  }

  .card-title-berkelas {
    font-family: 'Outfit', sans-serif;
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 0.45rem 0;
    line-height: 1.3;
    letter-spacing: -0.01em;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .card-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    margin-bottom: 0.75rem;
    font-size: 0.76rem;
  }

  .time-badge {
    color: #64748b;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
  }

  .time-badge i {
    color: #0d9488;
    font-size: 0.8rem;
  }

  .discount-badge-peach {
    background: #ffedd5;
    color: #ea580c;
    font-weight: 800;
    font-size: 0.7rem;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    white-space: nowrap;
    display: inline-block;
  }

  /* Bottom Price & Button */
  .card-bottom-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    padding-top: 0.7rem;
    border-top: 1px solid #f1f5f9;
    margin-top: auto;
  }

  .price-label-text {
    font-size: 0.65rem;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.15rem;
    display: block;
  }

  .price-amount-blue {
    font-family: 'Outfit', sans-serif;
    font-size: 1.15rem;
    font-weight: 900;
    color: #0284c7;
    line-height: 1.1;
  }

  .btn-pesan-teal {
    background: #0f766e;
    color: #ffffff;
    border: none;
    border-radius: 0.5rem;
    height: 36px;
    padding: 0 1.15rem;
    font-weight: 800;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(15, 118, 110, 0.28);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
  }

  .btn-pesan-teal:hover {
    background: #115e59;
    transform: translateY(-1px);
    box-shadow: 0 5px 14px rgba(15, 118, 110, 0.38);
    color: #ffffff;
  }

  .btn-pesan-teal:active {
    transform: translateY(0);
  }

  /* Luxury Modal Overrides */
  .modal-counter-btn {
    width: 34px;
    height: 34px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 0.45rem;
    font-weight: 800;
    font-size: 1rem;
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
  }

  .modal-counter-btn:hover {
    background: #e2e8f0;
    border-color: #94a3b8;
  }
</style>

<main class="admin-main">
  
  <!-- Flash & Action Messages -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-3" style="border-radius: 0.6rem; padding: 0.65rem 0.95rem; display: flex; align-items: center; gap: 0.6rem; box-shadow: 0 1px 4px rgba(0,0,0,0.03); font-size: 0.82rem;">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>" style="font-size: 1.15rem;"></i>
      <div style="line-height: 1.4;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mb-3" style="border-radius: 0.6rem; padding: 0.65rem 0.95rem; display: flex; align-items: center; gap: 0.6rem; box-shadow: 0 1px 4px rgba(0,0,0,0.03); font-size: 0.82rem;">
      <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>" style="font-size: 1.15rem;"></i>
      <div style="line-height: 1.4;"><?= $msg ?></div>
    </div>
  <?php endif; ?>

  <div class="ticket-catalog-container">
    
    <!-- 1. Category Filter Pills (Matches Reference Photo) -->
    <div class="filter-pills-wrap">
      <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" class="pill-berkelas <?= $katFilter === 0 ? 'active' : '' ?>">
        Semua Kategori
      </a>
      <?php foreach ($kategoriList as $k): ?>
        <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php?kategori=<?= $k['id'] ?><?= !empty($searchFilter) ? '&q=' . urlencode($searchFilter) : '' ?>" 
           class="pill-berkelas <?= $katFilter == $k['id'] ? 'active' : '' ?>">
          <?= htmlspecialchars($k['nama_kategori']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- 2. Search Input & Cari Button (Matches Reference Photo) -->
    <form action="<?= BASE_URL ?>pengunjung/pesan_tiket.php" method="GET" class="search-bar-wrap">
      <?php if ($katFilter > 0): ?>
        <input type="hidden" name="kategori" value="<?= $katFilter ?>">
      <?php endif; ?>
      <div class="search-input-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($searchFilter) ?>" placeholder="Cari nama wisata..." class="search-input-berkelas">
      </div>
      <button type="submit" class="btn-cari-berkelas">
        Cari
      </button>
      <?php if (!empty($searchFilter) || $katFilter > 0): ?>
        <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" class="btn-reset-berkelas" title="Reset filter & pencarian">
          <i class="fa-solid fa-rotate-left"></i> Reset
        </a>
      <?php endif; ?>
    </form>

    <!-- 3. Destination Grid Cards (Matches Reference Photo) -->
    <?php if (empty($destinasiList)): ?>
      <div class="card p-6 bg-white shadow-sm text-center" style="border-radius: 0.85rem; border: 1px dashed #cbd5e1; padding: 3.5rem 1.5rem;">
        <div style="width: 56px; height: 56px; border-radius: 50%; background: #f0fdf4; color: #0d9488; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.85rem auto; font-size: 1.6rem;">
          <i class="fa-solid fa-compass"></i>
        </div>
        <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0; font-family: 'Outfit', sans-serif;">
          Destinasi Tidak Ditemukan
        </h3>
        <p style="font-size: 0.82rem; color: #64748b; max-width: 440px; margin: 0 auto 1.25rem auto;">
          Tidak ada destinasi wisata yang cocok dengan kriteria pencarian "<strong><?= htmlspecialchars($searchFilter) ?></strong>".
        </p>
        <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" class="pill-berkelas active" style="display: inline-flex;">
          <i class="fa-solid fa-arrow-rotate-left" style="margin-right: 0.35rem;"></i> Tampilkan Semua Wisata
        </a>
      </div>
    <?php else: ?>
      <div class="dest-grid-photo">
        <?php foreach ($destinasiList as $d): 
          $harga = (float)$d['harga_tiket'];
          $diskon = (int)$d['diskon_rombongan'];
          $minRombongan = (int)$d['min_rombongan'];
          
          // Image Fallback Handling: check if remote URL, uploaded local file, or default unsplash
          $fotoRaw = trim($d['foto_utama'] ?? '');
          if (!empty($fotoRaw) && (strpos($fotoRaw, 'http://') === 0 || strpos($fotoRaw, 'https://') === 0)) {
              $fotoDisplay = $fotoRaw;
          } elseif (!empty($fotoRaw) && file_exists(__DIR__ . '/../assets/uploads/destinasi/' . $fotoRaw)) {
              $fotoDisplay = BASE_URL . 'assets/uploads/destinasi/' . $fotoRaw;
          } else {
              $fotoDisplay = 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&q=80';
          }
          
          // Format rating nicely (e.g. 4.8, 5.0)
          $ratingVal = number_format((float)$d['avg_rating'], 1);
        ?>
          <div class="card-wisata-berkelas">
            
            <!-- Cover Photo with Badges -->
            <div class="card-photo-area">
              <img src="<?= htmlspecialchars($fotoDisplay) ?>" 
                   alt="<?= htmlspecialchars($d['nama_destinasi']) ?>" 
                   loading="lazy"
                   onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&q=80'">
              <div class="card-photo-gradient"></div>

              <!-- Top Left Category Badge -->
              <span class="badge-category-pill">
                <?= htmlspecialchars($d['nama_kategori'] ?? 'Wisata') ?>
              </span>

              <!-- Top Right Rating Badge -->
              <span class="badge-rating-pill">
                <i class="fa-solid fa-star"></i> <?= $ratingVal ?>
              </span>

              <!-- Bottom Overlay: Location -->
              <div class="card-location-overlay">
                <i class="fa-solid fa-location-dot"></i>
                <span><?= htmlspecialchars($d['lokasi']) ?></span>
              </div>
            </div>

            <!-- Card Body Content -->
            <div class="card-body-berkelas">
              <div>
                <!-- Title -->
                <h3 class="card-title-berkelas" title="<?= htmlspecialchars($d['nama_destinasi']) ?>">
                  <?= htmlspecialchars($d['nama_destinasi']) ?>
                </h3>

                <!-- Meta Row: Operating Hours & Discount -->
                <div class="card-meta-row">
                  <span class="time-badge">
                    <i class="fa-regular fa-clock"></i>
                    <?= substr($d['jam_buka'], 0, 5) ?> - <?= substr($d['jam_tutup'], 0, 5) ?> WIB
                  </span>

                  <?php if ($diskon > 0): ?>
                    <span class="discount-badge-peach">
                      Diskon <?= $diskon ?>% (Min <?= $minRombongan ?>)
                    </span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Price & Pesan Button -->
              <div class="card-bottom-row">
                <div>
                  <span class="price-label-text">HARGA TIKET</span>
                  <div class="price-amount-blue">
                    Rp <?= number_format($harga, 0, ',', '.') ?>
                  </div>
                </div>

                <button type="button" 
                        onclick="openBookingModal(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['nama_destinasi'])) ?>', <?= $harga ?>, <?= $diskon ?>, <?= $minRombongan ?>, '<?= htmlspecialchars(addslashes($d['lokasi'])) ?>')" 
                        class="btn-pesan-teal">
                  <i class="fa-solid fa-cart-shopping"></i> Pesan
                </button>
              </div>

            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

</main>

<!-- Interactive Booking Modal (Instant Real-time Price & Discount Calculator) -->
<div id="modalBooking" class="luxury-modal-backdrop" style="display: none;">
  <div class="luxury-modal-box" style="max-width: 470px; padding: 1.35rem; border-radius: 1rem;">
    
    <!-- Modal Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; margin-bottom: 1rem;">
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <div style="width: 36px; height: 36px; border-radius: 0.55rem; background: #ccfbf1; color: #0f766e; display: flex; align-items: center; justify-content: center; font-size: 1.05rem;">
          <i class="fa-solid fa-ticket"></i>
        </div>
        <div>
          <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Outfit', sans-serif;">Reservasi E-Ticket</h3>
          <span id="bkModalSubtitle" style="font-size: 0.72rem; color: #64748b;">Pemesanan tiket online resmi instan</span>
        </div>
      </div>
      <button type="button" onclick="closeLuxuryModal('modalBooking')" style="background: none; border: none; font-size: 1.25rem; color: #94a3b8; cursor: pointer; padding: 0.2rem; transition: color 0.2s;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="<?= BASE_URL ?>pengunjung/pesan_tiket.php" method="POST" id="formBookingTiket">
      <input type="hidden" name="action_booking" value="1">
      <input type="hidden" name="destinasi_id" id="bkDestId" value="">

      <!-- Destination Summary Box -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.65rem; padding: 0.7rem 0.85rem; margin-bottom: 0.95rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
          <span style="font-size: 0.65rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em;">Destinasi Pilihan</span>
          <strong id="bkDestName" style="display: block; font-size: 0.92rem; color: #0f172a; font-family: 'Outfit', sans-serif;"></strong>
          <span id="bkDestLocation" style="font-size: 0.7rem; color: #64748b;"><i class="fa-solid fa-location-dot text-amber-500"></i> -</span>
        </div>
        <div style="text-align: right;">
          <span style="font-size: 0.65rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em;">Harga / Tiket</span>
          <strong id="bkPriceText" style="display: block; font-size: 0.95rem; color: #0284c7; font-weight: 900; font-family: 'Outfit', sans-serif;"></strong>
        </div>
      </div>

      <!-- Tanggal Kunjungan -->
      <div class="form-group mb-2">
        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">
          Tanggal Kunjungan <span style="color: #ef4444;">*</span>
        </label>
        <input type="date" name="tanggal_kunjungan" id="bkTanggal" class="form-control" style="font-size: 0.82rem; border-radius: 0.5rem; height: 36px; border: 1px solid #cbd5e1;" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
      </div>

      <!-- Jumlah Tiket Stepper -->
      <div class="form-group mb-2">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
          <label style="font-size: 0.75rem; font-weight: 700; color: #334155; margin: 0;">
            Jumlah Wisatawan (Tiket) <span style="color: #ef4444;">*</span>
          </label>
          <span id="bkGroupDiscountHint" style="font-size: 0.68rem; color: #ea580c; font-weight: 700; display: none;">
            <i class="fa-solid fa-tag"></i> Diskon Rombongan Aktif!
          </span>
        </div>
        <div style="display: flex; gap: 0.45rem; align-items: center;">
          <button type="button" onclick="adjustQty(-1)" class="modal-counter-btn">-</button>
          <input type="number" name="jumlah_tiket" id="bkQty" class="form-control" style="font-size: 0.9rem; font-weight: 800; text-align: center; border-radius: 0.5rem; height: 36px; border: 1px solid #cbd5e1; font-family: 'Outfit', sans-serif;" value="1" min="1" max="500" required oninput="recalcBooking()">
          <button type="button" onclick="adjustQty(1)" class="modal-counter-btn">+</button>
        </div>
      </div>

      <!-- Nama Pemesan & No WhatsApp -->
      <div class="grid grid-cols-2 gap-2 mb-2">
        <div>
          <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">Nama Pemesan</label>
          <input type="text" name="nama_pemesan" value="<?= htmlspecialchars($userName) ?>" class="form-control" style="font-size: 0.8rem; border-radius: 0.5rem; height: 34px; border: 1px solid #cbd5e1;" required>
        </div>
        <div>
          <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">No. WhatsApp</label>
          <input type="text" name="no_telp" value="<?= htmlspecialchars($userTelp) ?>" class="form-control" style="font-size: 0.8rem; border-radius: 0.5rem; height: 34px; border: 1px solid #cbd5e1;" placeholder="08123456789" required>
        </div>
      </div>

      <!-- Catatan Khusus (Opsional) -->
      <div class="form-group mb-3">
        <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">Catatan Khusus (Opsional)</label>
        <input type="text" name="catatan" placeholder="Contoh: Rombongan keluarga besar / bus 1" class="form-control" style="font-size: 0.78rem; border-radius: 0.5rem; height: 32px; border: 1px solid #cbd5e1;">
      </div>

      <!-- Live Calculation Box -->
      <div style="background: linear-gradient(135deg, #0b1329 0%, #0f172a 100%); color: #ffffff; border-radius: 0.75rem; padding: 0.85rem 1rem; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <div style="display: flex; justify-content: space-between; font-size: 0.76rem; opacity: 0.85; margin-bottom: 0.25rem;">
          <span>Subtotal (<span id="calcQty">1</span> Tiket)</span>
          <span id="calcSubtotal">Rp 0</span>
        </div>
        <div id="calcDiskonRow" style="display: none; justify-content: space-between; font-size: 0.76rem; color: #4ade80; margin-bottom: 0.25rem;">
          <span>Diskon Rombongan (<span id="calcDiskonPct">0</span>%)</span>
          <span id="calcDiskonNominal">- Rp 0</span>
        </div>
        <div style="border-top: 1px dashed rgba(255,255,255,0.2); padding-top: 0.45rem; margin-top: 0.35rem; display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 0.82rem; font-weight: 800;">Total Pembayaran</span>
          <span id="calcTotalFinal" style="font-size: 1.2rem; font-weight: 900; color: #38bdf8; font-family: 'Outfit', sans-serif;">Rp 0</span>
        </div>
      </div>

      <!-- Submit & Cancel Buttons -->
      <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
        <button type="button" onclick="closeLuxuryModal('modalBooking')" class="btn btn-secondary btn-xs" style="border-radius: 0.5rem; font-weight: 700; height: 36px; padding: 0 1rem; background: #e2e8f0; color: #334155; border: none;">
          Batal
        </button>
        <button type="submit" class="btn-pesan-teal" style="height: 36px; padding: 0 1.25rem;">
          <i class="fa-solid fa-credit-card"></i> Lanjutkan Pembayaran
        </button>
      </div>

    </form>
  </div>
</div>

<script>
  let currentPrice = 0;
  let currentDiskon = 0;
  let currentMinRombongan = 0;

  function openBookingModal(id, nama, harga, diskon, minRombongan, lokasi) {
    document.getElementById('bkDestId').value = id;
    document.getElementById('bkDestName').innerText = nama;
    document.getElementById('bkPriceText').innerText = 'Rp ' + Number(harga).toLocaleString('id-ID');
    document.getElementById('bkModalSubtitle').innerText = 'Reservasi tiket resmi untuk ' + nama;
    document.getElementById('bkDestLocation').innerHTML = '<i class="fa-solid fa-location-dot text-amber-500"></i> ' + (lokasi || 'Indonesia');
    
    currentPrice = harga;
    currentDiskon = diskon;
    currentMinRombongan = minRombongan;

    document.getElementById('bkQty').value = 1;
    recalcBooking();
    openLuxuryModal('modalBooking');
  }

  function adjustQty(delta) {
    const input = document.getElementById('bkQty');
    let val = parseInt(input.value) || 1;
    val += delta;
    if (val < 1) val = 1;
    if (val > 500) val = 500;
    input.value = val;
    recalcBooking();
  }

  function recalcBooking() {
    const qtyInput = document.getElementById('bkQty');
    let qty = parseInt(qtyInput.value) || 1;
    if (qty < 1) qty = 1;

    const subtotal = currentPrice * qty;
    let diskonNominal = 0;
    let isRombongan = (qty >= currentMinRombongan && currentDiskon > 0 && currentMinRombongan > 0);

    const hintBadge = document.getElementById('bkGroupDiscountHint');
    if (isRombongan) {
      diskonNominal = (subtotal * currentDiskon) / 100;
      document.getElementById('calcDiskonRow').style.display = 'flex';
      document.getElementById('calcDiskonPct').innerText = currentDiskon;
      document.getElementById('calcDiskonNominal').innerText = '- Rp ' + Math.round(diskonNominal).toLocaleString('id-ID');
      if (hintBadge) hintBadge.style.display = 'inline-block';
    } else {
      document.getElementById('calcDiskonRow').style.display = 'none';
      if (hintBadge) hintBadge.style.display = 'none';
    }

    const totalFinal = subtotal - diskonNominal;

    document.getElementById('calcQty').innerText = qty;
    document.getElementById('calcSubtotal').innerText = 'Rp ' + Math.round(subtotal).toLocaleString('id-ID');
    document.getElementById('calcTotalFinal').innerText = 'Rp ' + Math.round(totalFinal).toLocaleString('id-ID');
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
