<?php
require_once __DIR__ . '/config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

// 1. Handle Update Foto Profil
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_photo'])) {
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['foto_profil']['tmp_name'];
        $fileName = $_FILES['foto_profil']['name'];
        $fileSize = $_FILES['foto_profil']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) {
            $error = "Format file tidak didukung. Silakan gunakan format JPG, JPEG, PNG, atau WEBP.";
        } elseif ($fileSize > 5 * 1024 * 1024) {
            $error = "Ukuran file terlalu besar. Maksimal 5MB.";
        } else {
            $uploadDir = __DIR__ . '/assets/uploads/users/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if (!empty($user['foto']) && $user['foto'] !== 'default_avatar.png' && file_exists($uploadDir . $user['foto'])) {
                @unlink($uploadDir . $user['foto']);
            }

            $newFileName = 'user_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                $upd = $pdo->prepare("UPDATE users SET foto = ? WHERE id = ?");
                $upd->execute([$newFileName, $userId]);
                $_SESSION['user_foto'] = $newFileName;
                $success = "Foto profil Anda berhasil diperbarui!";
                
                // Refresh data
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } else {
                $error = "Gagal mengunggah foto profil.";
            }
        }
    } elseif (isset($_POST['remove_photo'])) {
        $uploadDir = __DIR__ . '/assets/uploads/users/';
        if (!empty($user['foto']) && $user['foto'] !== 'default_avatar.png' && file_exists($uploadDir . $user['foto'])) {
            @unlink($uploadDir . $user['foto']);
        }
        $upd = $pdo->prepare("UPDATE users SET foto = 'default_avatar.png' WHERE id = ?");
        $upd->execute([$userId]);
        $_SESSION['user_foto'] = 'default_avatar.png';
        $success = "Foto profil berhasil direset ke avatar default.";
        
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
}

// 2. Handle Update Biodata & Password
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_profile'])) {
    $nama = trim($_POST['nama'] ?? '');
    $no_telp = trim($_POST['no_telp'] ?? '');
    $password_baru = $_POST['password_baru'] ?? '';

    if (empty($nama)) {
        $error = "Nama lengkap tidak boleh kosong.";
    } else {
        if (!empty($password_baru)) {
            if (strlen($password_baru) < 6) {
                $error = "Kata sandi baru minimal 6 karakter.";
            } else {
                $hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET nama = ?, no_telp = ?, password = ? WHERE id = ?");
                $update->execute([$nama, $no_telp, $hash, $userId]);
                $_SESSION['user_nama'] = $nama;
                $success = "Profil dan kata sandi Anda berhasil diperbarui!";
            }
        } else {
            $update = $pdo->prepare("UPDATE users SET nama = ?, no_telp = ? WHERE id = ?");
            $update->execute([$nama, $no_telp, $userId]);
            $_SESSION['user_nama'] = $nama;
            $success = "Profil akun Anda berhasil disimpan!";
        }

        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
}

// Foto URL
$userFoto = $user['foto'] ?? 'default_avatar.png';
$hasCustomFoto = (!empty($userFoto) && $userFoto !== 'default_avatar.png' && file_exists(__DIR__ . '/assets/uploads/users/' . $userFoto));
$fotoUrl = $hasCustomFoto ? BASE_URL . 'assets/uploads/users/' . htmlspecialchars($userFoto) : '';
$initial = strtoupper(substr($user['nama'], 0, 1));

$pageTitle = "Profil Akun Pengguna - Pesona Nusantara";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container" style="padding-top: 3.5rem; padding-bottom: 5rem;">
  <div style="max-width: 720px; margin: 0 auto;">
    
    <div class="card" style="box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.12); border-radius: var(--radius-xl); overflow: hidden; border: 1px solid #e2e8f0; background: #ffffff;">
      
      <!-- Top Banner Header -->
      <div style="background: linear-gradient(135deg, #090d16 0%, #042426 50%, #082f49 100%); padding: 2.5rem; color: #ffffff; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 1.5rem;">
          
          <!-- Avatar Frame -->
          <div style="position: relative; width: 88px; height: 88px;">
            <?php if ($hasCustomFoto): ?>
              <img src="<?= $fotoUrl ?>" alt="<?= htmlspecialchars($user['nama']) ?>" style="width: 88px; height: 88px; border-radius: 50%; object-fit: cover; border: 3px solid #2dd4bf; box-shadow: 0 6px 20px rgba(13, 148, 136, 0.4);">
            <?php else: ?>
              <div style="width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg, #0d9488, #0284c7); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; border: 3px solid #ffffff; box-shadow: 0 6px 20px rgba(13, 148, 136, 0.4);">
                <?= $initial ?>
              </div>
            <?php endif; ?>
            <span style="position: absolute; bottom: 2px; right: 2px; width: 18px; height: 18px; border-radius: 50%; background: #10b981; border: 2.5px solid #ffffff;" title="Status Online"></span>
          </div>

          <div>
            <h2 style="font-size: 1.5rem; font-weight: 800; color: #ffffff; margin: 0 0 0.25rem 0;"><?= htmlspecialchars($user['nama']) ?></h2>
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
              <span class="badge badge-accent" style="text-transform: uppercase; font-size: 0.72rem; font-weight: 800;"><i class="fa-solid fa-crown"></i> <?= ucfirst($user['role']) ?></span>
              <span style="font-size: 0.78rem; color: #94a3b8;"><i class="fa-solid fa-envelope text-primary"></i> <?= htmlspecialchars($user['email']) ?></span>
            </div>
          </div>
        </div>

        <span style="font-size: 0.75rem; color: #94a3b8; background: rgba(255,255,255,0.08); padding: 0.4rem 0.85rem; border-radius: 9999px; border: 1px solid rgba(255,255,255,0.15);">
          <i class="fa-solid fa-calendar-check text-emerald"></i> Sejak <?= formatTanggalIndo($user['created_at']) ?>
        </span>
      </div>

      <div class="card-body" style="padding: 2.5rem;">
        
        <?php if ($error): ?>
          <div class="alert alert-danger mb-4" style="border-radius: 0.75rem;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div><?= htmlspecialchars($error) ?></div>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success mb-4" style="border-radius: 0.75rem;">
            <i class="fa-solid fa-check-circle"></i>
            <div><?= htmlspecialchars($success) ?></div>
          </div>
        <?php endif; ?>

        <!-- Form 1: Foto Profil -->
        <div style="margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid #f1f5f9;">
          <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-camera text-primary"></i> Foto Profil Berkelas
          </h3>

          <form action="" method="POST" enctype="multipart/form-data" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <input type="hidden" name="action_update_photo" value="1">
            
            <input type="file" name="foto_profil" accept="image/jpeg,image/png,image/webp,image/jpg" required class="form-control" style="max-width: 320px; font-size: 0.85rem; padding: 0.45rem 0.75rem;">
            
            <button type="submit" class="btn btn-primary btn-sm" style="font-weight: 800; border-radius: 0.6rem;">
              <i class="fa-solid fa-cloud-arrow-up"></i> Unggah Foto
            </button>

            <?php if ($hasCustomFoto): ?>
              <button type="submit" name="remove_photo" value="1" class="btn btn-secondary btn-sm" onclick="return confirm('Hapus foto profil?')" style="font-size: 0.8rem;">
                <i class="fa-solid fa-trash-can text-danger"></i> Reset Foto
              </button>
            <?php endif; ?>
          </form>
          <small style="color: #94a3b8; font-size: 0.75rem; margin-top: 0.4rem; display: block;">Mendukung format JPG, PNG, WEBP (Maksimal 5MB).</small>
        </div>

        <!-- Form 2: Biodata & Keamanan -->
        <form method="POST" action="">
          <input type="hidden" name="action_update_profile" value="1">

          <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-user-pen text-primary"></i> Informasi Akun & Biodata
          </h3>

          <div class="form-group mb-3">
            <label class="form-label font-bold text-xs uppercase text-slate-600">Alamat Email (Akun Resmi Terverifikasi)</label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background-color: #f1f5f9; cursor: not-allowed; font-weight: 600;">
          </div>

          <div class="grid grid-cols-2 gap-4 mb-3">
            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-slate-600" for="nama">Nama Lengkap *</label>
              <input type="text" name="nama" id="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required style="font-weight: 600;">
            </div>

            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-slate-600" for="no_telp">Nomor WhatsApp / HP</label>
              <input type="text" name="no_telp" id="no_telp" class="form-control" value="<?= htmlspecialchars($user['no_telp'] ?? '') ?>" placeholder="081234567890" style="font-weight: 600;">
            </div>
          </div>

          <div class="form-group mb-4" style="padding-top: 1.25rem; border-top: 1px dashed #cbd5e1;">
            <label class="form-label font-bold text-xs uppercase text-slate-600" for="password_baru">
              Ganti Kata Sandi (Kosongkan jika tidak ingin mengubah)
            </label>
            <input type="password" name="password_baru" id="password_baru" class="form-control" placeholder="Masukkan kata sandi baru (Min. 6 Karakter)">
          </div>

          <div class="flex justify-between items-center" style="margin-top: 2rem;">
            <a href="<?= BASE_URL ?>index.php" class="btn btn-secondary">
              <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
            </a>
            <button type="submit" class="btn btn-primary btn-lg shadow-glow" style="font-weight: 800;">
              <i class="fa-solid fa-floppy-disk"></i> Simpan Seluruh Perubahan
            </button>
          </div>
        </form>

      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
