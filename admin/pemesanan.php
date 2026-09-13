<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('admin');

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

// 1. UPDATE STATUS PEMBAYARAN CEPAT (POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_status'])) {
    $pemesananId = (int)$_POST['pemesanan_id'];
    $newStatus = $_POST['status_bayar'];

    $upd = $pdo->prepare("UPDATE pemesanan SET status_bayar = ? WHERE id = ?");
    $upd->execute([$newStatus, $pemesananId]);

    // Catat log transaksi jika lunas
    if ($newStatus === 'lunas') {
        $stmtP = $pdo->prepare("SELECT kode_booking, total_bayar, metode_pembayaran FROM pemesanan WHERE id = ?");
        $stmtP->execute([$pemesananId]);
        $rowP = $stmtP->fetch();
        if ($rowP) {
            try {
                $log = $pdo->prepare("INSERT INTO transaksi_log (user_id, tipe_transaksi, deskripsi, created_at) VALUES (?, 'pembayaran', ?, NOW())");
                $log->execute([$_SESSION['user_id'], 'Verifikasi pembayaran lunas pemesanan #' . $rowP['kode_booking'] . ' (' . formatRupiah($rowP['total_bayar']) . ')']);
            } catch (Exception $e) {}
        }
        setFlash('success', 'Transaksi <strong>#' . htmlspecialchars($rowP['kode_booking'] ?? $pemesananId) . '</strong> berhasil diverifikasi LUNAS!');
    } elseif ($newStatus === 'batal') {
        setFlash('warning', 'Transaksi pemesanan telah diubah menjadi BATAL / DITOLAK.');
    } else {
        setFlash('info', 'Status transaksi berhasil diperbarui menjadi PENDING.');
    }

    header("Location: " . BASE_URL . "admin/pemesanan.php" . (!empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''));
    exit;
}

// 2. HAPUS PEMESANAN
if ($action === 'delete' && $id > 0) {
    $stmtCek = $pdo->prepare("SELECT kode_booking, bukti_bayar FROM pemesanan WHERE id = ?");
    $stmtCek->execute([$id]);
    $pesananDel = $stmtCek->fetch();

    if ($pesananDel) {
        if (!empty($pesananDel['bukti_bayar']) && file_exists(__DIR__ . '/../assets/uploads/bukti/' . $pesananDel['bukti_bayar'])) {
            @unlink(__DIR__ . '/../assets/uploads/bukti/' . $pesananDel['bukti_bayar']);
        }
        $del = $pdo->prepare("DELETE FROM pemesanan WHERE id = ?");
        $del->execute([$id]);
        setFlash('success', 'Data transaksi <strong>#' . htmlspecialchars($pesananDel['kode_booking']) . '</strong> berhasil dihapus.');
    } else {
        setFlash('danger', 'Data pemesanan tidak ditemukan.');
    }
    header("Location: " . BASE_URL . "admin/pemesanan.php" . (!empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''));
    exit;
}

// 3. STATISTIK KPI SUMMARY
$cntTotal = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan")->fetchColumn();
$cntPending = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status_bayar = 'pending'")->fetchColumn();
$cntLunas = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status_bayar = 'lunas'")->fetchColumn();
$cntBatal = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status_bayar = 'batal'")->fetchColumn();
$totalOmsetLunas = (float)$pdo->query("SELECT COALESCE(SUM(total_bayar), 0) FROM pemesanan WHERE status_bayar = 'lunas'")->fetchColumn();

// 4. DETAIL PEMESANAN (MODAL / VIEW INSPECTOR)
$detailPesanan = null;
if ($id > 0 && $action === 'view') {
    $stmt = $pdo->prepare("SELECT p.*, d.nama_destinasi, d.lokasi, d.foto_utama, d.harga_tiket as tarif_satuan, 
                                  k.nama_kategori, u.nama as nama_user, u.email as email_user, u.no_telp as telp_user, u.foto as user_foto
                           FROM pemesanan p 
                           JOIN destinasi d ON p.destinasi_id = d.id 
                           JOIN kategori_wisata k ON d.kategori_id = k.id
                           JOIN users u ON p.user_id = u.id 
                           WHERE p.id = ? LIMIT 1");
    $stmt->execute([$id]);
    $detailPesanan = $stmt->fetch();
}

// 5. QUERY LIST TRANSAKSI DENGAN FILTER & SEARCH
$sql = "SELECT p.*, d.nama_destinasi, d.foto_utama, k.nama_kategori, u.nama as nama_user, u.email as email_user, u.foto as user_foto 
        FROM pemesanan p 
        JOIN destinasi d ON p.destinasi_id = d.id 
        JOIN kategori_wisata k ON d.kategori_id = k.id
        JOIN users u ON p.user_id = u.id 
        WHERE 1=1";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND p.status_bayar = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (p.kode_booking LIKE ? OR p.nama_pemesan LIKE ? OR u.nama LIKE ? OR d.nama_destinasi LIKE ? OR p.no_telp LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

$sql .= " ORDER BY p.id DESC";
$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$pesananList = $stmtList->fetchAll();

$pageTitle = "Manajemen Pemesanan & E-Tiket Wisata";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<main class="admin-main">
  
  <!-- Topbar Luxury -->
  <div class="admin-topbar-luxury">
    <div>
      <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.15rem;">
        <span class="badge-luxury badge-luxury-primary">
          <i class="fa-solid fa-receipt"></i> E-Commerce & Ticketing
        </span>
        <span style="font-size: 0.75rem; color: #64748b;">
          <?= formatTanggalIndo(date('Y-m-d')) ?> • <span id="liveClock"><?= date('H:i') ?> WIB</span>
        </span>
      </div>
      <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Pemesanan & E-Tiket Wisata
      </h1>
      <p style="font-size: 0.78rem; color: #64748b; margin: 0.1rem 0 0 0;">
        Verifikasi pembayaran online, approval barcode gate, dan cetak invoice resmi reservasi wisatawan.
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
      <a href="<?= BASE_URL ?>admin/laporan.php" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-file-invoice-dollar"></i> Rekap Laporan
      </a>
      <a href="<?= BASE_URL ?>admin/pemesanan.php" class="btn btn-secondary btn-sm" title="Segarkan Data">
        <i class="fa-solid fa-rotate"></i>
      </a>
    </div>
  </div>

  <!-- Flash Notification -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible mb-4" style="padding: 0.75rem 1rem; border-radius: 0.65rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.65rem;">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : ($flash['type'] === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-info') ?>" style="font-size: 1.1rem;"></i>
      <div style="flex: 1;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <!-- 4 KPI Stat Cards Row -->
  <div class="grid grid-cols-4 gap-4 mb-4">
    
    <!-- Total Transaksi -->
    <a href="<?= BASE_URL ?>admin/pemesanan.php" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-primary" style="cursor: pointer; border: <?= empty($statusFilter) ? '2px solid #0d9488' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
          <div class="kpi-icon-wrap kpi-icon-primary">
            <i class="fa-solid fa-ticket"></i>
          </div>
          <span class="badge-luxury badge-luxury-primary" style="font-size: 0.68rem;">Semua</span>
        </div>
        <div>
          <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
            Total Pemesanan
          </span>
          <h2 style="font-size: 1.35rem; font-weight: 900; color: #0f172a; margin: 0.15rem 0 0.1rem 0; font-family: 'Outfit', sans-serif;">
            <?= number_format($cntTotal) ?> <span style="font-size: 0.78rem; font-weight: 600; color: #64748b;">Order</span>
          </h2>
        </div>
      </div>
    </a>

    <!-- Pending / Butuh Verifikasi -->
    <a href="<?= BASE_URL ?>admin/pemesanan.php?status=pending" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-warning" style="cursor: pointer; border: <?= $statusFilter === 'pending' ? '2px solid #d97706' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
          <div class="kpi-icon-wrap kpi-icon-warning">
            <i class="fa-solid fa-clock-rotate-left"></i>
          </div>
          <span class="badge-luxury badge-luxury-warning" style="font-size: 0.68rem;">
            <?= $cntPending > 0 ? 'Perlu Aksi' : 'Selesai' ?>
          </span>
        </div>
        <div>
          <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
            Menunggu Approval
          </span>
          <h2 style="font-size: 1.35rem; font-weight: 900; color: <?= $cntPending > 0 ? '#b45309' : '#0f172a' ?>; margin: 0.15rem 0 0.1rem 0; font-family: 'Outfit', sans-serif;">
            <?= number_format($cntPending) ?> <span style="font-size: 0.78rem; font-weight: 600; color: #64748b;">Pending</span>
          </h2>
        </div>
      </div>
    </a>

    <!-- Lunas / Disetujui -->
    <a href="<?= BASE_URL ?>admin/pemesanan.php?status=lunas" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-success" style="cursor: pointer; border: <?= $statusFilter === 'lunas' ? '2px solid #10b981' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
          <div class="kpi-icon-wrap kpi-icon-success">
            <i class="fa-solid fa-circle-check"></i>
          </div>
          <span class="badge-luxury badge-luxury-success" style="font-size: 0.68rem;">Lunas</span>
        </div>
        <div>
          <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
            Omset Terverifikasi
          </span>
          <h2 style="font-size: 1.35rem; font-weight: 900; color: #059669; margin: 0.15rem 0 0.1rem 0; font-family: 'Outfit', sans-serif;">
            <?= formatRupiah($totalOmsetLunas) ?>
          </h2>
        </div>
      </div>
    </a>

    <!-- Batal / Ditolak -->
    <a href="<?= BASE_URL ?>admin/pemesanan.php?status=batal" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-purple" style="cursor: pointer; border: <?= $statusFilter === 'batal' ? '2px solid #ef4444' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
          <div class="kpi-icon-wrap" style="background: #fee2e2; color: #b91c1c;">
            <i class="fa-solid fa-ban"></i>
          </div>
          <span class="badge-luxury badge-luxury-danger" style="font-size: 0.68rem;">Batal</span>
        </div>
        <div>
          <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
            Dibatalkan
          </span>
          <h2 style="font-size: 1.35rem; font-weight: 900; color: #0f172a; margin: 0.15rem 0 0.1rem 0; font-family: 'Outfit', sans-serif;">
            <?= number_format($cntBatal) ?> <span style="font-size: 0.78rem; font-weight: 600; color: #64748b;">Batal</span>
          </h2>
        </div>
      </div>
    </a>

  </div>

  <!-- MODAL / DETAIL TRANSAKSI INSPECTOR (IF VIEW) -->
  <?php if ($detailPesanan): ?>
    <div class="card p-4 shadow-md mb-4 bg-white" style="border-radius: 0.85rem; border: 2px solid #0284c7; background: linear-gradient(180deg, #ffffff 0%, #f0fdfa 100%);">
      
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.65rem; border-bottom: 1.5px dashed #cbd5e1;">
        <div style="display: flex; align-items: center; gap: 0.65rem;">
          <span class="badge-luxury badge-luxury-primary" style="font-size: 0.8rem; font-weight: 800;">
            <i class="fa-solid fa-receipt"></i> #<?= htmlspecialchars($detailPesanan['kode_booking']) ?>
          </span>
          <span style="font-size: 0.78rem; color: #64748b;">
            Dipesan pada: <strong><?= formatTanggalIndo($detailPesanan['created_at']) ?></strong>
          </span>
        </div>
        
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <a href="<?= BASE_URL ?>tiket.php?kode=<?= htmlspecialchars($detailPesanan['kode_booking']) ?>" target="_blank" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-print"></i> Buka E-Ticket QR
          </a>
          <a href="<?= BASE_URL ?>admin/pemesanan.php<?= !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : '' ?>" class="btn btn-secondary btn-sm" title="Tutup Detail">
            <i class="fa-solid fa-xmark"></i> Tutup
          </a>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-4">
        
        <!-- Kolom 1: Profil Pemesan -->
        <div style="background: #ffffff; padding: 0.85rem; border-radius: 0.65rem; border: 1px solid #e2e8f0;">
          <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; display: block; margin-bottom: 0.45rem;">
            <i class="fa-solid fa-user-tag text-primary"></i> Data Pemesan
          </span>
          <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.5rem;">
            <div class="avatar" style="width: 38px; height: 38px; font-size: 0.95rem; background: linear-gradient(135deg, #0d9488, #0284c7); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800;">
              <?= strtoupper(substr($detailPesanan['nama_pemesan'] ?? $detailPesanan['nama_user'], 0, 1)) ?>
            </div>
            <div>
              <strong style="font-size: 0.9rem; color: #0f172a; display: block; line-height: 1.2;">
                <?= htmlspecialchars($detailPesanan['nama_pemesan'] ?? $detailPesanan['nama_user']) ?>
              </strong>
              <span style="font-size: 0.72rem; color: #64748b;">
                Akun: <?= htmlspecialchars($detailPesanan['nama_user']) ?>
              </span>
            </div>
          </div>
          <div style="font-size: 0.75rem; color: #475569; display: flex; flex-direction: column; gap: 0.2rem;">
            <div><i class="fa-solid fa-envelope text-primary"></i> <?= htmlspecialchars($detailPesanan['email_user']) ?></div>
            <div><i class="fa-brands fa-whatsapp text-emerald font-bold"></i> <?= htmlspecialchars($detailPesanan['no_telp'] ?? '-') ?></div>
            <?php if (!empty($detailPesanan['catatan'])): ?>
              <div style="margin-top: 0.35rem; padding: 0.35rem 0.5rem; background: #f8fafc; border-radius: 0.4rem; border: 1px dashed #cbd5e1; font-style: italic;">
                "<?= htmlspecialchars($detailPesanan['catatan']) ?>"
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Kolom 2: Destinasi & Tiket Info -->
        <div style="background: #ffffff; padding: 0.85rem; border-radius: 0.65rem; border: 1px solid #e2e8f0;">
          <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; display: block; margin-bottom: 0.45rem;">
            <i class="fa-solid fa-mountain-sun text-teal"></i> Destinasi & Kuota
          </span>
          <strong style="font-size: 0.95rem; color: #0f172a; display: block; margin-bottom: 0.2rem;">
            <?= htmlspecialchars($detailPesanan['nama_destinasi']) ?>
          </strong>
          <span style="font-size: 0.72rem; color: #64748b; display: block; margin-bottom: 0.5rem;">
            <i class="fa-solid fa-location-dot text-primary"></i> <?= htmlspecialchars($detailPesanan['lokasi']) ?>
          </span>

          <div style="font-size: 0.75rem; color: #334155; display: flex; flex-direction: column; gap: 0.25rem;">
            <div class="flex justify-between">
              <span>Tgl Kunjungan:</span>
              <strong><?= formatTanggalIndo($detailPesanan['tanggal_kunjungan']) ?></strong>
            </div>
            <div class="flex justify-between">
              <span>Tipe & Kuota:</span>
              <strong><?= $detailPesanan['jumlah_tiket'] ?> Orang (<?= ucfirst($detailPesanan['tipe_rombongan']) ?>)</strong>
            </div>
            <div class="flex justify-between" style="border-top: 1px solid #f1f5f9; padding-top: 0.25rem;">
              <span>Total Tagihan:</span>
              <strong style="font-size: 0.95rem; color: #0d9488; font-weight: 900; font-family: 'Outfit', sans-serif;">
                <?= formatRupiah($detailPesanan['total_bayar']) ?>
              </strong>
            </div>
          </div>
        </div>

        <!-- Kolom 3: Bukti & Form Verifikasi Cepat -->
        <div style="background: #ffffff; padding: 0.85rem; border-radius: 0.65rem; border: 1px solid #e2e8f0;">
          <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; display: block; margin-bottom: 0.45rem;">
            <i class="fa-solid fa-shield-halved text-primary"></i> Verifikasi Pembayaran
          </span>

          <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.65rem;">
            <?php if (!empty($detailPesanan['bukti_bayar'])): ?>
              <a href="<?= BASE_URL ?>assets/uploads/bukti/<?= htmlspecialchars($detailPesanan['bukti_bayar']) ?>" target="_blank" title="Klik untuk perbesar bukti" style="display: block; flex-shrink: 0;">
                <img src="<?= BASE_URL ?>assets/uploads/bukti/<?= htmlspecialchars($detailPesanan['bukti_bayar']) ?>" alt="Bukti Bayar" style="width: 75px; height: 55px; object-fit: cover; border-radius: 6px; border: 1.5px solid #0284c7; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
              </a>
              <div style="font-size: 0.72rem; color: #64748b;">
                <span class="badge badge-light mb-1 block" style="font-size: 0.68rem; font-weight: 700;">
                  Metode: <?= htmlspecialchars($detailPesanan['metode_pembayaran'] ?? 'Transfer Bank') ?>
                </span>
                <span class="text-xs text-primary font-bold"><i class="fa-solid fa-magnifying-glass-plus"></i> Lihat Bukti</span>
              </div>
            <?php else: ?>
              <div style="font-size: 0.72rem; color: #64748b; background: #f8fafc; padding: 0.4rem 0.6rem; border-radius: 0.4rem; border: 1px dashed #cbd5e1; flex: 1;">
                <i class="fa-solid fa-qrcode text-primary"></i> Pembayaran QRIS / Loket Resmi Terverifikasi Otomatis
              </div>
            <?php endif; ?>
          </div>

          <!-- Form Update Status Pembayaran -->
          <form action="<?= BASE_URL ?>admin/pemesanan.php" method="POST" style="display: flex; gap: 0.35rem; align-items: center;">
            <input type="hidden" name="pemesanan_id" value="<?= $detailPesanan['id'] ?>">
            <select name="status_bayar" class="form-control" style="font-size: 0.78rem; padding: 0.35rem 0.55rem; font-weight: 700; flex: 1;">
              <option value="pending" <?= $detailPesanan['status_bayar'] === 'pending' ? 'selected' : '' ?>>Pending (Menunggu)</option>
              <option value="lunas" <?= $detailPesanan['status_bayar'] === 'lunas' ? 'selected' : '' ?>>Lunas (Disetujui)</option>
              <option value="batal" <?= $detailPesanan['status_bayar'] === 'batal' ? 'selected' : '' ?>>Batal / Ditolak</option>
            </select>
            <button type="submit" name="update_status" class="btn btn-primary btn-sm" style="font-weight: 700;">
              Simpan
            </button>
          </form>

        </div>

      </div>
    </div>
  <?php endif; ?>

  <!-- Filter Status & Search Bar Card -->
  <div class="card p-3 shadow-sm mb-4 bg-white" style="border-radius: 0.75rem; border: 1px solid #e2e8f0;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
      
      <!-- Filter Status Tabs -->
      <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
        <span style="font-size: 0.72rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-right: 0.25rem;">
          Filter:
        </span>
        <a href="<?= BASE_URL ?>admin/pemesanan.php" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-secondary' ?>">
          Semua (<?= $cntTotal ?>)
        </a>
        <a href="<?= BASE_URL ?>admin/pemesanan.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">
          <i class="fa-solid fa-clock-rotate-left text-amber"></i> Pending (<?= $cntPending ?>)
        </a>
        <a href="<?= BASE_URL ?>admin/pemesanan.php?status=lunas" class="btn btn-sm <?= $statusFilter === 'lunas' ? 'btn-primary' : 'btn-secondary' ?>">
          <i class="fa-solid fa-circle-check text-emerald"></i> Lunas (<?= $cntLunas ?>)
        </a>
        <a href="<?= BASE_URL ?>admin/pemesanan.php?status=batal" class="btn btn-sm <?= $statusFilter === 'batal' ? 'btn-primary' : 'btn-secondary' ?>">
          <i class="fa-solid fa-ban text-danger"></i> Batal (<?= $cntBatal ?>)
        </a>
      </div>

      <!-- Quick Search Form -->
      <form action="<?= BASE_URL ?>admin/pemesanan.php" method="GET" style="display: flex; align-items: center; gap: 0.35rem;">
        <?php if (!empty($statusFilter)): ?>
          <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <?php endif; ?>
        <input type="text" name="q" class="form-control" placeholder="Cari kode booking / nama / destinasi..." value="<?= htmlspecialchars($search) ?>" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; width: 240px;">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>
        <?php if (!empty($search)): ?>
          <a href="<?= BASE_URL ?>admin/pemesanan.php<?= !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : '' ?>" class="btn btn-secondary btn-sm" title="Reset Pencarian">
            <i class="fa-solid fa-xmark"></i>
          </a>
        <?php endif; ?>
      </form>

    </div>
  </div>

  <!-- Main Transactions Table -->
  <div class="card-table-luxury">
    
    <div class="card-table-header">
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <h3 style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0;">
          Data Transaksi Pemesanan Tiket (<?= count($pesananList) ?>)
        </h3>
        <?php if (!empty($search)): ?>
          <span class="badge badge-light text-xs">Pencarian: "<?= htmlspecialchars($search) ?>"</span>
        <?php endif; ?>
      </div>

      <span class="badge-luxury badge-luxury-primary" style="font-size: 0.7rem;">
        <i class="fa-solid fa-shield-halved"></i> E-Ticket QR Code Terverifikasi
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="table-luxury">
        <thead>
          <tr>
            <th style="width: 13%;">Kode Booking</th>
            <th style="width: 20%;">Pemesan & Kontak</th>
            <th style="width: 18%;">Destinasi Wisata</th>
            <th style="width: 13%;">Tgl Kunjungan</th>
            <th style="width: 10%;">Tiket</th>
            <th style="width: 12%;">Total Bayar</th>
            <th style="width: 10%;">Status Bayar</th>
            <th style="width: 4%; text-align: center;">Gate</th>
            <th style="width: 10%; text-align: right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pesananList)): ?>
            <tr>
              <td colspan="9" style="text-align: center; padding: 2rem; color: #94a3b8;">
                <i class="fa-solid fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; color: #cbd5e1;"></i>
                Belum ada data transaksi pemesanan yang sesuai dengan filter.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($pesananList as $p): ?>
              <tr>
                <!-- Kode Booking -->
                <td>
                  <a href="<?= BASE_URL ?>admin/pemesanan.php?action=view&id=<?= $p['id'] ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>" style="text-decoration: none;">
                    <span style="font-family: monospace; font-size: 0.8rem; font-weight: 800; background: #f1f5f9; padding: 0.2rem 0.45rem; border-radius: 0.35rem; border: 1px solid #e2e8f0; color: #0284c7; display: inline-block;">
                      <?= htmlspecialchars($p['kode_booking']) ?>
                    </span>
                  </a>
                </td>

                <!-- Data Pemesan -->
                <td>
                  <strong style="color: #0f172a; font-size: 0.82rem; display: block; line-height: 1.2;">
                    <?= htmlspecialchars($p['nama_pemesan'] ?? $p['nama_user']) ?>
                  </strong>
                  <span style="font-size: 0.7rem; color: #64748b;">
                    <i class="fa-brands fa-whatsapp text-emerald font-bold"></i> <?= htmlspecialchars($p['no_telp'] ?? '-') ?>
                  </span>
                </td>

                <!-- Destinasi Wisata -->
                <td>
                  <strong style="font-size: 0.82rem; color: #1e293b; display: block; line-height: 1.2;">
                    <?= htmlspecialchars($p['nama_destinasi']) ?>
                  </strong>
                  <span style="font-size: 0.68rem; color: #64748b;">
                    <?= htmlspecialchars($p['nama_kategori']) ?>
                  </span>
                </td>

                <!-- Tanggal Kunjungan -->
                <td>
                  <span style="font-size: 0.78rem; color: #334155; font-weight: 600;">
                    <?= formatTanggalIndo($p['tanggal_kunjungan']) ?>
                  </span>
                </td>

                <!-- Jumlah Tiket -->
                <td>
                  <strong style="font-size: 0.82rem; color: #0f172a;">
                    <?= $p['jumlah_tiket'] ?> Org
                  </strong>
                  <span style="font-size: 0.68rem; color: #64748b; display: block; text-transform: capitalize;">
                    (<?= htmlspecialchars($p['tipe_rombongan']) ?>)
                  </span>
                </td>

                <!-- Total Bayar -->
                <td>
                  <strong style="color: #0d9488; font-size: 0.88rem; font-weight: 800; font-family: 'Outfit', sans-serif;">
                    <?= formatRupiah($p['total_bayar']) ?>
                  </strong>
                </td>

                <!-- Status Pembayaran -->
                <td>
                  <?php if ($p['status_bayar'] === 'lunas'): ?>
                    <span class="badge-luxury badge-luxury-success">Lunas</span>
                  <?php elseif ($p['status_bayar'] === 'pending'): ?>
                    <span class="badge-luxury badge-luxury-warning">Pending</span>
                  <?php else: ?>
                    <span class="badge-luxury badge-luxury-danger">Batal</span>
                  <?php endif; ?>
                </td>

                <!-- Status Check-In Gate -->
                <td style="text-align: center;">
                  <?php if ($p['status_kunjungan'] === 'sudah_digunakan'): ?>
                    <span class="badge-luxury badge-luxury-success" title="Sudah divalidasi di gerbang" style="padding: 0.15rem 0.4rem;">
                      <i class="fa-solid fa-check"></i>
                    </span>
                  <?php else: ?>
                    <span style="font-size: 0.68rem; color: #94a3b8;" title="Belum digunakan">-</span>
                  <?php endif; ?>
                </td>

                <!-- Aksi -->
                <td style="text-align: right;">
                  <div style="display: flex; gap: 0.25rem; justify-content: flex-end;">
                    <a href="<?= BASE_URL ?>admin/pemesanan.php?action=view&id=<?= $p['id'] ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>" class="btn btn-secondary btn-sm" title="Lihat Detail & Verifikasi">
                      <i class="fa-solid fa-eye"></i>
                    </a>
                    <a href="<?= BASE_URL ?>tiket.php?kode=<?= htmlspecialchars($p['kode_booking']) ?>" target="_blank" class="btn btn-primary btn-sm" title="Lihat E-Ticket QR">
                      <i class="fa-solid fa-qrcode"></i>
                    </a>
                    <a href="<?= BASE_URL ?>admin/pemesanan.php?action=delete&id=<?= $p['id'] ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>" class="btn btn-danger btn-sm" title="Hapus Transaksi" onclick="return confirm('Apakah Anda yakin ingin menghapus transaksi #<?= htmlspecialchars($p['kode_booking']) ?> ini?')">
                      <i class="fa-solid fa-trash"></i>
                    </a>
                  </div>
                </td>

              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
