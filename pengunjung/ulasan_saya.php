<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('pengunjung');

$userId = $_SESSION['user_id'];
$destinasiPreselect = (int)($_GET['destinasi_id'] ?? 0);

$msg = '';
$msgType = '';

// 1. Handle Tambah / Simpan Ulasan
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_submit_review'])) {
    $destinasiId = (int)($_POST['destinasi_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 5);
    $komentar = trim($_POST['komentar'] ?? '');

    if ($destinasiId <= 0) {
        $msg = "Silakan pilih destinasi wisata yang ingin diulas.";
        $msgType = "danger";
    } elseif ($rating < 1 || $rating > 5) {
        $msg = "Pilih rating bintang antara 1 hingga 5.";
        $msgType = "danger";
    } elseif (empty($komentar)) {
        $msg = "Tuliskan ulasan pengalaman wisata Anda.";
        $msgType = "danger";
    } else {
        // Cek apakah sudah pernah mengulas destinasi ini
        $cek = $pdo->prepare("SELECT id FROM ulasan WHERE user_id = ? AND destinasi_id = ? LIMIT 1");
        $cek->execute([$userId, $destinasiId]);
        $existing = $cek->fetch();

        if ($existing) {
            // Update ulasan
            $upd = $pdo->prepare("UPDATE ulasan SET rating = ?, komentar = ?, created_at = NOW() WHERE id = ?");
            $upd->execute([$rating, $komentar, $existing['id']]);
            setFlash('success', 'Ulasan destinasi wisata Anda berhasil diperbarui!');
        } else {
            // Insert ulasan baru
            $ins = $pdo->prepare("INSERT INTO ulasan (destinasi_id, user_id, rating, komentar, created_at) VALUES (?, ?, ?, ?, NOW())");
            $ins->execute([$destinasiId, $userId, $rating, $komentar]);
            setFlash('success', 'Terima kasih! Ulasan & rating bintang Anda berhasil dipublikasikan.');
        }
        header("Location: " . BASE_URL . "pengunjung/ulasan_saya.php");
        exit;
    }
}

// 2. Handle Hapus Ulasan
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_delete_review'])) {
    $ulasanId = (int)($_POST['ulasan_id'] ?? 0);
    $del = $pdo->prepare("DELETE FROM ulasan WHERE id = ? AND user_id = ?");
    if ($del->execute([$ulasanId, $userId])) {
        setFlash('success', 'Ulasan Anda berhasil dihapus.');
    } else {
        setFlash('danger', 'Gagal menghapus ulasan.');
    }
    header("Location: " . BASE_URL . "pengunjung/ulasan_saya.php");
    exit;
}

// 3. Ambil Daftar Destinasi
$destinasiList = $pdo->query("SELECT id, nama_destinasi, lokasi FROM destinasi WHERE status = 'buka' ORDER BY nama_destinasi ASC")->fetchAll();

// 4. Ambil Semua Ulasan yang Ditulis Pengguna Ini
$stmtMyReviews = $pdo->prepare("SELECT u.*, d.nama_destinasi, d.lokasi, d.foto_utama, k.nama_kategori 
                                FROM ulasan u 
                                JOIN destinasi d ON u.destinasi_id = d.id 
                                LEFT JOIN kategori_wisata k ON d.kategori_id = k.id 
                                WHERE u.user_id = ? 
                                ORDER BY u.id DESC");
$stmtMyReviews->execute([$userId]);
$myReviews = $stmtMyReviews->fetchAll();

$pageTitle = "Ulasan & Review Wisata Saya";
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
          <i class="fa-solid fa-star-half-stroke"></i> Komunitas & Pengalaman
        </span>
        <span style="font-size: 0.72rem; color: #64748b;">
          <?= count($myReviews) ?> Ulasan Dipublikasikan
        </span>
      </div>
      <h1 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Ulasan & Review Wisata Saya
      </h1>
      <p style="font-size: 0.75rem; color: #64748b; margin: 0.1rem 0 0 0;">
        Bagikan testimoni liburan Anda, berikan rating bintang, dan bantu wisatawan lain.
      </p>
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

  <!-- Grid Layout: Form Tulis Ulasan (Left) & Daftar Ulasan Saya (Right) -->
  <div class="grid grid-cols-12 gap-3">
    
    <!-- Left Column: Form Tulis Ulasan Baru (5 Cols) -->
    <div class="col-span-12 lg:col-span-5" style="grid-column: span 5 / span 5;">
      <div class="card p-3 bg-white shadow-sm" style="border-radius: 0.75rem; border: 1.5px solid #e0f2fe; position: sticky; top: 1rem;">
        
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f1f5f9;">
          <div style="width: 32px; height: 32px; border-radius: 0.45rem; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
            <i class="fa-solid fa-pen-nib"></i>
          </div>
          <div>
            <h3 style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0;">Tulis Ulasan Destinasi</h3>
            <span style="font-size: 0.68rem; color: #64748b;">Berikan testimoni jujur pengalaman Anda</span>
          </div>
        </div>

        <form action="<?= BASE_URL ?>pengunjung/ulasan_saya.php" method="POST">
          <input type="hidden" name="action_submit_review" value="1">

          <!-- Pilih Destinasi -->
          <div class="form-group mb-2">
            <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">
              Destinasi Objek Wisata <span style="color: #ef4444;">*</span>
            </label>
            <div style="position: relative;">
              <i class="fa-solid fa-mountain-sun" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem;"></i>
              <select name="destinasi_id" id="reviewDestinasiId" class="form-control" style="padding-left: 2rem; border-radius: 0.45rem; font-weight: 600; font-size: 0.78rem; height: 32px;" required>
                <option value="">-- Pilih Destinasi --</option>
                <?php foreach ($destinasiList as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= $destinasiPreselect == $d['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['nama_destinasi']) ?> (<?= htmlspecialchars($d['lokasi']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Rating Stars Interaktif -->
          <div class="form-group mb-2">
            <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">
              Rating Bintang <span style="color: #ef4444;">*</span>
            </label>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.45rem 0.65rem; display: flex; align-items: center; justify-content: space-between;">
              <div class="star-rating-select">
                <input type="radio" id="star5" name="rating" value="5" checked onchange="updateStarLabel(5)">
                <label for="star5" title="5 Bintang - Sempurna"><i class="fa-solid fa-star"></i></label>
                <input type="radio" id="star4" name="rating" value="4" onchange="updateStarLabel(4)">
                <label for="star4" title="4 Bintang - Sangat Bagus"><i class="fa-solid fa-star"></i></label>
                <input type="radio" id="star3" name="rating" value="3" onchange="updateStarLabel(3)">
                <label for="star3" title="3 Bintang - Cukup"><i class="fa-solid fa-star"></i></label>
                <input type="radio" id="star2" name="rating" value="2" onchange="updateStarLabel(2)">
                <label for="star2" title="2 Bintang - Kurang"><i class="fa-solid fa-star"></i></label>
                <input type="radio" id="star1" name="rating" value="1" onchange="updateStarLabel(1)">
                <label for="star1" title="1 Bintang - Sangat Kurang"><i class="fa-solid fa-star"></i></label>
              </div>
              <span id="starRatingText" style="font-size: 0.75rem; font-weight: 800; color: #d97706;">
                ⭐⭐⭐⭐⭐ Luar Biasa
              </span>
            </div>
          </div>

          <!-- Komentar -->
          <div class="form-group mb-3">
            <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem;">
              Komentar & Pengalaman Wisata <span style="color: #ef4444;">*</span>
            </label>
            <textarea name="komentar" rows="3" class="form-control" style="font-size: 0.78rem; border-radius: 0.45rem; padding: 0.5rem;" placeholder="Ceritakan keindahan pemandangan, kebersihan fasilitas, kenyamanan, atau keramahan staf..." required></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-xs btn-luxury-pulse" style="width: 100%; border-radius: 0.45rem; font-weight: 800; height: 32px; justify-content: center; box-shadow: 0 3px 10px rgba(2, 132, 199, 0.4);">
            <i class="fa-solid fa-paper-plane"></i> Publikasikan Ulasan
          </button>
        </form>

      </div>
    </div>

    <!-- Right Column: Daftar Ulasan Saya (7 Cols) -->
    <div class="col-span-12 lg:col-span-7" style="grid-column: span 7 / span 7;">
      
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.6rem;">
        <h2 style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0;">
          Semua Ulasan Saya (<?= count($myReviews) ?>)
        </h2>
      </div>

      <?php if (empty($myReviews)): ?>
        <div class="card p-6 bg-white shadow-sm text-center" style="border-radius: 0.75rem; border: 1px dashed #cbd5e1; padding: 3rem 1.25rem;">
          <div style="width: 50px; height: 50px; border-radius: 50%; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem auto; font-size: 1.35rem;">
            <i class="fa-solid fa-star-half-stroke"></i>
          </div>
          <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem 0;">Belum Ada Ulasan</h3>
          <p style="font-size: 0.8rem; color: #64748b; max-width: 380px; margin: 0 auto 1rem auto;">
            Anda belum pernah menulis ulasan destinasi wisata. Tulis ulasan pertama Anda melalui form di sebelah kiri!
          </p>
        </div>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 0.65rem;">
          <?php foreach ($myReviews as $r): ?>
            <div class="card p-3 bg-white shadow-sm" style="border-radius: 0.65rem; border: 1px solid #e2e8f0;">
              <div style="display: flex; gap: 0.65rem; align-items: flex-start;">
                
                <!-- Thumb Photo -->
                <div style="width: 60px; height: 60px; min-width: 60px; border-radius: 0.45rem; overflow: hidden; background: #0f172a;">
                  <img src="<?= BASE_URL ?>assets/uploads/destinasi/<?= htmlspecialchars($r['foto_utama']) ?>" 
                       alt="<?= htmlspecialchars($r['nama_destinasi']) ?>" 
                       style="width: 100%; height: 100%; object-fit: cover;"
                       onerror="this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=200&q=80'">
                </div>

                <!-- Review Content -->
                <div style="flex: 1; min-width: 0;">
                  <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 0.15rem;">
                    <h3 style="font-size: 0.88rem; font-weight: 800; color: #0f172a; margin: 0;">
                      <?= htmlspecialchars($r['nama_destinasi']) ?>
                    </h3>
                    
                    <div style="display: flex; align-items: center; gap: 0.25rem;">
                      <!-- Stars Display -->
                      <div style="color: #f59e0b; font-size: 0.72rem;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                          <i class="fa-<?= $i <= $r['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                        <?php endfor; ?>
                      </div>
                      <span style="font-size: 0.65rem; color: #94a3b8; margin-left: 0.25rem;">
                        <?= date('d/m/Y', strtotime($r['created_at'])) ?>
                      </span>
                    </div>
                  </div>

                  <p style="font-size: 0.78rem; color: #334155; line-height: 1.4; margin: 0 0 0.35rem 0; font-style: italic;">
                    "<?= nl2br(htmlspecialchars($r['komentar'])) ?>"
                  </p>

                  <!-- Actions -->
                  <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                    <form action="<?= BASE_URL ?>pengunjung/ulasan_saya.php" method="POST" style="margin: 0;" onsubmit="return confirm('Hapus ulasan ini?')">
                      <input type="hidden" name="action_delete_review" value="1">
                      <input type="hidden" name="ulasan_id" value="<?= $r['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-xs" style="padding: 0.15rem 0.45rem; font-size: 0.68rem; border-radius: 0.35rem;">
                        <i class="fa-solid fa-trash-can"></i> Hapus
                      </button>
                    </form>
                  </div>

                </div>

              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>

  </div>

</main>

<script>
  function updateStarLabel(val) {
    const labels = {
      5: '⭐⭐⭐⭐⭐ Luar Biasa',
      4: '⭐⭐⭐⭐ Sangat Bagus',
      3: '⭐⭐⭐ Cukup Baik',
      2: '⭐⭐ Kurang Memuaskan',
      1: '⭐ Sangat Kurang'
    };
    document.getElementById('starRatingText').innerText = labels[val] || '';
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
