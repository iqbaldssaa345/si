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

    // Cek destinasi
    $stmtDest = $pdo->prepare("SELECT * FROM destinasi WHERE id = ? AND status = 'buka' LIMIT 1");
    $stmtDest->execute([$destinasiId]);
    $dest = $stmtDest->fetch();

    if (!$dest) {
        $msg = "Destinasi wisata yang dipilih tidak valid atau sedang tutup.";
        $msgType = "danger";
    } elseif (empty($tanggalKunjungan) || strtotime($tanggalKunjungan) < strtotime(date('Y-m-d'))) {
        $msg = "Tanggal kunjungan tidak valid. Pilih tanggal hari ini atau masa mendatang.";
        $msgType = "danger";
    } elseif ($jumlahTiket < 1) {
        $msg = "Jumlah tiket minimal adalah 1 orang.";
        $msgType = "danger";
    } else {
        // Hitung total harga & diskon rombongan
        $hargaSatuan = (float)$dest['harga_tiket'];
        $diskonRombongan = (int)$dest['diskon_rombongan'];
        $minRombongan = (int)$dest['min_rombongan'];

        $subtotal = $hargaSatuan * $jumlahTiket;
        $tipeRombongan = ($jumlahTiket >= $minRombongan) ? 'rombongan' : 'sendiri';
        $diskonNominal = ($tipeRombongan === 'rombongan' && $diskonRombongan > 0) ? (($subtotal * $diskonRombongan) / 100) : 0;
        $totalBayar = $subtotal - $diskonNominal;

        // Generate Unique Booking Code (BK-YYYYMMDD-XXX)
        $prefix = 'BK-' . date('Ymd') . '-';
        $randCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
        $kodeBooking = $prefix . $randCode;

        // Cek unik
        $stmtCek = $pdo->prepare("SELECT id FROM pemesanan WHERE kode_booking = ?");
        $stmtCek->execute([$kodeBooking]);
        if ($stmtCek->fetch()) {
            $kodeBooking = $prefix . rand(1000, 9999);
        }

        $ins = $pdo->prepare("INSERT INTO pemesanan (user_id, destinasi_id, kode_booking, nama_pemesan, no_telp, tanggal_kunjungan, jumlah_tiket, tipe_rombongan, harga_satuan, diskon_didapat, total_bayar, status_bayar, status_kunjungan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'belum_digunakan', NOW())");
        
        if ($ins->execute([$userId, $destinasiId, $kodeBooking, $namaPemesan, $noTelp, $tanggalKunjungan, $jumlahTiket, $tipeRombongan, $hargaSatuan, $diskonNominal, $totalBayar])) {
            setFlash('success', "Pesanan tiket untuk <strong>" . htmlspecialchars($dest['nama_destinasi']) . "</strong> berhasil dibuat dengan Kode: <strong>$kodeBooking</strong>. Silakan selesaikan pembayaran!");
            header("Location: " . BASE_URL . "pembayaran.php?kode=" . urlencode($kodeBooking));
            exit;
        } else {
            $msg = "Terjadi kesalahan saat memproses pemesanan tiket. Silakan coba kembali.";
            $msgType = "danger";
        }
    }
}

// Fetch Kategori for Filter
$kategoriList = $pdo->query("SELECT * FROM kategori_wisata ORDER BY nama_kategori ASC")->fetchAll();

// Filter Query
$katFilter = (int)($_GET['kategori'] ?? 0);
$searchFilter = trim($_GET['q'] ?? '');

$sql = "SELECT d.*, k.nama_kategori, 
        COALESCE(AVG(u.rating), 4.8) as avg_rating,
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

$sql .= " GROUP BY d.id ORDER BY d.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$destinasiList = $stmt->fetchAll();

$pageTitle = "Pesan & Booking Tiket Wisata";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<main class="admin-main">
  
  <!-- Topbar Luxury -->
  <div class="admin-topbar-luxury">
    <div>
      <div style="display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.1rem;">
        <span class="badge-member badge-member-gold">
          <i class="fa-solid fa-compass"></i> Reservasi Tiket Instan
        </span>
        <span style="font-size: 0.72rem; color: #64748b;">
          Tersedia <?= count($destinasiList) ?> Destinasi Pilihan
        </span>
      </div>
      <h1 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Pesan Tiket Destinasi Wisata
      </h1>
      <p style="font-size: 0.75rem; color: #64748b; margin: 0.1rem 0 0 0;">
        Pilih destinasi impian Anda, nikmati diskon rombongan, dan dapatkan E-Ticket QR Code resmi instan.
      </p>
    </div>

    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
      <a href="<?= BASE_URL ?>pengunjung/tiket_saya.php" class="btn btn-secondary btn-xs" style="background: #ffffff; border: 1px solid #cbd5e1; font-weight: 700; height: 30px; padding: 0 0.75rem; border-radius: 0.45rem; display: inline-flex; align-items: center; gap: 0.35rem;">
        <i class="fa-solid fa-ticket text-primary"></i> Lihat E-Ticket Saya
      </a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-3" style="border-radius: 0.6rem; padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 1px 4px rgba(0,0,0,0.02); font-size: 0.82rem;">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>" style="font-size: 1.1rem;"></i>
      <div style="line-height: 1.35;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mb-3" style="border-radius: 0.6rem; padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 1px 4px rgba(0,0,0,0.02); font-size: 0.82rem;">
      <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>" style="font-size: 1.1rem;"></i>
      <div style="line-height: 1.35;"><?= $msg ?></div>
    </div>
  <?php endif; ?>

  <!-- Filter & Search Card -->
  <div class="card p-2 bg-white shadow-sm mb-3" style="border-radius: 0.75rem; border: 1px solid #e2e8f0;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap;">
      
      <!-- Category Filter Pills -->
      <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" class="btn btn-xs <?= $katFilter === 0 ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 0.45rem; font-weight: 700; height: 30px; padding: 0 0.65rem;">
          Semua Kategori
        </a>
        <?php foreach ($kategoriList as $k): ?>
          <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php?kategori=<?= $k['id'] ?>" class="btn btn-xs <?= $katFilter == $k['id'] ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 0.45rem; font-weight: 700; height: 30px; padding: 0 0.65rem;">
            <?= htmlspecialchars($k['nama_kategori']) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Search Input -->
      <form action="<?= BASE_URL ?>pengunjung/pesan_tiket.php" method="GET" style="display: flex; gap: 0.35rem; min-width: 230px; margin: 0;">
        <?php if ($katFilter > 0): ?>
          <input type="hidden" name="kategori" value="<?= $katFilter ?>">
        <?php endif; ?>
        <div style="position: relative; flex: 1;">
          <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.75rem;"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($searchFilter) ?>" placeholder="Cari nama wisata..." class="form-control" style="padding-left: 2rem; font-size: 0.78rem; border-radius: 0.45rem; height: 30px;">
        </div>
        <button type="submit" class="btn btn-secondary btn-xs" style="border-radius: 0.45rem; height: 30px; padding: 0 0.75rem; font-weight: 700;">
          Cari
        </button>
      </form>

    </div>
  </div>

  <!-- Destinasi Grid Cards -->
  <?php if (empty($destinasiList)): ?>
    <div class="card p-6 bg-white shadow-sm text-center" style="border-radius: 0.75rem; border: 1px dashed #cbd5e1; padding: 3rem 1.25rem;">
      <div style="width: 52px; height: 52px; border-radius: 50%; background: #f0fdf4; color: #0284c7; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem auto; font-size: 1.5rem;">
        <i class="fa-solid fa-mountain-sun"></i>
      </div>
      <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0;">
        Tidak Ada Destinasi Ditemukan
      </h3>
      <p style="font-size: 0.8rem; color: #64748b; max-width: 420px; margin: 0 auto 1rem auto;">
        Tidak ada data destinasi wisata yang sesuai dengan pencarian Anda. Silakan reset filter pencarian.
      </p>
      <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" class="btn btn-secondary btn-xs" style="border-radius: 0.45rem;">
        Reset Filter
      </a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-3 gap-3">
      <?php foreach ($destinasiList as $d): 
        $harga = (float)$d['harga_tiket'];
        $diskon = (int)$d['diskon_rombongan'];
        $minRombongan = (int)$d['min_rombongan'];
      ?>
        <div class="dest-card-luxury">
          
          <!-- Image Cover -->
          <div style="position: relative; height: 140px; background: #0f172a; overflow: hidden;">
            <img src="<?= BASE_URL ?>assets/uploads/destinasi/<?= htmlspecialchars($d['foto_utama']) ?>" 
                 alt="<?= htmlspecialchars($d['nama_destinasi']) ?>" 
                 style="width: 100%; height: 100%; object-fit: cover;"
                 onerror="this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=500&q=80'">
            <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(15,23,42,0.85) 0%, transparent 50%);"></div>

            <div style="position: absolute; top: 0.45rem; left: 0.45rem; display: flex; gap: 0.3rem;">
              <span class="badge badge-light" style="font-size: 0.62rem; font-weight: 800; background: rgba(255,255,255,0.92); backdrop-filter: blur(4px); padding: 0.15rem 0.4rem;">
                <?= htmlspecialchars($d['nama_kategori'] ?? 'Wisata') ?>
              </span>
            </div>

            <div style="position: absolute; top: 0.45rem; right: 0.45rem;">
              <span style="font-size: 0.68rem; font-weight: 800; color: #f59e0b; background: rgba(15,23,42,0.75); backdrop-filter: blur(4px); padding: 0.15rem 0.45rem; border-radius: 9999px; display: inline-flex; align-items: center; gap: 2px;">
                <i class="fa-solid fa-star"></i> <?= number_format((float)$d['avg_rating'], 1) ?>
              </span>
            </div>

            <div style="position: absolute; bottom: 0.45rem; left: 0.45rem; right: 0.45rem;">
              <span style="color: #94a3b8; font-size: 0.68rem; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <i class="fa-solid fa-location-dot text-amber-400"></i> <?= htmlspecialchars($d['lokasi']) ?>
              </span>
            </div>
          </div>

          <!-- Body Info -->
          <div style="padding: 0.75rem 0.85rem; display: flex; flex-direction: column; flex: 1; justify-content: space-between;">
            <div>
              <h2 style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0 0 0.3rem 0; line-height: 1.25;">
                <?= htmlspecialchars($d['nama_destinasi']) ?>
              </h2>

              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.45rem; font-size: 0.72rem; color: #64748b;">
                <span><i class="fa-regular fa-clock text-primary"></i> <?= substr($d['jam_buka'], 0, 5) ?> - <?= substr($d['jam_tutup'], 0, 5) ?> WIB</span>
                <?php if ($diskon > 0): ?>
                  <span style="color: #ea580c; font-weight: 800; background: #ffedd5; padding: 0.1rem 0.35rem; border-radius: 4px; font-size: 0.65rem;">
                    Diskon <?= $diskon ?>% (Min <?= $minRombongan ?>)
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Price & Book Button -->
            <div style="border-top: 1px solid #f1f5f9; padding-top: 0.5rem; display: flex; align-items: center; justify-content: space-between; gap: 0.4rem;">
              <div>
                <span style="font-size: 0.62rem; color: #64748b; font-weight: 700; text-transform: uppercase; display: block;">Harga Tiket</span>
                <span style="font-size: 0.92rem; font-weight: 900; color: #0284c7;">
                  Rp <?= number_format($harga, 0, ',', '.') ?>
                </span>
              </div>
              <button type="button" 
                      onclick="openBookingModal(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['nama_destinasi'])) ?>', <?= $harga ?>, <?= $diskon ?>, <?= $minRombongan ?>)" 
                      class="btn btn-primary btn-xs btn-luxury-pulse" 
                      style="font-weight: 800; border-radius: 0.45rem; height: 30px; padding: 0 0.85rem; font-size: 0.75rem;">
                <i class="fa-solid fa-cart-plus"></i> Pesan
              </button>
            </div>

          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<!-- Modal Booking Wisata Interactive (Live Price Calculator) -->
<div id="modalBooking" class="luxury-modal-backdrop" style="display: none;">
  <div class="luxury-modal-box" style="max-width: 460px; padding: 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.6rem; margin-bottom: 0.85rem;">
      <div style="display: flex; align-items: center; gap: 0.4rem;">
        <div style="width: 32px; height: 32px; border-radius: 0.45rem; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
          <i class="fa-solid fa-ticket"></i>
        </div>
        <div>
          <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0;">Form Reservasi Tiket</h3>
          <span id="bkModalSubtitle" style="font-size: 0.72rem; color: #64748b;"></span>
        </div>
      </div>
      <button type="button" onclick="closeLuxuryModal('modalBooking')" style="background: none; border: none; font-size: 1.15rem; color: #94a3b8; cursor: pointer;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="<?= BASE_URL ?>pengunjung/pesan_tiket.php" method="POST" id="formBookingTiket">
      <input type="hidden" name="action_booking" value="1">
      <input type="hidden" name="destinasi_id" id="bkDestId" value="">

      <!-- Destination Summary Pill -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.55rem; padding: 0.55rem 0.75rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
          <span style="font-size: 0.65rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Destinasi</span>
          <strong id="bkDestName" style="display: block; font-size: 0.85rem; color: #0f172a;"></strong>
        </div>
        <div style="text-align: right;">
          <span style="font-size: 0.65rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Harga Satuan</span>
          <strong id="bkPriceText" style="display: block; font-size: 0.85rem; color: #0284c7;"></strong>
        </div>
      </div>

      <!-- Tanggal Kunjungan -->
      <div class="form-group mb-2">
        <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">
          Tanggal Kunjungan <span style="color: #ef4444;">*</span>
        </label>
        <input type="date" name="tanggal_kunjungan" id="bkTanggal" class="form-control" style="font-size: 0.8rem; border-radius: 0.45rem; height: 32px;" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
      </div>

      <!-- Jumlah Tiket Stepper -->
      <div class="form-group mb-2">
        <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">
          Jumlah Wisatawan (Tiket) <span style="color: #ef4444;">*</span>
        </label>
        <div style="display: flex; gap: 0.4rem; align-items: center;">
          <button type="button" onclick="adjustQty(-1)" class="btn btn-secondary btn-xs" style="height: 32px; width: 34px; font-size: 0.9rem; font-weight: 900; border-radius: 0.45rem;">-</button>
          <input type="number" name="jumlah_tiket" id="bkQty" class="form-control" style="font-size: 0.85rem; font-weight: 800; text-align: center; border-radius: 0.45rem; height: 32px;" value="1" min="1" max="500" required oninput="recalcBooking()">
          <button type="button" onclick="adjustQty(1)" class="btn btn-secondary btn-xs" style="height: 32px; width: 34px; font-size: 0.9rem; font-weight: 900; border-radius: 0.45rem;">+</button>
        </div>
      </div>

      <!-- Nama & Kontak -->
      <div class="grid grid-cols-2 gap-2 mb-3">
        <div>
          <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">Nama Pemesan</label>
          <input type="text" name="nama_pemesan" value="<?= htmlspecialchars($userName) ?>" class="form-control" style="font-size: 0.8rem; border-radius: 0.45rem; height: 32px;" required>
        </div>
        <div>
          <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">No WhatsApp</label>
          <input type="text" name="no_telp" value="<?= htmlspecialchars($userTelp) ?>" class="form-control" style="font-size: 0.8rem; border-radius: 0.45rem; height: 32px;" placeholder="08123456789" required>
        </div>
      </div>

      <!-- Live Calculation Box -->
      <div style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #ffffff; border-radius: 0.55rem; padding: 0.75rem 0.85rem; margin-bottom: 0.85rem;">
        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; opacity: 0.8; margin-bottom: 0.2rem;">
          <span>Subtotal (<span id="calcQty">1</span> Tiket)</span>
          <span id="calcSubtotal">Rp 0</span>
        </div>
        <div id="calcDiskonRow" style="display: none; justify-content: space-between; font-size: 0.75rem; color: #4ade80; margin-bottom: 0.2rem;">
          <span>Diskon Rombongan (<span id="calcDiskonPct">0</span>%)</span>
          <span id="calcDiskonNominal">- Rp 0</span>
        </div>
        <div style="border-top: 1px dashed rgba(255,255,255,0.2); padding-top: 0.35rem; margin-top: 0.2rem; display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 0.8rem; font-weight: 800;">Total Pembayaran</span>
          <span id="calcTotalFinal" style="font-size: 1.1rem; font-weight: 900; color: #38bdf8; font-family: 'Outfit', sans-serif;">Rp 0</span>
        </div>
      </div>

      <!-- Submit Buttons -->
      <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
        <button type="button" onclick="closeLuxuryModal('modalBooking')" class="btn btn-secondary btn-xs" style="border-radius: 0.45rem; font-weight: 700; height: 32px; padding: 0 0.85rem;">
          Batal
        </button>
        <button type="submit" class="btn btn-primary btn-xs btn-luxury-pulse" style="border-radius: 0.45rem; font-weight: 800; height: 32px; padding: 0 1rem; box-shadow: 0 3px 10px rgba(2, 132, 199, 0.4);">
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

  function openBookingModal(id, nama, harga, diskon, minRombongan) {
    document.getElementById('bkDestId').value = id;
    document.getElementById('bkDestName').innerText = nama;
    document.getElementById('bkPriceText').innerText = 'Rp ' + Number(harga).toLocaleString('id-ID');
    document.getElementById('bkModalSubtitle').innerText = 'Pesan tiket resmi untuk ' + nama;
    
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
    let isRombongan = (qty >= currentMinRombongan && currentDiskon > 0);

    if (isRombongan) {
      diskonNominal = (subtotal * currentDiskon) / 100;
      document.getElementById('calcDiskonRow').style.display = 'flex';
      document.getElementById('calcDiskonPct').innerText = currentDiskon;
      document.getElementById('calcDiskonNominal').innerText = '- Rp ' + Math.round(diskonNominal).toLocaleString('id-ID');
    } else {
      document.getElementById('calcDiskonRow').style.display = 'none';
    }

    const totalFinal = subtotal - diskonNominal;

    document.getElementById('calcQty').innerText = qty;
    document.getElementById('calcSubtotal').innerText = 'Rp ' + Math.round(subtotal).toLocaleString('id-ID');
    document.getElementById('calcTotalFinal').innerText = 'Rp ' + Math.round(totalFinal).toLocaleString('id-ID');
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
