<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('pengunjung');

$userId = $_SESSION['user_id'];
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

$msg = '';
$msgType = '';

// 1. Handle Upload Bukti Transfer
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_upload_bukti'])) {
    $pemesananId = (int)($_POST['pemesanan_id'] ?? 0);
    
    // Cek kepemilikan pesanan
    $stmtCek = $pdo->prepare("SELECT * FROM pemesanan WHERE id = ? AND user_id = ? AND status_bayar = 'pending'");
    $stmtCek->execute([$pemesananId, $userId]);
    $pesanan = $stmtCek->fetch();

    if (!$pesanan) {
        setFlash('danger', 'Pesanan tidak valid atau sudah tidak dalam status menunggu pembayaran.');
    } else {
        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['bukti_transfer']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

            if (in_array($ext, $allowed)) {
                $fileName = 'bukti_' . $pesanan['kode_booking'] . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/../assets/uploads/bukti/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                if (move_uploaded_file($tmp, $uploadDir . $fileName)) {
                    $upd = $pdo->prepare("UPDATE pemesanan SET bukti_bayar = ? WHERE id = ?");
                    $upd->execute([$fileName, $pemesananId]);
                    setFlash('success', 'Bukti pembayaran untuk kode <strong>' . htmlspecialchars($pesanan['kode_booking']) . '</strong> berhasil diunggah! Mohon tunggu konfirmasi admin.');
                } else {
                    setFlash('danger', 'Gagal memindahkan file bukti transfer ke server.');
                }
            } else {
                setFlash('danger', 'Format file tidak didukung. Harap upload gambar JPG, PNG, atau WEBP.');
            }
        } else {
            setFlash('danger', 'Silakan pilih file bukti pembayaran yang valid.');
        }
    }
    header("Location: " . BASE_URL . "pengunjung/riwayat_transaksi.php");
    exit;
}

// 2. Handle Batalkan Pesanan
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_cancel'])) {
    $pemesananId = (int)($_POST['pemesanan_id'] ?? 0);
    $stmtCek = $pdo->prepare("SELECT kode_booking FROM pemesanan WHERE id = ? AND user_id = ? AND status_bayar = 'pending'");
    $stmtCek->execute([$pemesananId, $userId]);
    $kodeB = $stmtCek->fetchColumn();

    if ($kodeB) {
        $del = $pdo->prepare("DELETE FROM pemesanan WHERE id = ?");
        $del->execute([$pemesananId]);
        setFlash('success', "Pesanan tiket dengan kode <strong>$kodeB</strong> berhasil dibatalkan.");
    } else {
        setFlash('danger', "Pesanan tidak dapat dibatalkan.");
    }
    header("Location: " . BASE_URL . "pengunjung/riwayat_transaksi.php");
    exit;
}

// 3. Query Pemesanan
$sql = "SELECT p.*, d.nama_destinasi, d.lokasi, d.foto_utama, k.nama_kategori
        FROM pemesanan p 
        JOIN destinasi d ON p.destinasi_id = d.id 
        LEFT JOIN kategori_wisata k ON d.kategori_id = k.id 
        WHERE p.user_id = ?";
$params = [$userId];

if ($statusFilter === 'pending') {
    $sql .= " AND p.status_bayar = 'pending'";
} elseif ($statusFilter === 'lunas') {
    $sql .= " AND p.status_bayar = 'lunas'";
} elseif ($statusFilter === 'selesai') {
    $sql .= " AND p.status_kunjungan = 'sudah_digunakan'";
}

if (!empty($searchQuery)) {
    $sql .= " AND (p.kode_booking LIKE ? OR d.nama_destinasi LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transaksiList = $stmt->fetchAll();

// Total Summary
$totalBelanja = 0;
$totalTiketBeli = 0;
$totalPendingCount = 0;
foreach ($transaksiList as $row) {
    if ($row['status_bayar'] === 'lunas') {
        $totalBelanja += (float)$row['total_bayar'];
        $totalTiketBeli += (int)$row['jumlah_tiket'];
    } elseif ($row['status_bayar'] === 'pending') {
        $totalPendingCount++;
    }
}

$pageTitle = "Riwayat Transaksi & Pembayaran";
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
          <i class="fa-solid fa-receipt"></i> Mutasi Pemesanan
        </span>
        <span style="font-size: 0.72rem; color: #64748b;">
          Total Belanja Lunas: Rp <?= number_format($totalBelanja, 0, ',', '.') ?>
        </span>
      </div>
      <h1 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Riwayat Transaksi & Pembayaran
      </h1>
      <p style="font-size: 0.75rem; color: #64748b; margin: 0.1rem 0 0 0;">
        Pantau status verifikasi pembayaran, upload bukti transfer bank, dan akses riwayat transaksi.
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

  <!-- Bank Info Card (Compact) -->
  <div class="card p-3 bg-white shadow-sm mb-3" style="border-radius: 0.75rem; border: 1px solid #e2e8f0; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.85rem; flex-wrap: wrap;">
      <div style="display: flex; align-items: center; gap: 0.6rem;">
        <div style="width: 36px; height: 36px; border-radius: 0.5rem; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-building-columns"></i>
        </div>
        <div>
          <span style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">Rekening Resmi Pembayaran</span>
          <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; font-weight: 800; color: #0f172a;">
            <span>Bank BCA: <strong>123-456-7890</strong> (a.n. Pesona Nusantara)</span>
            <button type="button" onclick="copyToClipboard('1234567890', 'No Rekening BCA')" class="btn btn-secondary btn-xs" style="padding: 0.1rem 0.4rem; font-size: 0.68rem; border-radius: 0.35rem; font-weight: 700;">
              <i class="fa-solid fa-copy"></i> Salin
            </button>
          </div>
        </div>
      </div>
      <div style="font-size: 0.72rem; color: #64748b;">
        <i class="fa-solid fa-circle-info text-primary"></i> Upload bukti transfer setelah transfer agar tiket segera diaktifkan.
      </div>
    </div>
  </div>

  <!-- Filter Tabs & Search Form -->
  <div class="card p-2 bg-white shadow-sm mb-3" style="border-radius: 0.75rem; border: 1px solid #e2e8f0;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap;">
      
      <!-- Filter Tabs -->
      <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>pengunjung/riwayat_transaksi.php?status=all" class="btn btn-xs <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 0.45rem; font-weight: 700; height: 30px; padding: 0 0.65rem;">
          Semua Mutasi
        </a>
        <a href="<?= BASE_URL ?>pengunjung/riwayat_transaksi.php?status=pending" class="btn btn-xs <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 0.45rem; font-weight: 700; height: 30px; padding: 0 0.65rem;">
          <i class="fa-solid fa-clock text-amber-500"></i> Menunggu Bayar
        </a>
        <a href="<?= BASE_URL ?>pengunjung/riwayat_transaksi.php?status=lunas" class="btn btn-xs <?= $statusFilter === 'lunas' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 0.45rem; font-weight: 700; height: 30px; padding: 0 0.65rem;">
          <i class="fa-solid fa-circle-check text-emerald-500"></i> Lunas
        </a>
        <a href="<?= BASE_URL ?>pengunjung/riwayat_transaksi.php?status=selesai" class="btn btn-xs <?= $statusFilter === 'selesai' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 0.45rem; font-weight: 700; height: 30px; padding: 0 0.65rem;">
          <i class="fa-solid fa-flag-checkered text-slate-500"></i> Kunjungan Selesai
        </a>
      </div>

      <!-- Search Input -->
      <form action="<?= BASE_URL ?>pengunjung/riwayat_transaksi.php" method="GET" style="display: flex; gap: 0.35rem; min-width: 230px; margin: 0;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <div style="position: relative; flex: 1;">
          <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.75rem;"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Cari kode / destinasi..." class="form-control" style="padding-left: 2rem; font-size: 0.78rem; border-radius: 0.45rem; height: 30px;">
        </div>
        <button type="submit" class="btn btn-secondary btn-xs" style="border-radius: 0.45rem; height: 30px; padding: 0 0.75rem; font-weight: 700;">
          Cari
        </button>
      </form>

    </div>
  </div>

  <!-- Transaction Table Card -->
  <div class="card-table-luxury">
    <?php if (empty($transaksiList)): ?>
      <div style="padding: 3rem 1.25rem; text-align: center; color: #64748b;">
        <div style="width: 50px; height: 50px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem auto; font-size: 1.35rem;">
          <i class="fa-solid fa-receipt"></i>
        </div>
        <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem 0;">Tidak Ada Riwayat Transaksi</h3>
        <p style="font-size: 0.8rem; margin: 0 0 1rem 0;">Belum ada data transaksi yang sesuai filter.</p>
        <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" class="btn btn-primary btn-xs" style="border-radius: 0.45rem; font-weight: 800;">
          <i class="fa-solid fa-cart-plus"></i> Pesan Tiket Wisata
        </a>
      </div>
    <?php else: ?>
      <div style="overflow-x: auto;">
        <table class="table-luxury">
          <thead>
            <tr>
              <th>Kode Booking</th>
              <th>Destinasi Wisata</th>
              <th>Kunjungan</th>
              <th>Tiket</th>
              <th>Total Bayar</th>
              <th>Bukti Bayar</th>
              <th>Status</th>
              <th style="text-align: right;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transaksiList as $t): 
              $isLunas = ($t['status_bayar'] === 'lunas');
              $isSelesai = ($t['status_kunjungan'] === 'sudah_digunakan');
              $hasBukti = !empty($t['bukti_bayar']);
            ?>
              <tr>
                <td>
                  <code style="font-size: 0.78rem; font-weight: 900; color: #0284c7; font-family: monospace;">
                    <?= htmlspecialchars($t['kode_booking']) ?>
                  </code>
                  <div style="font-size: 0.65rem; color: #94a3b8;">
                    <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?>
                  </div>
                </td>
                <td>
                  <div style="font-weight: 800; color: #0f172a; font-size: 0.82rem;">
                    <?= htmlspecialchars($t['nama_destinasi']) ?>
                  </div>
                  <div style="font-size: 0.68rem; color: #64748b;">
                    <i class="fa-solid fa-location-dot text-amber-500"></i> <?= htmlspecialchars($t['lokasi']) ?>
                  </div>
                </td>
                <td>
                  <div style="font-size: 0.78rem; font-weight: 700; color: #334155;">
                    <?= date('d/m/Y', strtotime($t['tanggal_kunjungan'])) ?>
                  </div>
                </td>
                <td>
                  <span style="font-weight: 700; color: #0f172a; font-size: 0.78rem;">
                    <?= $t['jumlah_tiket'] ?> Org
                  </span>
                  <span style="font-size: 0.65rem; color: #64748b; display: block;">
                    (<?= ucfirst($t['tipe_rombongan']) ?>)
                  </span>
                </td>
                <td>
                  <span style="font-weight: 900; color: #0284c7; font-size: 0.82rem;">
                    Rp <?= number_format($t['total_bayar'], 0, ',', '.') ?>
                  </span>
                </td>
                <td>
                  <?php if ($hasBukti): ?>
                    <a href="<?= BASE_URL ?>assets/uploads/bukti/<?= htmlspecialchars($t['bukti_bayar']) ?>" target="_blank" class="btn btn-secondary btn-xs" style="padding: 0.15rem 0.45rem; font-size: 0.68rem; border-radius: 0.35rem; font-weight: 700;">
                      <i class="fa-solid fa-image text-primary"></i> Lihat Bukti
                    </a>
                  <?php else: ?>
                    <span style="font-size: 0.68rem; color: #94a3b8; font-style: italic;">Belum upload</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($isSelesai): ?>
                    <span class="badge badge-secondary" style="font-size: 0.65rem; padding: 0.15rem 0.45rem; border-radius: 9999px;">
                      <i class="fa-solid fa-check-double"></i> Selesai
                    </span>
                  <?php elseif ($isLunas): ?>
                    <span class="badge badge-success" style="font-size: 0.65rem; padding: 0.15rem 0.45rem; border-radius: 9999px; background: #dcfce7; color: #15803d; border: 1px solid #86efac;">
                      <i class="fa-solid fa-circle-check"></i> Lunas
                    </span>
                  <?php else: ?>
                    <span class="badge badge-warning" style="font-size: 0.65rem; padding: 0.15rem 0.45rem; border-radius: 9999px; background: #fef3c7; color: #b45309; border: 1px solid #fde68a;">
                      <i class="fa-solid fa-clock"></i> Belum Bayar
                    </span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 0.3rem;">
                    <?php if ($isLunas): ?>
                      <a href="<?= BASE_URL ?>pengunjung/tiket_saya.php?q=<?= urlencode($t['kode_booking']) ?>" class="btn btn-secondary btn-xs" style="padding: 0.2rem 0.5rem; font-size: 0.7rem; border-radius: 0.35rem; font-weight: 700;" title="E-Ticket">
                        <i class="fa-solid fa-ticket text-primary"></i> E-Ticket
                      </a>
                    <?php else: ?>
                      <button type="button" onclick="openUploadModal(<?= $t['id'] ?>, '<?= htmlspecialchars($t['kode_booking']) ?>', '<?= htmlspecialchars(addslashes($t['nama_destinasi'])) ?>', <?= $t['total_bayar'] ?>)" class="btn btn-primary btn-xs" style="padding: 0.2rem 0.55rem; font-size: 0.7rem; border-radius: 0.35rem; font-weight: 800;" title="Upload Bukti Transfer">
                        <i class="fa-solid fa-upload"></i> Upload
                      </button>
                      <a href="<?= BASE_URL ?>pembayaran.php?kode=<?= urlencode($t['kode_booking']) ?>" class="btn btn-warning btn-xs" style="padding: 0.2rem 0.55rem; font-size: 0.7rem; border-radius: 0.35rem; font-weight: 800;" title="Bayar">
                        <i class="fa-solid fa-credit-card"></i> Bayar
                      </a>
                      <form action="<?= BASE_URL ?>pengunjung/riwayat_transaksi.php" method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')">
                        <input type="hidden" name="action_cancel" value="1">
                        <input type="hidden" name="pemesanan_id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-xs" style="padding: 0.2rem 0.45rem; font-size: 0.7rem; border-radius: 0.35rem;" title="Batalkan">
                          <i class="fa-solid fa-xmark"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</main>

<!-- Modal Upload Bukti Transfer -->
<div id="modalUploadBukti" class="luxury-modal-backdrop" style="display: none;">
  <div class="luxury-modal-box" style="max-width: 440px; padding: 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.6rem; margin-bottom: 0.85rem;">
      <div style="display: flex; align-items: center; gap: 0.4rem;">
        <div style="width: 32px; height: 32px; border-radius: 0.45rem; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
          <i class="fa-solid fa-receipt"></i>
        </div>
        <div>
          <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0;">Upload Bukti Transfer</h3>
          <span style="font-size: 0.72rem; color: #64748b;">Konfirmasi pembayaran Anda</span>
        </div>
      </div>
      <button type="button" onclick="closeLuxuryModal('modalUploadBukti')" style="background: none; border: none; font-size: 1.15rem; color: #94a3b8; cursor: pointer;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="<?= BASE_URL ?>pengunjung/riwayat_transaksi.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action_upload_bukti" value="1">
      <input type="hidden" name="pemesanan_id" id="upPemesananId" value="">

      <!-- Info Pesanan -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.55rem; padding: 0.55rem 0.75rem; margin-bottom: 0.75rem;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.2rem;">
          <span style="font-size: 0.68rem; color: #64748b; font-weight: 700;">Kode Booking</span>
          <code id="upKodeBooking" style="font-size: 0.75rem; font-weight: 900; color: #0284c7; font-family: monospace;"></code>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.2rem;">
          <span style="font-size: 0.68rem; color: #64748b; font-weight: 700;">Destinasi</span>
          <strong id="upNamaDestinasi" style="font-size: 0.75rem; color: #0f172a;"></strong>
        </div>
        <div style="display: flex; justify-content: space-between;">
          <span style="font-size: 0.68rem; color: #64748b; font-weight: 700;">Total Tagihan</span>
          <strong id="upTotalBayar" style="font-size: 0.85rem; color: #0284c7; font-weight: 900;"></strong>
        </div>
      </div>

      <!-- File Input -->
      <div class="form-group mb-3">
        <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">
          Pilih File Gambar Bukti Struk / Screenshot <span style="color: #ef4444;">*</span>
        </label>
        <input type="file" name="bukti_transfer" accept="image/jpeg,image/png,image/webp" class="form-control" style="font-size: 0.78rem; border-radius: 0.45rem; padding: 0.35rem;" required>
        <span style="font-size: 0.68rem; color: #94a3b8; display: block; margin-top: 0.2rem;">
          Format: JPG, PNG, WEBP (Maksimal 3MB)
        </span>
      </div>

      <!-- Submit Buttons -->
      <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
        <button type="button" onclick="closeLuxuryModal('modalUploadBukti')" class="btn btn-secondary btn-xs" style="border-radius: 0.45rem; font-weight: 700; height: 32px; padding: 0 0.85rem;">
          Batal
        </button>
        <button type="submit" class="btn btn-primary btn-xs btn-luxury-pulse" style="border-radius: 0.45rem; font-weight: 800; height: 32px; padding: 0 1rem;">
          <i class="fa-solid fa-cloud-arrow-up"></i> Upload Bukti
        </button>
      </div>

    </form>
  </div>
</div>

<script>
  function openUploadModal(id, kode, nama, total) {
    document.getElementById('upPemesananId').value = id;
    document.getElementById('upKodeBooking').innerText = kode;
    document.getElementById('upNamaDestinasi').innerText = nama;
    document.getElementById('upTotalBayar').innerText = 'Rp ' + Number(total).toLocaleString('id-ID');
    openLuxuryModal('modalUploadBukti');
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
