<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('pengunjung');

$userId = $_SESSION['user_id'];
$msg = '';
$msgType = '';

// Ambil Data Pengguna Terkini
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

// 1. Handle Upload & Update Foto Profil
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_photo'])) {
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['foto_profil']['tmp_name'];
        $fileName = $_FILES['foto_profil']['name'];
        $fileSize = $_FILES['foto_profil']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) {
            $msg = "Format file tidak didukung. Silakan gunakan format JPG, JPEG, PNG, atau WEBP.";
            $msgType = "danger";
        } elseif ($fileSize > 5 * 1024 * 1024) { // Max 5MB
            $msg = "Ukuran file terlalu besar. Maksimal 5MB.";
            $msgType = "danger";
        } else {
            $uploadDir = __DIR__ . '/../assets/uploads/users/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Hapus foto lama jika bukan default
            if (!empty($user['foto']) && $user['foto'] !== 'default_avatar.png' && file_exists($uploadDir . $user['foto'])) {
                @unlink($uploadDir . $user['foto']);
            }

            $newFileName = 'user_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                $upd = $pdo->prepare("UPDATE users SET foto = ? WHERE id = ?");
                $upd->execute([$newFileName, $userId]);
                $_SESSION['user_foto'] = $newFileName;
                setFlash('success', 'Foto profil Anda berhasil diperbarui dengan tampilan berkelas!');
                header("Location: " . BASE_URL . "pengunjung/profil.php");
                exit;
            } else {
                $msg = "Gagal mengunggah foto profil. Silakan coba lagi.";
                $msgType = "danger";
            }
        }
    } elseif (isset($_POST['remove_photo'])) {
        // Hapus foto profil kembali ke default
        $uploadDir = __DIR__ . '/../assets/uploads/users/';
        if (!empty($user['foto']) && $user['foto'] !== 'default_avatar.png' && file_exists($uploadDir . $user['foto'])) {
            @unlink($uploadDir . $user['foto']);
        }
        $upd = $pdo->prepare("UPDATE users SET foto = 'default_avatar.png' WHERE id = ?");
        $upd->execute([$userId]);
        $_SESSION['user_foto'] = 'default_avatar.png';
        setFlash('success', 'Foto profil berhasil direset ke avatar default.');
        header("Location: " . BASE_URL . "pengunjung/profil.php");
        exit;
    } else {
        $msg = "Silakan pilih berkas foto terlebih dahulu.";
        $msgType = "warning";
    }
}

// 2. Handle Update Biodata Profil
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_profile'])) {
    $nama = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $noTelp = trim($_POST['no_telp'] ?? '');

    if (empty($nama) || empty($email)) {
        $msg = "Nama lengkap dan alamat email tidak boleh kosong.";
        $msgType = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Format alamat email tidak valid.";
        $msgType = "danger";
    } else {
        // Cek duplikasi email
        $cek = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? AND id != ? LIMIT 1");
        $cek->execute([$email, $userId]);
        if ($cek->fetch()) {
            $msg = "Alamat email sudah digunakan oleh akun lain.";
            $msgType = "danger";
        } else {
            $upd = $pdo->prepare("UPDATE users SET nama = ?, email = ?, no_telp = ? WHERE id = ?");
            if ($upd->execute([$nama, $email, $noTelp, $userId])) {
                $_SESSION['user_nama'] = $nama;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_telp'] = $noTelp;
                setFlash('success', 'Data profil akun Anda berhasil diperbarui!');
                header("Location: " . BASE_URL . "pengunjung/profil.php");
                exit;
            } else {
                $msg = "Gagal memperbarui data profil.";
                $msgType = "danger";
            }
        }
    }
}

// 3. Handle Ganti Kata Sandi
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_password'])) {
    $oldPass = $_POST['password_lama'] ?? '';
    $newPass = $_POST['password_baru'] ?? '';
    $confirmPass = $_POST['konfirmasi_password'] ?? '';

    if (empty($oldPass) || empty($newPass) || empty($confirmPass)) {
        $msg = "Semua kolom kata sandi wajib diisi.";
        $msgType = "danger";
    } elseif (!password_verify($oldPass, $user['password'])) {
        $msg = "Kata sandi saat ini yang Anda masukkan salah.";
        $msgType = "danger";
    } elseif (strlen($newPass) < 6) {
        $msg = "Kata sandi baru minimal harus 6 karakter.";
        $msgType = "danger";
    } elseif ($newPass !== $confirmPass) {
        $msg = "Konfirmasi kata sandi baru tidak cocok.";
        $msgType = "danger";
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($upd->execute([$newHash, $userId])) {
            setFlash('success', 'Kata sandi akun Anda berhasil diperbarui!');
            header("Location: " . BASE_URL . "pengunjung/profil.php");
            exit;
        } else {
            $msg = "Gagal mengganti kata sandi.";
            $msgType = "danger";
        }
    }
}

// 4. Statistik Member
$totalTiket = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE user_id = $userId")->fetchColumn();
$totalLunas = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE user_id = $userId AND status_bayar = 'lunas'")->fetchColumn();
$totalUlasan = (int)$pdo->query("SELECT COUNT(*) FROM ulasan WHERE user_id = $userId")->fetchColumn();

// Foto Profile URL
$userFoto = $user['foto'] ?? 'default_avatar.png';
$hasCustomFoto = (!empty($userFoto) && $userFoto !== 'default_avatar.png' && file_exists(__DIR__ . '/../assets/uploads/users/' . $userFoto));
$fotoUrl = $hasCustomFoto ? BASE_URL . 'assets/uploads/users/' . htmlspecialchars($userFoto) : '';
$initial = strtoupper(substr($user['nama'], 0, 1));

$pageTitle = "Pengaturan Profil & Foto Wisatawan";
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
          <i class="fa-solid fa-crown text-amber-500"></i> VIP Member Area
        </span>
      </div>
      <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Pengaturan Profil & Foto Wisatawan
      </h1>
      <p style="font-size: 0.78rem; color: #64748b; margin: 0.1rem 0 0 0;">
        Kelola foto profil resmi berkelas, identitas data diri, dan keamanan akun reservasi Anda.
      </p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-3" style="border-radius: 0.75rem; padding: 0.75rem 1rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03); font-size: 0.85rem;">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald' : 'fa-circle-exclamation text-danger' ?>" style="font-size: 1.25rem;"></i>
      <div style="line-height: 1.4;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mb-3" style="border-radius: 0.75rem; padding: 0.75rem 1rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03); font-size: 0.85rem;">
      <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check text-emerald' : 'fa-circle-exclamation text-danger' ?>" style="font-size: 1.25rem;"></i>
      <div style="line-height: 1.4;"><?= $msg ?></div>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-12 gap-4">
    
    <!-- Left Column: Luxury Avatar & Photo Management Card (4 Cols) -->
    <div class="col-span-12 lg:col-span-4" style="grid-column: span 4 / span 4;">
      
      <!-- Profile Card -->
      <div class="card p-5 bg-white shadow-md text-center" style="border-radius: 1rem; border: 1px solid #e2e8f0; position: sticky; top: 1rem;">
        
        <!-- Luxury Avatar Frame with Glow & Camera Badge -->
        <div style="position: relative; width: 110px; height: 110px; margin: 0 auto 1.25rem auto;">
          <?php if ($hasCustomFoto): ?>
            <img src="<?= $fotoUrl ?>" alt="<?= htmlspecialchars($user['nama']) ?>" id="previewAvatarImg" style="width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 3.5px solid #0d9488; box-shadow: 0 8px 25px rgba(13, 148, 136, 0.35);">
          <?php else: ?>
            <div id="initialAvatarBox" style="width: 110px; height: 110px; border-radius: 50%; background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.75rem; font-weight: 800; border: 3.5px solid #ffffff; box-shadow: 0 8px 25px rgba(13, 148, 136, 0.35);">
              <?= $initial ?>
            </div>
            <img src="" alt="Preview" id="previewAvatarImg" style="display: none; width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 3.5px solid #0d9488; box-shadow: 0 8px 25px rgba(13, 148, 136, 0.35);">
          <?php endif; ?>

          <!-- Online VIP Status indicator -->
          <span style="position: absolute; bottom: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: #10b981; border: 3px solid #ffffff; box-shadow: 0 2px 6px rgba(0,0,0,0.2);" title="Akun Wisatawan Aktif"></span>
        </div>

        <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.2rem 0;">
          <?= htmlspecialchars($user['nama']) ?>
        </h3>

        <span style="font-size: 0.8rem; color: #64748b; display: block; margin-bottom: 0.75rem;">
          <i class="fa-solid fa-envelope text-primary"></i> <?= htmlspecialchars($user['email']) ?>
        </span>

        <span class="badge-member badge-member-gold mb-3" style="display: inline-flex; padding: 0.3rem 0.85rem; font-size: 0.75rem;">
          <i class="fa-solid fa-shield-check text-emerald"></i> Verified Pengunjung
        </span>

        <!-- Photo Upload Form & Dropzone -->
        <form action="<?= BASE_URL ?>pengunjung/profil.php" method="POST" enctype="multipart/form-data" id="photoUploadForm" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed #cbd5e1;">
          <input type="hidden" name="action_update_photo" value="1">

          <label for="fotoProfilInput" class="btn btn-outline-primary btn-sm btn-block" style="cursor: pointer; border-radius: 0.6rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 0.45rem; margin-bottom: 0.5rem;">
            <i class="fa-solid fa-camera"></i> Pilih Foto Baru
          </label>
          <input type="file" name="foto_profil" id="fotoProfilInput" accept="image/jpeg,image/png,image/webp,image/jpg" style="display: none;" onchange="handlePreviewPhoto(this)">

          <div id="uploadActionsContainer" style="display: none; gap: 0.4rem; margin-bottom: 0.5rem;">
            <button type="submit" class="btn btn-primary btn-sm btn-block" style="font-weight: 800; border-radius: 0.6rem;">
              <i class="fa-solid fa-cloud-arrow-up"></i> Simpan Foto
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="cancelPreviewPhoto()" title="Batal">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>

          <?php if ($hasCustomFoto): ?>
            <button type="submit" name="remove_photo" value="1" class="btn btn-sm btn-light text-danger btn-block" style="font-size: 0.75rem; border-radius: 0.6rem;" onclick="return confirm('Apakah Anda yakin ingin menghapus foto profil ini?')">
              <i class="fa-solid fa-trash-can"></i> Hapus Foto Profil
            </button>
          <?php endif; ?>

          <small style="display: block; font-size: 0.68rem; color: #94a3b8; margin-top: 0.6rem;">
            Format JPG, PNG, WEBP (Maks. 5MB)
          </small>
        </form>

        <!-- Quick Stats Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin-top: 1.25rem; border-top: 1px solid #f1f5f9; padding-top: 1rem; text-align: center;">
          <div style="background: #f8fafc; padding: 0.65rem 0.5rem; border-radius: 0.6rem; border: 1px solid #e2e8f0;">
            <span style="font-size: 0.68rem; color: #64748b; font-weight: 700; text-transform: uppercase; display: block;">Tiket Lunas</span>
            <strong style="font-size: 1.2rem; color: #0d9488; font-weight: 900; font-family: 'Outfit', sans-serif;"><?= $totalLunas ?></strong>
          </div>
          <div style="background: #f8fafc; padding: 0.65rem 0.5rem; border-radius: 0.6rem; border: 1px solid #e2e8f0;">
            <span style="font-size: 0.68rem; color: #64748b; font-weight: 700; text-transform: uppercase; display: block;">Ulasan Saya</span>
            <strong style="font-size: 1.2rem; color: #f59e0b; font-weight: 900; font-family: 'Outfit', sans-serif;"><?= $totalUlasan ?></strong>
          </div>
        </div>

      </div>
    </div>

    <!-- Right Column: Form Biodata & Password (8 Cols) -->
    <div class="col-span-12 lg:col-span-8" style="grid-column: span 8 / span 8; display: flex; flex-direction: column; gap: 1rem;">
      
      <!-- Card 1: Biodata Akun -->
      <div class="card p-5 bg-white shadow-md" style="border-radius: 1rem; border: 1px solid #e2e8f0;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 36px; height: 36px; border-radius: 10px; background: #ccfbf1; color: #0d9488; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
              <i class="fa-solid fa-address-card"></i>
            </div>
            <div>
              <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0;">Biodata Identitas Pengunjung</h3>
              <span style="font-size: 0.72rem; color: #64748b;">Informasi kontak yang tertera pada E-Ticket resmi</span>
            </div>
          </div>
        </div>

        <form action="<?= BASE_URL ?>pengunjung/profil.php" method="POST">
          <input type="hidden" name="action_update_profile" value="1">

          <div class="grid grid-cols-2 gap-4 mb-3">
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.04em;">
                Nama Lengkap <span style="color: #ef4444;">*</span>
              </label>
              <input type="text" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" class="form-control" style="font-size: 0.9rem; border-radius: 0.6rem; height: 42px;" required>
            </div>
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.04em;">
                Alamat Email <span style="color: #ef4444;">*</span>
              </label>
              <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control" style="font-size: 0.9rem; border-radius: 0.6rem; height: 42px;" required>
            </div>
          </div>

          <div class="form-group mb-4">
            <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.04em;">
              Nomor WhatsApp / HP Aktif
            </label>
            <div style="position: relative;">
              <input type="text" name="no_telp" value="<?= htmlspecialchars($user['no_telp'] ?? '') ?>" placeholder="081234567890" class="form-control" style="font-size: 0.9rem; border-radius: 0.6rem; height: 42px; padding-left: 2.75rem;">
              <i class="fa-brands fa-whatsapp text-emerald" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 1.1rem;"></i>
            </div>
          </div>

          <div style="display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary shadow-glow" style="border-radius: 0.6rem; font-weight: 800; height: 40px; padding: 0 1.5rem;">
              <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan Biodata
            </button>
          </div>
        </form>
      </div>

      <!-- Card 2: Keamanan & Kata Sandi -->
      <div class="card p-5 bg-white shadow-md" style="border-radius: 1rem; border: 1px solid #e2e8f0;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 36px; height: 36px; border-radius: 10px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
              <i class="fa-solid fa-lock"></i>
            </div>
            <div>
              <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0;">Keamanan & Kata Sandi Akun</h3>
              <span style="font-size: 0.72rem; color: #64748b;">Perbarui kata sandi secara berkala demi perlindungan akun</span>
            </div>
          </div>
        </div>

        <form action="<?= BASE_URL ?>pengunjung/profil.php" method="POST">
          <input type="hidden" name="action_update_password" value="1">

          <div class="form-group mb-3">
            <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.04em;">
              Kata Sandi Saat Ini <span style="color: #ef4444;">*</span>
            </label>
            <input type="password" name="password_lama" class="form-control" style="font-size: 0.9rem; border-radius: 0.6rem; height: 42px;" placeholder="Masukkan password lama Anda" required>
          </div>

          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.04em;">
                Kata Sandi Baru <span style="color: #ef4444;">*</span>
              </label>
              <input type="password" name="password_baru" class="form-control" style="font-size: 0.9rem; border-radius: 0.6rem; height: 42px;" placeholder="Minimal 6 karakter" minlength="6" required>
            </div>
            <div>
              <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.04em;">
                Ulangi Kata Sandi Baru <span style="color: #ef4444;">*</span>
              </label>
              <input type="password" name="konfirmasi_password" class="form-control" style="font-size: 0.9rem; border-radius: 0.6rem; height: 42px;" placeholder="Ulangi kata sandi baru" minlength="6" required>
            </div>
          </div>

          <div style="display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-warning shadow-glow-gold" style="border-radius: 0.6rem; font-weight: 800; height: 40px; padding: 0 1.5rem; color: #78350f;">
              <i class="fa-solid fa-key"></i> Perbarui Kata Sandi
            </button>
          </div>
        </form>
      </div>

    </div>

  </div>

</main>

<script>
function handlePreviewPhoto(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
      alert('Ukuran berkas terlalu besar. Maksimal 5MB.');
      input.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
      const previewImg = document.getElementById('previewAvatarImg');
      const initialBox = document.getElementById('initialAvatarBox');
      const actionsContainer = document.getElementById('uploadActionsContainer');

      if (initialBox) initialBox.style.display = 'none';
      if (previewImg) {
        previewImg.src = e.target.result;
        previewImg.style.display = 'block';
      }
      if (actionsContainer) {
        actionsContainer.style.display = 'flex';
      }
    };
    reader.readAsDataURL(file);
  }
}

function cancelPreviewPhoto() {
  const input = document.getElementById('fotoProfilInput');
  const previewImg = document.getElementById('previewAvatarImg');
  const initialBox = document.getElementById('initialAvatarBox');
  const actionsContainer = document.getElementById('uploadActionsContainer');

  if (input) input.value = '';
  if (actionsContainer) actionsContainer.style.display = 'none';

  <?php if ($hasCustomFoto): ?>
    if (previewImg) previewImg.src = '<?= $fotoUrl ?>';
  <?php else: ?>
    if (previewImg) previewImg.style.display = 'none';
    if (initialBox) initialBox.style.display = 'flex';
  <?php endif; ?>
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
