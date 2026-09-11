<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('pengunjung');

$userId = $_SESSION['user_id'];
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');
$today = date('Y-m-d');

// Base SQL
$sql = "SELECT p.*, d.nama_destinasi, d.lokasi, d.foto_utama, d.jam_buka, d.jam_tutup, d.hari_operasional, k.nama_kategori
        FROM pemesanan p 
        JOIN destinasi d ON p.destinasi_id = d.id 
        LEFT JOIN kategori_wisata k ON d.kategori_id = k.id 
        WHERE p.user_id = ?";
$params = [$userId];

if ($statusFilter === 'aktif') {
    $sql .= " AND p.status_bayar = 'lunas' AND p.status_kunjungan = 'belum_digunakan' AND p.tanggal_kunjungan >= CURDATE()";
} elseif ($statusFilter === 'selesai') {
    $sql .= " AND p.status_kunjungan = 'sudah_digunakan'";
} elseif ($statusFilter === 'pending') {
    $sql .= " AND p.status_bayar = 'pending'";
} elseif ($statusFilter === 'expired') {
    $sql .= " AND p.status_kunjungan = 'belum_digunakan' AND p.tanggal_kunjungan < CURDATE()";
}

if (!empty($searchQuery)) {
    $sql .= " AND (p.kode_booking LIKE ? OR d.nama_destinasi LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tiketList = $stmt->fetchAll();

// Counts for filter pills
$cntAll = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE user_id = $userId")->fetchColumn();
$cntAktif = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE user_id = $userId AND status_bayar = 'lunas' AND status_kunjungan = 'belum_digunakan' AND tanggal_kunjungan >= CURDATE()")->fetchColumn();
$cntSelesai = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE user_id = $userId AND status_kunjungan = 'sudah_digunakan'")->fetchColumn();
$cntPending = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE user_id = $userId AND status_bayar = 'pending'")->fetchColumn();

$pageTitle = "E-Ticket & Tiket Wisata Saya";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<main class="admin-main">
  
  <!-- Topbar Luxury -->
  <div class="admin-topbar-luxury">
    <div>
      <div style="display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.1rem;">
        <span class="badge-member badge-member-cyan">
          <i class="fa-solid fa-qrcode"></i> Digital Gate Pass
        </span>
        <span style="font-size: 0.72rem; color: #64748b;">
          Total <?= $cntAll ?> Tiket Tercatat
        </span>
      </div>
      <h1 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        E-Ticket & Tiket Wisata Saya
      </h1>
      <p style="font-size: 0.75rem; color: #64748b; margin: 0.1rem 0 0 0;">
        Pindai QR Code di gate masuk untuk verifikasi kunjungan otomatis tanpa antre tiket fisik.
      </p>
    </div>

    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
      <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" class="btn btn-primary btn-xs btn-luxury-pulse" style="font-weight: 800; box-shadow: 0 3px 10px rgba(2, 132, 199, 0.35); height: 30px; padding: 0 0.85rem; border-radius: 0.45rem; display: inline-flex; align-items: center; gap: 0.35rem;">
        <i class="fa-solid fa-cart-plus"></i> Pesan Tiket Baru
      </a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-3" style="border-radius: 0.6rem; padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 1px 4px rgba(0,0,0,0.02); font-size: 0.82rem;">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>" style="font-size: 1.1rem;"></i>
      <div style="line-height: 1.35;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <!-- Filter Pills & Search Bar Row -->
  <div class="card p-2 bg-white shadow-sm mb-3" style="border-radius: 0.75rem; border: 1px solid #e2e8f0;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap;">
      
      <!-- Filter Tabs -->
      <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>pengunjung/tiket_saya.php?status=all" class="btn btn-xs <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 0.45rem; font-weight: 700; height: 30px; padding: 0 0.65rem;">
          Semua (<?= $cntAll ?>)
        </a>
        <a href="<?= BASE_URL ?>pengunjung/tiket_saya.php?status=aktif" class="btn btn-xs <?= $statusFilter === 'aktif' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 0.45rem; font-weight: 700; height: 30px; padding: 0 0.65rem;">
          <i class="fa-solid fa-circle-check text-emerald-500"></i> Siap Pakai (<?= $cntAktif ?>)
        </a>
        <a href="<?= BASE_URL ?>pengunjung/tiket_saya.php?status=pending" class="btn btn-xs <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 0.45rem; font-weight: 700; height: 30px; padding: 0 0.65rem;">
          <i class="fa-solid fa-clock text-amber-500"></i> Menunggu Bayar (<?= $cntPending ?>)
        </a>
        <a href="<?= BASE_URL ?>pengunjung/tiket_saya.php?status=selesai" class="btn btn-xs <?= $statusFilter === 'selesai' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 0.45rem; font-weight: 700; height: 30px; padding: 0 0.65rem;">
          <i class="fa-solid fa-flag-checkered text-slate-500"></i> Selesai (<?= $cntSelesai ?>)
        </a>
      </div>

      <!-- Search Input -->
      <form action="<?= BASE_URL ?>pengunjung/tiket_saya.php" method="GET" style="display: flex; gap: 0.35rem; min-width: 240px; margin: 0;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <div style="position: relative; flex: 1;">
          <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.75rem;"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Cari kode tiket / wisata..." class="form-control" style="padding-left: 2rem; font-size: 0.78rem; border-radius: 0.45rem; height: 30px;">
        </div>
        <button type="submit" class="btn btn-secondary btn-xs" style="border-radius: 0.45rem; height: 30px; padding: 0 0.75rem; font-weight: 700;">
          Cari
        </button>
      </form>

    </div>
  </div>

  <!-- Tickets List (Boarding Pass Cards) -->
  <?php if (empty($tiketList)): ?>
    <div class="card p-6 bg-white shadow-sm text-center" style="border-radius: 0.75rem; border: 1px dashed #cbd5e1; padding: 3rem 1.25rem;">
      <div style="width: 52px; height: 52px; border-radius: 50%; background: #f0fdf4; color: #0284c7; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem auto; font-size: 1.5rem;">
        <i class="fa-solid fa-ticket"></i>
      </div>
      <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0;">
        Tidak Ada E-Ticket Ditemukan
      </h3>
      <p style="font-size: 0.8rem; color: #64748b; max-width: 420px; margin: 0 auto 1rem auto;">
        <?php if (!empty($searchQuery) || $statusFilter !== 'all'): ?>
          Tidak ada tiket yang sesuai dengan kriteria filter pencarian. Silakan reset filter.
        <?php else: ?>
          Anda belum memiliki riwayat tiket wisata. Pilih destinasi impian Anda dan lakukan reservasi instan!
        <?php endif; ?>
      </p>
      <div style="display: flex; gap: 0.5rem; justify-content: center;">
        <?php if (!empty($searchQuery) || $statusFilter !== 'all'): ?>
          <a href="<?= BASE_URL ?>pengunjung/tiket_saya.php" class="btn btn-secondary btn-xs" style="border-radius: 0.45rem;">Reset Filter</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" class="btn btn-primary btn-xs btn-luxury-pulse" style="border-radius: 0.45rem;">
          <i class="fa-solid fa-compass"></i> Booking Tiket Sekarang
        </a>
      </div>
    </div>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 0.85rem;">
      <?php foreach ($tiketList as $t): 
        $isLunas = ($t['status_bayar'] === 'lunas');
        $isUsed = ($t['status_kunjungan'] === 'sudah_digunakan');
        $isExpired = (!$isUsed && strtotime($t['tanggal_kunjungan']) < strtotime($today));
        $isReady = ($isLunas && !$isUsed && !$isExpired);
        $isToday = ($t['tanggal_kunjungan'] === $today);
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($t['kode_booking']);
      ?>
        <div class="ticket-card-luxury" style="border-left: 4px solid <?= $isReady ? '#0284c7' : ($isUsed ? '#10b981' : ($t['status_bayar'] === 'pending' ? '#f59e0b' : '#94a3b8')) ?>;">
          <div class="ticket-notch-left"></div>
          <div class="ticket-notch-right"></div>
          
          <div style="display: grid; grid-template-columns: 180px 1fr 180px; gap: 1rem; padding: 0.85rem 1.15rem; align-items: center;" class="ticket-grid-responsive">
            
            <!-- Column 1: Image & Code -->
            <div style="position: relative; border-radius: 0.55rem; overflow: hidden; height: 110px; background: #0f172a;">
              <img src="<?= BASE_URL ?>assets/uploads/destinasi/<?= htmlspecialchars($t['foto_utama']) ?>" 
                   alt="<?= htmlspecialchars($t['nama_destinasi']) ?>" 
                   style="width: 100%; height: 100%; object-fit: cover;"
                   onerror="this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400&q=80'">
              <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(15,23,42,0.85) 0%, transparent 60%);"></div>
              
              <div style="position: absolute; top: 0.4rem; left: 0.4rem;">
                <span class="badge badge-light" style="font-size: 0.62rem; font-weight: 800; background: rgba(255,255,255,0.92); backdrop-filter: blur(4px); padding: 0.15rem 0.4rem;">
                  <?= htmlspecialchars($t['nama_kategori'] ?? 'Wisata') ?>
                </span>
              </div>

              <div style="position: absolute; bottom: 0.4rem; left: 0.4rem; right: 0.4rem;">
                <code style="display: block; font-size: 0.75rem; font-weight: 900; color: #38bdf8; font-family: monospace; letter-spacing: 0.05em;">
                  <?= htmlspecialchars($t['kode_booking']) ?>
                </code>
              </div>
            </div>

            <!-- Column 2: Ticket Details -->
            <div style="min-width: 0;">
              <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.15rem; flex-wrap: wrap;">
                <?php if ($isReady): ?>
                  <span class="badge badge-success" style="font-size: 0.65rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 9999px; background: #dcfce7; color: #15803d; border: 1px solid #86efac;">
                    <i class="fa-solid fa-circle-check"></i> E-Ticket Valid / Siap Scan
                  </span>
                  <?php if ($isToday): ?>
                    <span class="badge badge-warning" style="font-size: 0.65rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 9999px; background: #fef3c7; color: #b45309; border: 1px solid #fde68a;">
                      🔥 Kunjungan Hari Ini
                    </span>
                  <?php endif; ?>
                <?php elseif ($isUsed): ?>
                  <span class="badge badge-secondary" style="font-size: 0.65rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 9999px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">
                    <i class="fa-solid fa-check-double text-emerald-600"></i> Kunjungan Selesai
                  </span>
                <?php elseif ($isExpired): ?>
                  <span class="badge badge-danger" style="font-size: 0.65rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 9999px;">
                    <i class="fa-solid fa-clock-rotate-left"></i> Kadaluarsa
                  </span>
                <?php else: ?>
                  <span class="badge badge-warning" style="font-size: 0.65rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 9999px; background: #fef3c7; color: #b45309; border: 1px solid #fde68a;">
                    <i class="fa-solid fa-clock"></i> Menunggu Pembayaran
                  </span>
                <?php endif; ?>
              </div>

              <h2 style="font-size: 1.1rem; font-weight: 900; color: #0f172a; margin: 0 0 0.15rem 0;">
                <?= htmlspecialchars($t['nama_destinasi']) ?>
              </h2>

              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.4rem; font-size: 0.75rem; color: #64748b; margin-top: 0.35rem;">
                <div>
                  <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; display: block;">Tanggal Kunjungan</span>
                  <strong style="color: #0f172a;"><i class="fa-regular fa-calendar text-primary"></i> <?= formatTanggalIndo($t['tanggal_kunjungan']) ?></strong>
                </div>
                <div>
                  <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; display: block;">Jumlah Peserta</span>
                  <strong style="color: #0f172a;"><i class="fa-solid fa-users text-emerald-600"></i> <?= $t['jumlah_tiket'] ?> Wisatawan (<?= ucfirst($t['tipe_rombongan']) ?>)</strong>
                </div>
                <div>
                  <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; display: block;">Total Bayar</span>
                  <strong style="color: #0284c7;">Rp <?= number_format($t['total_bayar'], 0, ',', '.') ?></strong>
                </div>
              </div>
            </div>

            <!-- Column 3: Gate QR & Action -->
            <div style="text-align: center; border-left: 1px dashed #e2e8f0; padding-left: 1rem;">
              <?php if ($isLunas): ?>
                <div style="background: #ffffff; padding: 0.35rem; border-radius: 0.5rem; display: inline-block; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;" onclick="openGateModal('<?= htmlspecialchars($t['kode_booking']) ?>', '<?= htmlspecialchars($t['nama_destinasi']) ?>', '<?= formatTanggalIndo($t['tanggal_kunjungan']) ?>', '<?= $t['jumlah_tiket'] ?>')">
                  <img src="<?= $qrCodeUrl ?>" alt="QR Code" style="width: 70px; height: 70px; display: block; border-radius: 0.25rem;">
                </div>
                <div style="margin-top: 0.35rem; display: flex; flex-direction: column; gap: 0.3rem;">
                  <button type="button" onclick="openGateModal('<?= htmlspecialchars($t['kode_booking']) ?>', '<?= htmlspecialchars($t['nama_destinasi']) ?>', '<?= formatTanggalIndo($t['tanggal_kunjungan']) ?>', '<?= $t['jumlah_tiket'] ?>')" class="btn btn-primary btn-xs" style="width: 100%; border-radius: 0.4rem; font-weight: 800; font-size: 0.7rem; padding: 0.25rem 0.5rem;">
                    <i class="fa-solid fa-qrcode"></i> Buka Gate QR
                  </button>
                </div>
              <?php else: ?>
                <div style="background: #fffbeb; padding: 0.6rem 0.5rem; border-radius: 0.5rem; border: 1px dashed #fcd34d; margin-bottom: 0.4rem;">
                  <i class="fa-solid fa-triangle-exclamation text-amber-500" style="font-size: 1.1rem; margin-bottom: 0.15rem; display: block;"></i>
                  <span style="font-size: 0.68rem; font-weight: 700; color: #92400e; display: block;">Belum Lunas</span>
                </div>
                <a href="<?= BASE_URL ?>pembayaran.php?kode=<?= urlencode($t['kode_booking']) ?>" class="btn btn-warning btn-xs" style="width: 100%; border-radius: 0.4rem; font-weight: 800; font-size: 0.72rem; padding: 0.3rem 0.5rem;">
                  <i class="fa-solid fa-credit-card"></i> Bayar Sekarang
                </a>
              <?php endif; ?>
            </div>

          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<!-- Modal Gate Pass QR Code (Cocok & Optimal untuk Layar HP) -->
<div id="modalGatePass" class="luxury-modal-backdrop" style="display: none;">
  <div class="luxury-modal-box" style="max-width: 400px; text-align: center; padding: 1.25rem; border-radius: 1.15rem;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
      <span class="badge-member badge-member-cyan" style="font-size: 0.68rem;">
        <i class="fa-solid fa-mobile-screen-button"></i> Mobile Gate Pass
      </span>
      <button type="button" onclick="closeLuxuryModal('modalGatePass')" style="background: none; border: none; font-size: 1.2rem; color: #94a3b8; cursor: pointer;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <h3 id="modalGateTitle" style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.15rem 0;">Gate Pass Wisata</h3>
    <span id="modalGateDate" style="font-size: 0.75rem; color: #64748b; display: block; margin-bottom: 0.5rem;"></span>

    <!-- Frame QR Khusus Layar HP dengan Kontras Tinggi -->
    <div class="qr-phone-frame">
      <img id="modalGateQrImg" src="" alt="QR Code Gate Pass">
      <code id="modalGateCode" style="display: block; font-size: 0.92rem; font-weight: 900; color: #0284c7; margin-top: 0.45rem; letter-spacing: 0.08em; font-family: monospace;"></code>
    </div>

    <!-- Tip Kecerahan HP -->
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 0.5rem; padding: 0.45rem 0.65rem; margin-bottom: 0.85rem; font-size: 0.72rem; color: #92400e; display: flex; align-items: center; gap: 0.4rem; text-align: left;">
      <i class="fa-solid fa-sun text-amber-500" style="font-size: 1rem;"></i>
      <span><strong>Tips Scan HP:</strong> Naikkan kecerahan layar HP ke maksimal saat didekatkan ke scanner gate masuk.</span>
    </div>

    <div style="display: flex; gap: 0.35rem; justify-content: center; flex-wrap: wrap;">
      <button type="button" onclick="copyToClipboard(document.getElementById('modalGateCode').innerText, 'Kode Booking')" class="btn btn-secondary btn-xs" style="border-radius: 0.45rem; font-weight: 700; height: 32px; padding: 0 0.75rem;">
        <i class="fa-solid fa-copy"></i> Salin Kode
      </button>
      <a id="modalDownloadQrBtn" href="" target="_blank" download="GatePass_QR.png" class="btn btn-secondary btn-xs" style="border-radius: 0.45rem; font-weight: 700; height: 32px; padding: 0 0.75rem; display: inline-flex; align-items: center; gap: 0.3rem;">
        <i class="fa-solid fa-download"></i> Simpan Gambar di HP
      </a>
      <button type="button" onclick="closeLuxuryModal('modalGatePass')" class="btn btn-primary btn-xs" style="border-radius: 0.45rem; font-weight: 800; height: 32px; padding: 0 0.9rem;">
        Tutup
      </button>
    </div>
  </div>
</div>

<script>
  function openGateModal(kode, destinasi, tanggal, tiket) {
    document.getElementById('modalGateTitle').innerText = destinasi;
    document.getElementById('modalGateDate').innerText = 'Jadwal: ' + tanggal + ' (' + tiket + ' Tiket)';
    document.getElementById('modalGateCode').innerText = kode;
    
    // Gunakan resolusi tinggi 400x400 dengan margin dan Error Correction M untuk HP
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=2&ecc=M&data=' + encodeURIComponent(kode);
    document.getElementById('modalGateQrImg').src = qrUrl;
    document.getElementById('modalDownloadQrBtn').href = qrUrl;
    
    openLuxuryModal('modalGatePass');
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
