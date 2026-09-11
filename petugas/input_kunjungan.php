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
      <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Input Kunjungan Wisatawan (Loket)
      </h1>
      <p style="font-size: 0.85rem; color: #64748b; margin: 0.2rem 0 0 0;">
        Formulir pencatatan wisatawan langsung bagi pengunjung yang membeli tiket fisik di loket gerbang.
      </p>
    </div>

    <div style="display: flex; gap: 0.5rem;">
      <a href="<?= BASE_URL ?>petugas/riwayat_input.php" class="btn btn-secondary btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; font-weight: 700;">
        <i class="fa-solid fa-clock-rotate-left"></i> Lihat Riwayat Input Anda
      </a>
    </div>
  </div>

  <!-- Flash Notification -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-6" style="border-radius: 0.85rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.35rem;"></i>
      <div style="font-size: 0.92rem; line-height: 1.4;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mb-6" style="border-radius: 0.85rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
      <i class="fa-solid fa-circle-exclamation" style="font-size: 1.35rem;"></i>
      <div style="font-size: 0.92rem; line-height: 1.4;"><?= $msg ?></div>
    </div>
  <?php endif; ?>

  <!-- Main 12-Column Layout -->
  <div class="grid grid-cols-12 gap-6 mb-7">
    
    <!-- Left Column: Input Form Card (8 cols) -->
    <div class="col-span-8" style="grid-column: span 8 / span 8;">
      <div class="card p-6 bg-white shadow-sm" style="border-radius: 1.25rem; border: 1.5px solid #ccfbf1;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid #f1f5f9;">
          <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-clipboard-user text-primary"></i> Formulir Presensi Masuk
          </h3>
          <span class="badge-luxury badge-luxury-primary" style="font-size: 0.72rem;">Shift: <?= date('d M Y') ?></span>
        </div>

        <form action="<?= BASE_URL ?>petugas/input_kunjungan.php" method="POST" id="mainPresensiForm">
          
          <!-- Destinasi Wisata Selection -->
          <div class="form-group mb-5">
            <label class="form-label font-bold text-xs uppercase text-muted" style="display: flex; justify-content: space-between;">
              <span>Destinasi Objek Wisata <span class="text-danger">*</span></span>
              <span style="font-weight: normal; font-size: 0.72rem; color: #94a3b8;">Hanya destinasi berstatus buka</span>
            </label>
            <div style="position: relative;">
              <i class="fa-solid fa-mountain-sun" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #0d9488; font-size: 1.1rem;"></i>
              <select name="destinasi_id" id="selDestinasi" class="form-control" style="padding-left: 2.75rem; font-weight: 700; font-size: 0.95rem; border-radius: 0.75rem;" required onchange="updateLiveTicketSlip()">
                <option value="">-- Pilih Destinasi Wisata --</option>
                <?php foreach ($destinasiList as $d): ?>
                  <option value="<?= $d['id'] ?>" data-name="<?= htmlspecialchars($d['nama_destinasi']) ?>" data-lokasi="<?= htmlspecialchars($d['lokasi']) ?>" data-harga="<?= $d['harga_tiket'] ?>">
                    <?= htmlspecialchars($d['nama_destinasi']) ?> — <?= htmlspecialchars($d['lokasi']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Tanggal & Hari Kunjungan -->
          <div class="grid grid-cols-2 gap-4 mb-5">
            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-muted">Tanggal Kunjungan <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_kunjungan" id="inputTgl" class="form-control" style="border-radius: 0.75rem; font-weight: 600;" value="<?= date('Y-m-d') ?>" required onchange="updateLiveTicketSlip()">
            </div>

            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-muted">Hari Operasional</label>
              <input type="text" id="displayHari" class="form-control" style="border-radius: 0.75rem; font-weight: 700; background: #f8fafc; color: #0f766e;" value="<?= getNamaHariIndo(date('Y-m-d')) ?>" readonly>
            </div>
          </div>

          <!-- Jumlah Pengunjung & Quick Add Stepper -->
          <div class="form-group mb-5">
            <label class="form-label font-bold text-xs uppercase text-muted" style="display: flex; justify-content: space-between;">
              <span>Jumlah Wisatawan Masuk <span class="text-danger">*</span></span>
              <span style="font-weight: normal; font-size: 0.72rem; color: #94a3b8;">Gunakan tombol cepat di bawah</span>
            </label>
            
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
              <button type="button" class="btn btn-secondary btn-lg" onclick="adjustCount(-1)" style="font-weight: 900; width: 48px; height: 48px; border-radius: 0.75rem; font-size: 1.25rem;">-</button>
              <input type="number" name="jumlah_pengunjung" id="inputJmlOrang" class="form-control text-center" value="1" min="1" style="font-weight: 900; font-size: 1.5rem; height: 48px; font-family: 'Outfit', sans-serif; border-radius: 0.75rem; color: #0f766e;" required oninput="updateLiveTicketSlip()">
              <button type="button" class="btn btn-secondary btn-lg" onclick="adjustCount(1)" style="font-weight: 900; width: 48px; height: 48px; border-radius: 0.75rem; font-size: 1.25rem;">+</button>
            </div>

            <!-- Quick Add Chips -->
            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
              <button type="button" class="btn btn-light btn-sm" onclick="setCount(2)" style="border: 1px solid #e2e8f0; font-weight: 700; border-radius: 0.5rem;">2 Org</button>
              <button type="button" class="btn btn-light btn-sm" onclick="setCount(4)" style="border: 1px solid #e2e8f0; font-weight: 700; border-radius: 0.5rem;">4 Org (Keluarga)</button>
              <button type="button" class="btn btn-light btn-sm" onclick="setCount(10)" style="border: 1px solid #e2e8f0; font-weight: 700; border-radius: 0.5rem;">+10 (Rombongan)</button>
              <button type="button" class="btn btn-light btn-sm" onclick="setCount(25)" style="border: 1px solid #e2e8f0; font-weight: 700; border-radius: 0.5rem;">+25 (Bus Medium)</button>
              <button type="button" class="btn btn-light btn-sm" onclick="setCount(50)" style="border: 1px solid #e2e8f0; font-weight: 700; border-radius: 0.5rem;">+50 (Big Bus)</button>
            </div>
          </div>

          <!-- Jenis Kunjungan -->
          <div class="form-group mb-5">
            <label class="form-label font-bold text-xs uppercase text-muted">Kategori Kunjungan</label>
            <div class="grid grid-cols-2 gap-3">
              <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 0.75rem; cursor: pointer; background: #ffffff;" id="labelIndividu">
                <input type="radio" name="jenis_kunjungan" value="individu" id="radioIndividu" checked onchange="updateRadioSelection()">
                <div>
                  <strong style="font-size: 0.88rem; color: #1e293b; display: block;">Individu / Mandiri</strong>
                  <span style="font-size: 0.72rem; color: #64748b;">Perorangan atau keluarga kecil (&lt; 10 orang)</span>
                </div>
              </label>

              <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 0.75rem; cursor: pointer; background: #ffffff;" id="labelRombongan">
                <input type="radio" name="jenis_kunjungan" value="rombongan" id="radioRombongan" onchange="updateRadioSelection()">
                <div>
                  <strong style="font-size: 0.88rem; color: #1e293b; display: block;">Rombongan / Tour</strong>
                  <span style="font-size: 0.72rem; color: #64748b;">Grup sekolah, kantor, atau rombongan bus</span>
                </div>
              </label>
            </div>
          </div>

          <!-- Keterangan Tambahan -->
          <div class="form-group mb-6">
            <label class="form-label font-bold text-xs uppercase text-muted">Keterangan / Catatan Loket (Opsional)</label>
            <textarea name="keterangan" id="inputKet" rows="2" class="form-control text-sm" placeholder="Cth: Pembelian tunai loket gerbang barat, rombongan SMA Nusantara..." style="border-radius: 0.75rem;" oninput="updateLiveTicketSlip()"></textarea>
          </div>

          <!-- Submit Button -->
          <button type="submit" class="btn btn-primary btn-block btn-lg btn-luxury-pulse" style="font-weight: 800; border-radius: 0.85rem; padding: 1.1rem; font-size: 1.15rem; box-shadow: 0 10px 25px -5px rgba(13, 148, 136, 0.45);">
            <i class="fa-solid fa-floppy-disk"></i> SIMPAN PRESENSI KE REKAPITULASI
          </button>

        </form>
      </div>
    </div>

    <!-- Right Column: Live Pass Slip Preview & Today's Summary (4 cols) -->
    <div class="col-span-4" style="grid-column: span 4 / span 4;">
      
      <!-- Live Slip Mockup -->
      <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 1.25rem; padding: 1.5rem; color: #ffffff; margin-bottom: 1.5rem; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.2);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
          <span style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #38bdf8;">
            <i class="fa-solid fa-receipt"></i> Slip Ringkasan Masuk
          </span>
          <span class="badge-luxury badge-luxury-success" style="font-size: 0.65rem;">Loket Fisik</span>
        </div>

        <div style="background: rgba(255,255,255,0.06); border: 1px dashed rgba(255,255,255,0.2); border-radius: 0.85rem; padding: 1.25rem;">
          <span style="font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; display: block;">Destinasi:</span>
          <h4 id="slipDest" style="font-size: 1.1rem; font-weight: 800; color: #ffffff; margin: 0.2rem 0 0.75rem 0;">
            -- Belum Dipilih --
          </h4>

          <div style="display: flex; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 0.6rem; margin-bottom: 0.6rem; font-size: 0.8rem;">
            <span style="color: #94a3b8;">Tanggal:</span>
            <strong id="slipTgl" style="color: #f8fafc;"><?= date('d/m/Y') ?></strong>
          </div>

          <div style="display: flex; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 0.6rem; margin-bottom: 0.6rem; font-size: 0.8rem;">
            <span style="color: #94a3b8;">Tipe:</span>
            <strong id="slipTipe" style="color: #38bdf8; text-transform: capitalize;">Individu</strong>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 0.75rem; margin-top: 0.5rem;">
            <span style="color: #94a3b8; font-weight: 700;">Jumlah Masuk:</span>
            <strong id="slipJumlah" style="font-size: 1.5rem; color: #2dd4bf; font-family: 'Outfit', sans-serif;">1 Orang</strong>
          </div>
        </div>

        <div style="margin-top: 1rem; font-size: 0.72rem; color: #64748b; text-align: center;">
          Data langsung terintegrasi dengan laporan dashboard admin utama.
        </div>
      </div>

      <!-- Shift Card -->
      <div class="card p-5 bg-white shadow-sm" style="border-radius: 1.25rem; border: 1px solid #e2e8f0;">
        <h4 style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0 0 0.75rem 0; display: flex; align-items: center; gap: 0.4rem;">
          <i class="fa-solid fa-clock-rotate-left text-primary"></i> Rekap Shift Anda Hari Ini
        </h4>

        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;">
          <span style="color: #64748b;">Total Wisatawan:</span>
          <strong style="color: #0d9488; font-size: 1.05rem;"><?= number_format($statHariIni['total_pengunjung']) ?> Org</strong>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0; font-size: 0.85rem;">
          <span style="color: #64748b;">Total Transaksi:</span>
          <strong style="color: #1e293b;"><?= $statHariIni['total_entri'] ?> Kali Entri</strong>
        </div>

        <a href="<?= BASE_URL ?>petugas/riwayat_input.php" class="btn btn-light btn-block btn-sm" style="margin-top: 1rem; font-weight: 700; border: 1px solid #cbd5e1; font-size: 0.78rem;">
          Lihat Riwayat Lengkap
        </a>
      </div>

    </div>

  </div>

</main>

<script>
const daysIndo = {
  0: 'Minggu',
  1: 'Senin',
  2: 'Selasa',
  3: 'Rabu',
  4: 'Kamis',
  5: 'Jumat',
  6: 'Sabtu'
};

function adjustCount(delta) {
  const input = document.getElementById('inputJmlOrang');
  let val = parseInt(input.value) || 1;
  val = Math.max(1, val + delta);
  input.value = val;
  autoSelectJenis(val);
  updateLiveTicketSlip();
}

function setCount(amount) {
  const input = document.getElementById('inputJmlOrang');
  let val = parseInt(input.value) || 0;
  val += amount;
  input.value = val;
  autoSelectJenis(val);
  updateLiveTicketSlip();
}

function autoSelectJenis(count) {
  const rIndividu = document.getElementById('radioIndividu');
  const rRombongan = document.getElementById('radioRombongan');
  if (count >= 10) {
    rRombongan.checked = true;
  } else {
    rIndividu.checked = true;
  }
  updateRadioSelection();
}

function updateRadioSelection() {
  const rIndividu = document.getElementById('radioIndividu');
  const lIndividu = document.getElementById('labelIndividu');
  const lRombongan = document.getElementById('labelRombongan');

  if (rIndividu.checked) {
    lIndividu.style.borderColor = '#0d9488';
    lIndividu.style.background = '#f0fdfa';
    lRombongan.style.borderColor = '#e2e8f0';
    lRombongan.style.background = '#ffffff';
  } else {
    lRombongan.style.borderColor = '#0d9488';
    lRombongan.style.background = '#f0fdfa';
    lIndividu.style.borderColor = '#e2e8f0';
    lIndividu.style.background = '#ffffff';
  }
  updateLiveTicketSlip();
}

function updateLiveTicketSlip() {
  const selDest = document.getElementById('selDestinasi');
  const selectedOpt = selDest.options[selDest.selectedIndex];
  const destName = selectedOpt && selectedOpt.dataset.name ? selectedOpt.dataset.name : '-- Belum Dipilih --';
  
  const jml = document.getElementById('inputJmlOrang').value || 1;
  const tgl = document.getElementById('inputTgl').value;
  
  // Update Day display
  if (tgl) {
    const d = new Date(tgl);
    const dayName = daysIndo[d.getDay()] || 'Hari';
    document.getElementById('displayHari').value = dayName;
    document.getElementById('slipTgl').innerText = tgl;
  }

  const isRombongan = document.getElementById('radioRombongan').checked;

  document.getElementById('slipDest').innerText = destName;
  document.getElementById('slipJumlah').innerText = parseInt(jml) + ' Orang';
  document.getElementById('slipTipe').innerText = isRombongan ? 'Rombongan / Tour' : 'Individu';
}

// Initial Call
updateRadioSelection();
updateLiveTicketSlip();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
