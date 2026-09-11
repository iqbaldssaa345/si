<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('admin');

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

// Update Status Pembayaran
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_status'])) {
    $pemesananId = (int)$_POST['pemesanan_id'];
    $newStatus = $_POST['status_bayar'];

    $upd = $pdo->prepare("UPDATE pemesanan SET status_bayar = ? WHERE id = ?");
    $upd->execute([$newStatus, $pemesananId]);

    // Catat log jika lunas
    if ($newStatus === 'lunas') {
        $stmtP = $pdo->prepare("SELECT total_bayar, metode_pembayaran FROM pemesanan WHERE id = ?");
        $stmtP->execute([$pemesananId]);
        $rowP = $stmtP->fetch();
        if ($rowP) {
            $log = $pdo->prepare("INSERT INTO transaksi_log (pemesanan_id, nominal, metode, status, waktu_bayar, catatan) VALUES (?, ?, ?, 'LUNAS', NOW(), 'Diverifikasi oleh Administrator')");
            $log->execute([$pemesananId, $rowP['total_bayar'], $rowP['metode_pembayaran'] ?? 'Transfer']);
        }
    }

    setFlash('success', 'Status pembayaran pemesanan berhasil diperbarui!');
    header("Location: " . BASE_URL . "admin/pemesanan.php");
    exit;
}

// Hapus Pemesanan
if ($action === 'delete' && $id > 0) {
    $del = $pdo->prepare("DELETE FROM pemesanan WHERE id = ?");
    $del->execute([$id]);
    setFlash('success', 'Data pemesanan tiket berhasil dihapus.');
    header("Location: " . BASE_URL . "admin/pemesanan.php");
    exit;
}

// Detail Pemesanan jika id diberikan
$detailPesanan = null;
if ($id > 0 && $action === 'view') {
    $stmt = $pdo->prepare("SELECT p.*, d.nama_destinasi, d.lokasi, u.nama as nama_user, u.email as email_user 
                           FROM pemesanan p 
                           JOIN destinasi d ON p.destinasi_id = d.id 
                           JOIN users u ON p.user_id = u.id 
                           WHERE p.id = ? LIMIT 1");
    $stmt->execute([$id]);
    $detailPesanan = $stmt->fetch();
}

// Build query list
$sql = "SELECT p.*, d.nama_destinasi, u.nama as nama_user 
        FROM pemesanan p 
        JOIN destinasi d ON p.destinasi_id = d.id 
        JOIN users u ON p.user_id = u.id";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " WHERE p.status_bayar = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY p.id DESC";
$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$pesananList = $stmtList->fetchAll();

$pageTitle = "Manajemen Pemesanan & Tiket";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<main class="admin-main">
  
  <div class="admin-topbar-luxury">
    <div>
      <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0;">Pemesanan & E-Tiket Wisata</h1>
      <p style="font-size: 0.85rem; color: #64748b; margin: 0.2rem 0 0 0;">Verifikasi pembayaran online, approval tiket, dan pencetakan invoice resmi</p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-6" style="border-radius: 0.75rem;">
      <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flash['message']) ?>
    </div>
  <?php endif; ?>

  <!-- Modal / Detail Popup jika action=view -->
  <?php if ($detailPesanan): ?>
    <div class="card p-6 shadow-lg mb-8 bg-white" style="border-radius: 1.25rem; border: 2px solid #0284c7;">
      <div class="flex items-center justify-between mb-4 pb-3 border-b">
        <h3 class="font-bold text-dark text-lg">Detail Transaksi #<?= htmlspecialchars($detailPesanan['kode_booking']) ?></h3>
        <a href="<?= BASE_URL ?>admin/pemesanan.php" class="btn btn-secondary btn-sm">Tutup</a>
      </div>

      <div class="grid grid-cols-3 gap-6">
        <div>
          <span class="text-xs text-muted block uppercase font-bold">Data Pengunjung</span>
          <strong class="text-dark"><?= htmlspecialchars($detailPesanan['nama_pemesan'] ?? $detailPesanan['nama_user']) ?></strong>
          <div class="text-xs text-muted">Akun: <?= htmlspecialchars($detailPesanan['nama_user']) ?> (<?= htmlspecialchars($detailPesanan['email_user']) ?>)</div>
          <div class="text-xs text-muted">Telp: <?= htmlspecialchars($detailPesanan['no_telp'] ?? '-') ?></div>
          <div class="text-xs text-muted mt-2">Catatan: <?= htmlspecialchars($detailPesanan['catatan'] ?? '-') ?></div>
        </div>

        <div>
          <span class="text-xs text-muted block uppercase font-bold">Destinasi & Tiket</span>
          <strong class="text-dark"><?= htmlspecialchars($detailPesanan['nama_destinasi']) ?></strong>
          <div class="text-xs text-muted">Kunjungan: <strong><?= formatTanggalIndo($detailPesanan['tanggal_kunjungan']) ?></strong></div>
          <div class="text-xs text-muted">Jumlah: <strong><?= $detailPesanan['jumlah_tiket'] ?> Orang</strong> (<?= ucfirst($detailPesanan['tipe_rombongan']) ?>)</div>
          <div class="text-sm text-primary font-bold mt-1">Total: <?= formatRupiah($detailPesanan['total_bayar']) ?></div>
        </div>

        <div>
          <span class="text-xs text-muted block uppercase font-bold">Bukti Bayar</span>
          <?php if (!empty($detailPesanan['bukti_bayar'])): ?>
            <a href="<?= BASE_URL ?>assets/uploads/bukti/<?= htmlspecialchars($detailPesanan['bukti_bayar']) ?>" target="_blank" class="block mt-1">
              <img src="<?= BASE_URL ?>assets/uploads/bukti/<?= htmlspecialchars($detailPesanan['bukti_bayar']) ?>" alt="Bukti" style="max-height: 100px; border-radius: 6px; border: 1px solid var(--slate-300);">
            </a>
          <?php else: ?>
            <span class="text-xs text-muted block mt-1">Tidak ada file bukti (Pembayaran Instan / QRIS)</span>
          <?php endif; ?>

          <form action="<?= BASE_URL ?>admin/pemesanan.php" method="POST" class="mt-4 flex items-center gap-2">
            <input type="hidden" name="pemesanan_id" value="<?= $detailPesanan['id'] ?>">
            <select name="status_bayar" class="form-control text-xs">
              <option value="pending" <?= $detailPesanan['status_bayar'] === 'pending' ? 'selected' : '' ?>>Pending / Menunggu Bayar</option>
              <option value="lunas" <?= $detailPesanan['status_bayar'] === 'lunas' ? 'selected' : '' ?>>Lunas (Disetujui)</option>
              <option value="batal" <?= $detailPesanan['status_bayar'] === 'batal' ? 'selected' : '' ?>>Batal / Ditolak</option>
            </select>
            <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update</button>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Filter Status Tabs -->
  <div class="card p-4 shadow-sm mb-6 bg-white" style="border-radius: 1rem; border: 1px solid #e2e8f0;">
    <div class="flex items-center gap-2" style="flex-wrap: wrap;">
      <span class="text-xs font-bold text-muted uppercase mr-2">Filter Status:</span>
      <a href="<?= BASE_URL ?>admin/pemesanan.php" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-secondary' ?>">Semua</a>
      <a href="<?= BASE_URL ?>admin/pemesanan.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">Pending</a>
      <a href="<?= BASE_URL ?>admin/pemesanan.php?status=lunas" class="btn btn-sm <?= $statusFilter === 'lunas' ? 'btn-primary' : 'btn-secondary' ?>">Lunas</a>
      <a href="<?= BASE_URL ?>admin/pemesanan.php?status=batal" class="btn btn-sm <?= $statusFilter === 'batal' ? 'btn-primary' : 'btn-secondary' ?>">Batal</a>
    </div>
  </div>

  <!-- Table -->
  <div class="card-table-luxury">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
      <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0;">
        Daftar Seluruh Transaksi Pemesanan (<?= count($pesananList) ?>)
      </h3>
      <span class="badge-luxury badge-luxury-primary" style="font-size: 0.75rem;">
        <i class="fa-solid fa-ticket"></i> E-Ticket Transaksi
      </span>
    </div>
    <div class="overflow-x-auto">
      <table class="table-luxury">
        <thead>
          <tr>
            <th>Kode Booking</th>
            <th>Pemesan</th>
            <th>Destinasi</th>
            <th>Tgl Kunjungan</th>
            <th>Tiket</th>
            <th>Total Bayar</th>
            <th>Status Pembayaran</th>
            <th>Check-In</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pesananList as $p): ?>
            <tr>
              <td class="font-mono font-bold text-xs"><?= htmlspecialchars($p['kode_booking']) ?></td>
              <td>
                <strong class="text-dark text-sm block"><?= htmlspecialchars($p['nama_pemesan'] ?? $p['nama_user']) ?></strong>
                <span class="text-xs text-muted"><?= htmlspecialchars($p['no_telp'] ?? '-') ?></span>
              </td>
              <td><?= htmlspecialchars($p['nama_destinasi']) ?></td>
              <td class="text-xs"><?= formatTanggalIndo($p['tanggal_kunjungan']) ?></td>
              <td><?= $p['jumlah_tiket'] ?> (<?= ucfirst($p['tipe_rombongan']) ?>)</td>
              <td class="font-bold text-primary"><?= formatRupiah($p['total_bayar']) ?></td>
              <td>
                <?php if ($p['status_bayar'] === 'lunas'): ?>
                  <span class="badge badge-success">Lunas</span>
                <?php elseif ($p['status_bayar'] === 'pending'): ?>
                  <span class="badge badge-warning">Pending</span>
                <?php else: ?>
                  <span class="badge badge-danger">Batal</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($p['status_kunjungan'] === 'sudah_digunakan'): ?>
                  <span class="badge badge-success text-xs"><i class="fa-solid fa-check"></i> Masuk</span>
                <?php else: ?>
                  <span class="text-xs text-muted">Belum</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="flex gap-2">
                  <a href="<?= BASE_URL ?>admin/pemesanan.php?action=view&id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Lihat & Verifikasi">
                    <i class="fa-solid fa-eye"></i>
                  </a>
                  <a href="<?= BASE_URL ?>tiket.php?kode=<?= $p['kode_booking'] ?>" target="_blank" class="btn btn-info btn-sm" title="Lihat E-Ticket">
                    <i class="fa-solid fa-ticket"></i>
                  </a>
                  <a href="<?= BASE_URL ?>admin/pemesanan.php?action=delete&id=<?= $p['id'] ?>" class="btn btn-danger btn-sm" title="Hapus" onclick="return confirm('Hapus pemesanan tiket ini?')">
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

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
