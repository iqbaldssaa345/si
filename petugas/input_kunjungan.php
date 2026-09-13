<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('petugas');

$destinasiList = $pdo->query("SELECT id, nama_destinasi, lokasi, harga_tiket FROM destinasi WHERE status = 'buka' ORDER BY nama_destinasi ASC")->fetchAll();

$msg = '';
$msgType = '';
$petugasId = $_SESSION['user_id'];
$today = date('Y-m-d');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $destinasiId = (int)($_POST['destinasi_id'] ?? 0);
    $tglKunjungan = $_POST['tanggal_kunjungan'] ?? date('Y-m-d');
    $jumlahOrang = (int)($_POST['jumlah_pengunjung'] ?? 1);
    $tipeKunjungan = $_POST['jenis_kunjungan'] ?? 'individu';
    $keterangan = trim($_POST['keterangan'] ?? '');

    // Dapatkan nama hari bahasa Indonesia otomatis
    $hari = getNamaHariIndo($tglKunjungan);

    if ($destinasiId <= 0) {
        $msg = "Silakan pilih destinasi objek wisata tujuan.";
        $msgType = "danger";
    } elseif ($jumlahOrang < 1) {
        $msg = "Jumlah pengunjung minimal 1 orang.";
        $msgType = "danger";
    } else {
        $insert = $pdo->prepare("INSERT INTO presensi_kunjungan (petugas_id, destinasi_id, tanggal_kunjungan, hari, jumlah_pengunjung, jenis_kunjungan, keterangan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($insert->execute([$petugasId, $destinasiId, $tglKunjungan, $hari, $jumlahOrang, $tipeKunjungan, $keterangan])) {
            setFlash('success', 'Presensi kunjungan <strong>' . number_format($jumlahOrang) . ' wisatawan</strong> berhasil disimpan ke dalam rekapitulasi!');
            header("Location: " . BASE_URL . "petugas/input_kunjungan.php");
            exit;
        } else {
            $msg = "Gagal menyimpan data kunjungan. Silakan coba kembali.";
            $msgType = "danger";
        }
    }
}

// Statistik Petugas Hari Ini
$stmtHariIni = $pdo->prepare("SELECT COALESCE(SUM(jumlah_pengunjung), 0) as total_pengunjung, COUNT(id) as total_entri 
                              FROM presensi_kunjungan 
                              WHERE tanggal_kunjungan = ? AND petugas_id = ?");
$stmtHariIni->execute([$today, $petugasId]);
$statHariIni = $stmtHariIni->fetch();

// 5 Entri Terakhir Petugas Hari Ini
$stmtRecentToday = $pdo->prepare("SELECT k.*, d.nama_destinasi, d.lokasi 
                                  FROM presensi_kunjungan k 
                                  JOIN destinasi d ON k.destinasi_id = d.id 
                                  WHERE k.petugas_id = ? AND k.tanggal_kunjungan = ? 
                                  ORDER BY k.id DESC LIMIT 5");
$stmtRecentToday->execute([$petugasId, $today]);
$recentEntries = $stmtRecentToday->fetchAll();

$pageTitle = "Input Presensi Kunjungan Loket";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<main class="admin-main">
  
  <!-- Topbar Luxury -->
  <div class="admin-topbar-luxury">
    <div>
      <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
        <span class="badge-luxury badge-luxury-warning">
          <i class="fa-solid fa-pen-nib"></i> Entri Presensi Langsung
        </span>
        <span style="font-size: 0.8rem; color: #64748b;">
          Loket Gerbang Pintu Masuk
        </span>
      </div>
      <h1 style="font-size: 1.45rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Input Kunjungan Wisatawan (Loket)
      </h1>
      <p style="font-size: 0.82rem; color: #64748b; margin: 0.15rem 0 0 0;">
        Formulir pencatatan wisatawan langsung bagi pengunjung yang membeli tiket fisik di loket gerbang.
      </p>
    </div>

    <div style="display: flex; gap: 0.5rem;">
      <a href="<?= BASE_URL ?>petugas/riwayat_input.php" class="btn btn-secondary btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; font-weight: 700; border-radius: 0.65rem;">
        <i class="fa-solid fa-clock-rotate-left"></i> Lihat Riwayat Input Anda
      </a>
    </div>
  </div>

  <!-- Flash Notification -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-4" style="border-radius: 0.85rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.35rem;"></i>
      <div style="font-size: 0.9rem; line-height: 1.4;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mb-4" style="border-radius: 0.85rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
      <i class="fa-solid fa-circle-exclamation" style="font-size: 1.35rem;"></i>
      <div style="font-size: 0.9rem; line-height: 1.4;"><?= $msg ?></div>
    </div>
  <?php endif; ?>

  <!-- Main 12-Column Layout -->
  <div class="grid grid-cols-12 gap-5 mb-5">
    
    <!-- Left Column: Input Form Card (7 cols) -->
    <div class="col-span-7" style="grid-column: span 7 / span 7;">
      <div class="card p-5 bg-white shadow-sm" style="border-radius: 1.15rem; border: 1.5px solid #ccfbf1;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
          <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-clipboard-user text-teal-600"></i> Formulir Presensi Masuk
          </h3>
          <span class="badge-luxury badge-luxury-primary" style="font-size: 0.7rem;">Shift: <?= date('d M Y') ?></span>
        </div>

        <form action="<?= BASE_URL ?>petugas/input_kunjungan.php" method="POST" id="mainPresensiForm">
          
          <!-- Destinasi Wisata Selection -->
          <div class="form-group mb-4">
            <label class="form-label font-bold text-xs uppercase text-muted" style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
              <span>Destinasi Objek Wisata <span class="text-danger">*</span></span>
              <span style="font-weight: normal; font-size: 0.72rem; color: #94a3b8;">Hanya destinasi berstatus buka</span>
            </label>
            <div style="position: relative;">
              <i class="fa-solid fa-mountain-sun" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #0d9488; font-size: 1.1rem;"></i>
              <select name="destinasi_id" id="selDestinasi" class="form-control" style="padding-left: 2.75rem; font-weight: 700; font-size: 0.9rem; border-radius: 0.75rem; height: 44px;" required onchange="updateLiveTicketSlip()">
                <option value="">-- Pilih Destinasi Wisata --</option>
                <?php foreach ($destinasiList as $d): ?>
                  <option value="<?= $d['id'] ?>" data-name="<?= htmlspecialchars($d['nama_destinasi']) ?>" data-lokasi="<?= htmlspecialchars($d['lokasi']) ?>" data-harga="<?= $d['harga_tiket'] ?>">
                    <?= htmlspecialchars($d['nama_destinasi']) ?> — <?= htmlspecialchars($d['lokasi']) ?> (Rp <?= number_format($d['harga_tiket'], 0, ',', '.') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Tanggal & Hari Kunjungan -->
          <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-muted" style="display: block; margin-bottom: 0.35rem;">Tanggal Kunjungan <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_kunjungan" id="inputTgl" class="form-control" style="border-radius: 0.65rem; font-weight: 600; height: 40px; font-size: 0.85rem;" value="<?= date('Y-m-d') ?>" required onchange="handleDateChange(this.value)">
            </div>

            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-muted" style="display: block; margin-bottom: 0.35rem;">Hari Operasional</label>
              <input type="text" id="displayHari" class="form-control" style="border-radius: 0.65rem; font-weight: 700; background: #f8fafc; color: #0f766e; height: 40px; font-size: 0.85rem;" value="<?= getNamaHariIndo(date('Y-m-d')) ?>" readonly>
            </div>
          </div>

          <!-- Jumlah Pengunjung & Quick Add Stepper -->
          <div class="form-group mb-4">
            <label class="form-label font-bold text-xs uppercase text-muted" style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
              <span>Jumlah Wisatawan Masuk <span class="text-danger">*</span></span>
              <span style="font-weight: normal; font-size: 0.72rem; color: #94a3b8;">Gunakan tombol cepat</span>
            </label>
            
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
              <button type="button" class="btn btn-secondary" onclick="adjustCount(-1)" style="font-size: 1.1rem; font-weight: 800; width: 44px; height: 44px; border-radius: 0.65rem;">-</button>
              <input type="number" name="jumlah_pengunjung" id="inputJml" class="form-control text-center" value="1" min="1" style="font-weight: 900; font-size: 1.35rem; font-family: 'Outfit', sans-serif; color: #0f172a; height: 44px; border-radius: 0.65rem;" required oninput="handleCountChange(this.value)">
              <button type="button" class="btn btn-secondary" onclick="adjustCount(1)" style="font-size: 1.1rem; font-weight: 800; width: 44px; height: 44px; border-radius: 0.65rem;">+</button>
            </div>

            <!-- Quick Increments -->
            <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
              <button type="button" class="btn btn-light btn-xs" onclick="addCount(5)" style="font-weight: 700; border: 1px solid #cbd5e1; border-radius: 0.35rem; font-size: 0.72rem; padding: 0.2rem 0.55rem;">+5 Org</button>
              <button type="button" class="btn btn-light btn-xs" onclick="addCount(10)" style="font-weight: 700; border: 1px solid #cbd5e1; border-radius: 0.35rem; font-size: 0.72rem; padding: 0.2rem 0.55rem;">+10 Org</button>
              <button type="button" class="btn btn-light btn-xs" onclick="addCount(25)" style="font-weight: 700; border: 1px solid #cbd5e1; border-radius: 0.35rem; font-size: 0.72rem; padding: 0.2rem 0.55rem;">+25 Org</button>
              <button type="button" class="btn btn-light btn-xs" onclick="addCount(50)" style="font-weight: 700; border: 1px solid #cbd5e1; border-radius: 0.35rem; font-size: 0.72rem; padding: 0.2rem 0.55rem;">+50 Org</button>
              <button type="button" class="btn btn-light btn-xs" onclick="addCount(100)" style="font-weight: 700; border: 1px solid #cbd5e1; border-radius: 0.35rem; font-size: 0.72rem; padding: 0.2rem 0.55rem;">+100 Org</button>
            </div>
          </div>

          <!-- Jenis Kunjungan (Individu vs Rombongan) -->
          <div class="form-group mb-4">
            <label class="form-label font-bold text-xs uppercase text-muted" style="display: block; margin-bottom: 0.35rem;">Kategori Kunjungan</label>
            <div class="grid grid-cols-2 gap-3">
              <label style="border: 2px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem; display: flex; align-items: center; gap: 0.6rem; cursor: pointer; transition: all 0.2s ease;" id="labelIndividu" onclick="selectJenis('individu')">
                <input type="radio" name="jenis_kunjungan" id="radioIndividu" value="individu" checked onchange="updateLiveTicketSlip()" style="accent-color: #0d9488;">
                <div>
                  <strong style="display: block; font-size: 0.85rem; color: #0f172a;"><i class="fa-solid fa-user text-teal-600"></i> Individu / Sendiri</strong>
                  <span style="font-size: 0.7rem; color: #64748b;">Pengunjung perorangan</span>
                </div>
              </label>

              <label style="border: 2px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem; display: flex; align-items: center; gap: 0.6rem; cursor: pointer; transition: all 0.2s ease;" id="labelRombongan" onclick="selectJenis('rombongan')">
                <input type="radio" name="jenis_kunjungan" id="radioRombongan" value="rombongan" onchange="updateLiveTicketSlip()" style="accent-color: #0d9488;">
                <div>
                  <strong style="display: block; font-size: 0.85rem; color: #0f172a;"><i class="fa-solid fa-people-group text-amber-500"></i> Rombongan / Grup</strong>
                  <span style="font-size: 0.7rem; color: #64748b;">Tour Bus / Komunitas</span>
                </div>
              </label>
            </div>
          </div>

          <!-- Keterangan / Catatan Tambahan -->
          <div class="form-group mb-5">
            <label class="form-label font-bold text-xs uppercase text-muted" style="display: block; margin-bottom: 0.35rem;">Catatan Loket (Opsional)</label>
            <input type="text" name="keterangan" id="inputKet" class="form-control" placeholder="Cth: Rombongan Bus Pariwisata Surabaya, Loket Pintu Utama..." style="border-radius: 0.65rem; height: 40px; font-size: 0.85rem;" oninput="updateLiveTicketSlip()">
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg btn-luxury-pulse" style="font-weight: 800; border-radius: 0.75rem; padding: 0.85rem; box-shadow: 0 4px 14px rgba(13, 148, 136, 0.35); font-size: 1rem; width: 100%;">
            <i class="fa-solid fa-check-to-slot"></i> Simpan Presensi Kunjungan
          </button>
        </form>
      </div>
    </div>

    <!-- Right Column: Live Interactive Ticket Slip Preview (5 cols) -->
    <div class="col-span-5" style="grid-column: span 5 / span 5;">
      
      <!-- Live Slip Card -->
      <div class="ticket-pass p-5 bg-white mb-4" style="border: 2px solid #99f6e4;">
        <div style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); margin: -1.25rem -1.25rem 1rem -1.25rem; padding: 1rem 1.25rem; color: white;">
          <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #99f6e4;">
            PREVIEW TIKET PRESENSI LOKET
          </span>
          <h4 id="previewDestNama" style="font-size: 1.15rem; font-weight: 900; margin: 0.15rem 0 0 0; color: #ffffff;">
            Pilih Destinasi Wisata
          </h4>
          <span id="previewDestLokasi" style="font-size: 0.72rem; color: #ccfbf1;">
            Lokasi Belum Dipilih
          </span>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.65rem;">
          <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding-bottom: 0.4rem;">
            <span style="font-size: 0.75rem; color: #64748b;">Tanggal & Hari:</span>
            <strong id="previewTgl" style="font-size: 0.8rem; color: #0f172a;"><?= formatTanggalIndo(date('Y-m-d')) ?> (<?= getNamaHariIndo(date('Y-m-d')) ?>)</strong>
          </div>

          <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding-bottom: 0.4rem;">
            <span style="font-size: 0.75rem; color: #64748b;">Kategori:</span>
            <span id="previewJenis" class="badge-luxury badge-luxury-primary" style="font-size: 0.68rem;">Individu</span>
          </div>

          <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding-bottom: 0.4rem; align-items: center;">
            <span style="font-size: 0.75rem; color: #64748b;">Wisatawan Masuk:</span>
            <strong id="previewJml" style="font-size: 1.35rem; color: #0d9488; font-weight: 900; font-family: 'Outfit', sans-serif;">1 Orang</strong>
          </div>

          <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding-bottom: 0.4rem; align-items: center;">
            <span style="font-size: 0.75rem; color: #64748b;">Estimasi Nilai Tiket:</span>
            <strong id="previewEstimasi" style="font-size: 0.95rem; color: #0284c7; font-weight: 800;">Rp 0</strong>
          </div>

          <div style="display: flex; justify-content: space-between; padding-top: 0.2rem;">
            <span style="font-size: 0.75rem; color: #64748b;">Petugas Loket:</span>
            <strong style="font-size: 0.8rem; color: #334155;"><?= htmlspecialchars($_SESSION['user_nama']) ?></strong>
          </div>
        </div>

        <div style="background: #f8fafc; border-radius: 0.65rem; padding: 0.6rem 0.75rem; margin-top: 0.85rem; border: 1px solid #e2e8f0;">
          <span style="font-size: 0.7rem; color: #64748b; font-weight: 700; display: block; margin-bottom: 0.15rem;">Catatan:</span>
          <span id="previewKet" style="font-size: 0.78rem; color: #334155; font-style: italic;">Tidak ada catatan khusus.</span>
        </div>
      </div>

      <!-- Shift Summary Mini Card -->
      <div class="card p-4 bg-white shadow-sm" style="border-radius: 1rem; border: 1px solid #e2e8f0;">
        <h4 style="font-size: 0.85rem; font-weight: 800; color: #0f172a; margin: 0 0 0.65rem 0; display: flex; align-items: center; gap: 0.35rem;">
          <i class="fa-solid fa-chart-pie text-teal-600"></i> Rekap Shift Anda Hari Ini
        </h4>
        <div class="grid grid-cols-2 gap-3 text-center">
          <div style="background: #ccfbf1; padding: 0.75rem; border-radius: 0.65rem;">
            <span style="font-size: 0.68rem; font-weight: 700; color: #0f766e; text-transform: uppercase;">Total Wisatawan</span>
            <div style="font-size: 1.25rem; font-weight: 900; color: #0f766e; font-family: 'Outfit', sans-serif;">
              <?= number_format($statHariIni['total_pengunjung']) ?>
            </div>
          </div>
          <div style="background: #e0f2fe; padding: 0.75rem; border-radius: 0.65rem;">
            <span style="font-size: 0.68rem; font-weight: 700; color: #0369a1; text-transform: uppercase;">Entri Disimpan</span>
            <div style="font-size: 1.25rem; font-weight: 900; color: #0369a1; font-family: 'Outfit', sans-serif;">
              <?= number_format($statHariIni['total_entri']) ?>
            </div>
          </div>
        </div>
      </div>

    </div>

  </div>

  <!-- Today's Recent Entries Table (100% Width) -->
  <div class="card-table-luxury">
    <div style="padding: 1.15rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #ffffff;">
      <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.4rem;">
        <i class="fa-solid fa-list-check text-teal-600"></i> Entri Presensi yang Anda Simpan Hari Ini
      </h3>
      <a href="<?= BASE_URL ?>petugas/riwayat_input.php" class="btn btn-secondary btn-xs" style="font-weight: 700; border-radius: 0.5rem;">
        Lihat Semua
      </a>
    </div>

    <div style="overflow-x: auto; width: 100%;">
      <table class="table-luxury" style="width: 100%;">
        <thead>
          <tr>
            <th style="width: 12%;">Waktu</th>
            <th style="width: 32%;">Destinasi</th>
            <th style="width: 16%; text-align: center;">Jumlah Masuk</th>
            <th style="width: 15%;">Kategori</th>
            <th style="width: 25%;">Catatan Loket</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentEntries)): ?>
            <tr>
              <td colspan="5" style="text-align: center; padding: 2rem 1rem; color: #94a3b8;">
                Belum ada entri kunjungan yang disimpan pada shift ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentEntries as $re): ?>
              <tr>
                <td>
                  <strong style="color: #0f172a; font-size: 0.85rem;"><?= date('H:i', strtotime($re['created_at'])) ?> WIB</strong>
                </td>
                <td>
                  <strong style="color: #0f172a; font-size: 0.88rem;"><?= htmlspecialchars($re['nama_destinasi']) ?></strong>
                  <span style="font-size: 0.72rem; color: #64748b; display: block;"><?= htmlspecialchars($re['lokasi']) ?></span>
                </td>
                <td style="text-align: center;">
                  <span style="background: #ccfbf1; color: #0f766e; font-weight: 800; font-size: 0.9rem; padding: 0.2rem 0.65rem; border-radius: 9999px;">
                    <?= number_format($re['jumlah_pengunjung']) ?> Orang
                  </span>
                </td>
                <td>
                  <?php if ($re['jenis_kunjungan'] === 'rombongan'): ?>
                    <span class="badge-luxury badge-luxury-warning" style="font-size: 0.7rem;">Rombongan</span>
                  <?php else: ?>
                    <span class="badge-luxury badge-luxury-primary" style="font-size: 0.7rem;">Individu</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="font-size: 0.78rem; color: #475569;"><?= htmlspecialchars($re['keterangan'] ?? '-') ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<script>
const namaHariMap = {
  0: 'Minggu', 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu'
};

function handleDateChange(dateStr) {
  if (!dateStr) return;
  const d = new Date(dateStr + 'T00:00:00');
  const dayName = namaHariMap[d.getDay()] || 'Senin';
  document.getElementById('displayHari').value = dayName;
  updateLiveTicketSlip();
}

function adjustCount(delta) {
  const input = document.getElementById('inputJml');
  let val = parseInt(input.value) || 1;
  val = Math.max(1, val + delta);
  input.value = val;
  handleCountChange(val);
}

function addCount(amount) {
  const input = document.getElementById('inputJml');
  let val = parseInt(input.value) || 0;
  val += amount;
  input.value = val;
  handleCountChange(val);
}

function handleCountChange(count) {
  const cnt = parseInt(count) || 1;
  if (cnt >= 10) {
    selectJenis('rombongan');
  } else {
    selectJenis('individu');
  }
  updateLiveTicketSlip();
}

function selectJenis(type) {
  const radioIndividu = document.getElementById('radioIndividu');
  const radioRombongan = document.getElementById('radioRombongan');
  const labelIndividu = document.getElementById('labelIndividu');
  const labelRombongan = document.getElementById('labelRombongan');

  if (type === 'rombongan') {
    radioRombongan.checked = true;
    labelRombongan.style.borderColor = '#f59e0b';
    labelRombongan.style.background = '#fef3c7';
    labelIndividu.style.borderColor = '#e2e8f0';
    labelIndividu.style.background = '#ffffff';
  } else {
    radioIndividu.checked = true;
    labelIndividu.style.borderColor = '#0d9488';
    labelIndividu.style.background = '#ccfbf1';
    labelRombongan.style.borderColor = '#e2e8f0';
    labelRombongan.style.background = '#ffffff';
  }
  updateLiveTicketSlip();
}

function updateLiveTicketSlip() {
  const sel = document.getElementById('selDestinasi');
  const opt = sel.options[sel.selectedIndex];
  
  const destName = opt && opt.value ? opt.getAttribute('data-name') : 'Pilih Destinasi Wisata';
  const destLokasi = opt && opt.value ? opt.getAttribute('data-lokasi') : 'Lokasi Belum Dipilih';
  const harga = opt && opt.value ? parseFloat(opt.getAttribute('data-harga')) || 0 : 0;
  
  const tgl = document.getElementById('inputTgl').value;
  const hari = document.getElementById('displayHari').value;
  const jml = parseInt(document.getElementById('inputJml').value) || 1;
  const isRombongan = document.getElementById('radioRombongan').checked;
  const ket = document.getElementById('inputKet').value.trim();

  document.getElementById('previewDestNama').innerText = destName;
  document.getElementById('previewDestLokasi').innerText = destLokasi;
  document.getElementById('previewTgl').innerText = tgl + ' (' + hari + ')';
  document.getElementById('previewJml').innerText = jml.toLocaleString('id-ID') + ' Orang';
  
  const jenisBadge = document.getElementById('previewJenis');
  if (isRombongan) {
    jenisBadge.className = 'badge-luxury badge-luxury-warning';
    jenisBadge.innerText = 'Rombongan';
  } else {
    jenisBadge.className = 'badge-luxury badge-luxury-primary';
    jenisBadge.innerText = 'Individu';
  }

  const totalEstimasi = harga * jml;
  document.getElementById('previewEstimasi').innerText = 'Rp ' + totalEstimasi.toLocaleString('id-ID');
  document.getElementById('previewKet').innerText = ket ? ket : 'Tidak ada catatan khusus.';
}

// Initial setup
selectJenis('individu');
updateLiveTicketSlip();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
