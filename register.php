<?php
require_once __DIR__ . '/config/database.php';

// Pastikan kolom username tersedia di database
try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `username` VARCHAR(100) NULL AFTER `nama`");
    $pdo->exec("UPDATE `users` SET `username` = SUBSTRING_INDEX(email, '@', 1) WHERE `username` IS NULL OR `username` = ''");
} catch (Exception $e) {}

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$error = '';
$success = '';

$namaInput = '';
$usernameInput = '';
$emailInput = '';
$telpInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $no_telp = trim($_POST['no_telp'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $konfirmasi = trim($_POST['konfirmasi_password'] ?? '');

    $namaInput = $nama;
    $usernameInput = $username;
    $emailInput = $email;
    $telpInput = $no_telp;

    // Bersihkan username dari karakter tidak valid jika diisi
    if (!empty($username)) {
        $username = preg_replace('/[^a-z0-9_.]/', '', $username);
    } else {
        $username = explode('@', $email)[0];
    }

    if (empty($nama) || empty($email) || empty($password)) {
        $error = "Mohon lengkapi seluruh kolom yang bertanda bintang (*).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format alamat email tidak valid. Pastikan format penulisan benar.";
    } elseif (strlen($password) < 6) {
        $error = "Kata sandi minimal harus 6 karakter demi keamanan akun Anda.";
    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi kata sandi tidak cocok dengan kata sandi baru.";
    } else {
        // Cek apakah email sudah terdaftar
        $stmtEmail = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1");
        $stmtEmail->execute([$email]);

        // Cek apakah username sudah dipakai
        $stmtUser = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = ? LIMIT 1");
        $stmtUser->execute([$username]);

        if ($stmtEmail->fetch()) {
            $error = "Alamat email <strong>" . htmlspecialchars($email) . "</strong> sudah terdaftar. Silakan <a href='" . BASE_URL . "login.php' style='color:#0d9488; font-weight:800; text-decoration:underline;'>Masuk di sini</a> atau gunakan email lain.";
        } elseif ($stmtUser->fetch()) {
            $error = "Username <strong>@" . htmlspecialchars($username) . "</strong> sudah digunakan oleh pengguna lain. Silakan pilih username lain.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (nama, username, email, password, no_telp, role, created_at) VALUES (?, ?, ?, ?, ?, 'pengunjung', NOW())");
            
            if ($insert->execute([$nama, $username, $email, $hash, $no_telp])) {
                $newId = $pdo->lastInsertId();
                
                // Log pendaftaran
                try {
                    $log = $pdo->prepare("INSERT INTO transaksi_log (user_id, tipe_transaksi, deskripsi, created_at) VALUES (?, 'kunjungan', ?, NOW())");
                    $log->execute([$newId, 'Pendaftaran akun baru: ' . $nama . ' (@' . $username . ')']);
                } catch (Exception $e) {}

                // Auto login user baru
                $_SESSION['user_id'] = $newId;
                $_SESSION['user_nama'] = $nama;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'pengunjung';
                $_SESSION['user_foto'] = 'default_avatar.png';

                setFlash('success', 'Selamat bergabung, <strong>' . htmlspecialchars($nama) . '</strong>! Akun pengunjung Anda berhasil dibuat.');
                header("Location: " . BASE_URL . "pengunjung/index.php");
                exit;
            } else {
                $error = "Terjadi kendala saat mendaftarkan akun. Silakan coba beberapa saat lagi.";
            }
        }
    }
}

$pageTitle = "Daftar Akun Pengunjung - Destinasi Wisata Nusantara";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="auth-luxury-page">
  <div class="container" style="max-width: 1080px; padding: 2.5rem 1rem;">
    
    <div class="auth-luxury-card">
      
      <!-- SISI KIRI: MEMBER PRIVILEGE SHOWCASE -->
      <div class="auth-brand-side">
        <div class="auth-brand-badge">
          <i class="fa-solid fa-sparkles text-amber"></i> Keuntungan Pengunjung Resmi
        </div>

        <h1 class="auth-brand-title">
          Buka Pintu <br>
          <span class="text-gradient-gold">Petualangan Nusantara</span>
        </h1>

        <p class="auth-brand-desc">
          Daftarkan akun pengunjung Anda secara gratis dalam 1 menit dan nikmati beragam kemudahan reservasi tiket digital resmi serta potongan promo rombongan otomatis.
        </p>

        <!-- Feature List with icons -->
        <div class="auth-privilege-list">
          
          <div class="auth-privilege-item">
            <div class="auth-privilege-icon">
              <i class="fa-solid fa-qrcode"></i>
            </div>
            <div>
              <div class="auth-privilege-heading">E-Ticket QR Langsung Aktif</div>
              <div class="auth-privilege-text">Simpan barcode digital di smartphone tanpa repot cetak fisik.</div>
            </div>
          </div>

          <div class="auth-privilege-item">
            <div class="auth-privilege-icon" style="color: #fbbf24; background: rgba(245, 158, 11, 0.15);">
              <i class="fa-solid fa-tags"></i>
            </div>
            <div>
              <div class="auth-privilege-heading">Diskon Rombongan Otomatis</div>
              <div class="auth-privilege-text">Potongan harga grup hingga 20% langsung dihitung sistem.</div>
            </div>
          </div>

          <div class="auth-privilege-item">
            <div class="auth-privilege-icon" style="color: #34d399; background: rgba(16, 185, 129, 0.15);">
              <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
              <div class="auth-privilege-heading">Riwayat Kunjungan Terpadu</div>
              <div class="auth-privilege-text">Pantau seluruh invoice, status bayar, dan ulasan wisata Anda.</div>
            </div>
          </div>

        </div>

        <div class="auth-brand-footer">
          <span><i class="fa-solid fa-shield-check text-emerald"></i> Pendaftaran 100% Gratis & Terverifikasi</span>
        </div>
      </div>

      <!-- SISI KANAN: FORMULIR REGISTRASI BERKELAS -->
      <div class="auth-form-side">
        
        <div class="auth-form-header">
          <div class="auth-logo-circle" style="background: linear-gradient(135deg, rgba(2, 132, 199, 0.15), rgba(13, 148, 136, 0.15)); color: #0284c7; border-color: rgba(2, 132, 199, 0.3);">
            <i class="fa-solid fa-user-plus"></i>
          </div>
          <h2 class="auth-form-title">Daftar Akun Baru</h2>
          <p class="auth-form-subtitle">Lengkapi formulir singkat di bawah untuk memulai pengalaman liburan Anda</p>
        </div>

        <!-- Alert Pesan Error -->
        <?php if ($error): ?>
          <div class="auth-alert auth-alert-danger">
            <i class="fa-solid fa-circle-exclamation auth-alert-icon"></i>
            <div><?= $error ?></div>
          </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="on" id="mainRegisterForm">
          
          <div class="grid grid-cols-2 gap-4">
            
            <!-- Nama Lengkap Input -->
            <div class="auth-form-group">
              <label class="auth-label">Nama Lengkap <span class="text-danger">*</span></label>
              <div class="auth-input-container">
                <i class="fa-solid fa-user-tag auth-input-icon"></i>
                <input type="text" name="nama" id="regNama" class="auth-input-field" placeholder="Cth: Muhammad Iqbal" required value="<?= htmlspecialchars($namaInput) ?>" autofocus>
              </div>
            </div>

            <!-- Username Input -->
            <div class="auth-form-group">
              <label class="auth-label">Username Akun <span class="text-danger">*</span></label>
              <div class="auth-input-container">
                <i class="fa-solid fa-at auth-input-icon"></i>
                <input type="text" name="username" id="regUsername" class="auth-input-field" placeholder="Cth: iqbal_traveler" required value="<?= htmlspecialchars($usernameInput) ?>">
              </div>
            </div>

          </div>

          <div class="grid grid-cols-2 gap-4">
            
            <!-- Email Input -->
            <div class="auth-form-group">
              <label class="auth-label">Alamat Email <span class="text-danger">*</span></label>
              <div class="auth-input-container">
                <i class="fa-solid fa-envelope auth-input-icon"></i>
                <input type="email" name="email" id="regEmail" class="auth-input-field" placeholder="nama@email.com" required value="<?= htmlspecialchars($emailInput) ?>">
              </div>
            </div>

            <!-- No Telp / WhatsApp Input -->
            <div class="auth-form-group">
              <label class="auth-label">No. WhatsApp / HP</label>
              <div class="auth-input-container">
                <i class="fa-brands fa-whatsapp auth-input-icon" style="color: #10b981;"></i>
                <input type="tel" name="no_telp" id="regTelp" class="auth-input-field" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($telpInput) ?>">
              </div>
            </div>

          </div>

          <!-- Password Input with Strength Meter -->
          <div class="auth-form-group">
            <label class="auth-label">Kata Sandi Baru <span class="text-danger">*</span></label>
            <div class="auth-input-container">
              <i class="fa-solid fa-lock auth-input-icon"></i>
              <input type="password" name="password" id="regPassword" class="auth-input-field" placeholder="Minimal 6 karakter kombinasi" required minlength="6" onkeyup="checkRegPasswordStrength(this.value)">
              <button type="button" class="auth-password-toggle" onclick="togglePasswordVisibility('regPassword', 'eyeIconReg')" title="Tampilkan/Sembunyikan">
                <i class="fa-regular fa-eye" id="eyeIconReg"></i>
              </button>
            </div>

            <!-- Password Strength Meter -->
            <div class="password-meter-wrap" id="regPasswordMeterWrap">
              <div class="password-meter-bar">
                <div class="password-meter-fill" id="regPasswordMeterFill"></div>
              </div>
              <span class="password-meter-text" id="regPasswordMeterText">Kekuatan Sandi: Belum diisi</span>
            </div>
          </div>

          <!-- Confirm Password Input -->
          <div class="auth-form-group">
            <label class="auth-label">Ulangi Kata Sandi <span class="text-danger">*</span></label>
            <div class="auth-input-container">
              <i class="fa-solid fa-shield-check auth-input-icon"></i>
              <input type="password" name="konfirmasi_password" id="regConfirmPassword" class="auth-input-field" placeholder="Ketik ulang kata sandi Anda" required minlength="6" onkeyup="checkRegPasswordMatch()">
              <button type="button" class="auth-password-toggle" onclick="togglePasswordVisibility('regConfirmPassword', 'eyeIconRegConfirm')" title="Tampilkan/Sembunyikan">
                <i class="fa-regular fa-eye" id="eyeIconRegConfirm"></i>
              </button>
            </div>
            <div id="regPasswordMatchMsg" class="auth-hint-text"></div>
          </div>

          <!-- Terms Agreement -->
          <div class="auth-extra-row">
            <label class="auth-checkbox-label">
              <input type="checkbox" name="agree" class="auth-checkbox" required checked>
              <span>Saya menyetujui Ketentuan Layanan & Kebijakan Privasi</span>
            </label>
          </div>

          <!-- Submit Button -->
          <button type="submit" class="auth-submit-btn" style="background: linear-gradient(135deg, #0284c7 0%, #0d9488 100%);">
            <span>Buat Akun Pengunjung Sekarang</span>
            <i class="fa-solid fa-arrow-right"></i>
          </button>

        </form>

        <!-- Switch to Login -->
        <div class="auth-switch-box">
          Sudah memiliki akun terdaftar? 
          <a href="<?= BASE_URL ?>login.php" class="auth-switch-link font-bold">Masuk ke Akun Anda</a>
        </div>

        <div class="auth-security-footnote">
          <i class="fa-solid fa-shield-halved text-emerald"></i>
          <span>Data Anda Dijamin Aman & Terenkripsi Sesuai Regulasi Wisata</span>
        </div>

      </div>

    </div>

  </div>
</div>

<style>
/* ==========================================================================
   ULTRA-LUXURY REGISTER PAGE STYLING
   ========================================================================== */
.auth-luxury-page {
  min-height: 86vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(circle at 10% 20%, rgba(2, 132, 199, 0.08) 0%, transparent 45%),
              radial-gradient(circle at 90% 80%, rgba(13, 148, 136, 0.08) 0%, transparent 45%),
              #f8fafc;
  padding: 3rem 0;
}

.auth-luxury-card {
  background: #ffffff;
  border-radius: 1.75rem;
  border: 1px solid #e2e8f0;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.06);
  display: grid;
  grid-template-columns: 1fr 1.25fr;
  overflow: hidden;
  margin: 0 auto;
}

.auth-brand-side {
  background: radial-gradient(circle at 70% 30%, #0c4a6e 0%, #090d16 100%);
  padding: 3.5rem 3rem;
  color: #ffffff;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
}

.auth-brand-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem 1rem;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 9999px;
  font-size: 0.8rem;
  font-weight: 700;
  color: #f1f5f9;
  margin-bottom: 1.5rem;
  width: fit-content;
}

.auth-brand-title {
  font-family: 'Outfit', sans-serif;
  font-size: 2.35rem;
  font-weight: 900;
  line-height: 1.2;
  margin-bottom: 1.25rem;
  color: #ffffff;
}

.auth-brand-desc {
  font-size: 0.92rem;
  color: #94a3b8;
  line-height: 1.7;
  margin-bottom: 2rem;
}

.auth-privilege-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 2.5rem;
}

.auth-privilege-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.85rem 1rem;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 1rem;
  transition: all 0.25s ease;
}

.auth-privilege-item:hover {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(56, 189, 248, 0.4);
  transform: translateX(4px);
}

.auth-privilege-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: rgba(2, 132, 199, 0.2);
  color: #38bdf8;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.auth-privilege-heading {
  font-size: 0.9rem;
  font-weight: 800;
  color: #ffffff;
}

.auth-privilege-text {
  font-size: 0.75rem;
  color: #94a3b8;
}

.auth-brand-footer {
  font-size: 0.78rem;
  color: #94a3b8;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* Sisi Kanan: Form */
.auth-form-side {
  padding: 3.5rem 3rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.auth-form-header {
  text-align: center;
  margin-bottom: 1.75rem;
}

.auth-logo-circle {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: linear-gradient(135deg, rgba(13, 148, 136, 0.15), rgba(2, 132, 199, 0.15));
  border: 1px solid rgba(13, 148, 136, 0.3);
  color: #0d9488;
  font-size: 1.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
}

.auth-form-title {
  font-size: 1.65rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.35rem;
}

.auth-form-subtitle {
  font-size: 0.85rem;
  color: #64748b;
}

.auth-alert {
  padding: 0.85rem 1.15rem;
  border-radius: 0.85rem;
  font-size: 0.85rem;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  line-height: 1.5;
}

.auth-alert-danger {
  background: #fee2e2;
  border: 1px solid #fecaca;
  color: #b91c1c;
}

.auth-alert-icon {
  font-size: 1.1rem;
  margin-top: 0.1rem;
  flex-shrink: 0;
}

.auth-form-group {
  margin-bottom: 1.15rem;
}

.auth-label {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #475569;
  margin-bottom: 0.35rem;
}

.auth-input-container {
  position: relative;
  display: flex;
  align-items: center;
}

.auth-input-icon {
  position: absolute;
  left: 1rem;
  color: #0284c7;
  font-size: 1rem;
  pointer-events: none;
}

.auth-input-field {
  width: 100%;
  padding: 0.8rem 2.8rem 0.8rem 2.75rem;
  background: #f8fafc;
  border: 1.5px solid #cbd5e1;
  border-radius: 0.85rem;
  font-size: 0.9rem;
  color: #0f172a;
  font-weight: 600;
  outline: none;
  transition: all 0.25s ease;
}

.auth-input-field:focus {
  background: #ffffff;
  border-color: #0284c7;
  box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.15);
}

.auth-password-toggle {
  position: absolute;
  right: 1rem;
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 1.1rem;
  cursor: pointer;
}

.auth-password-toggle:hover {
  color: #0f172a;
}

.password-meter-wrap {
  margin-top: 0.4rem;
}

.password-meter-bar {
  width: 100%;
  height: 5px;
  background: #e2e8f0;
  border-radius: 9999px;
  overflow: hidden;
  margin-bottom: 0.25rem;
}

.password-meter-fill {
  height: 100%;
  width: 0%;
  transition: width 0.3s ease, background-color 0.3s ease;
}

.password-meter-text {
  font-size: 0.72rem;
  font-weight: 700;
  color: #64748b;
}

.auth-hint-text {
  font-size: 0.72rem;
  color: #64748b;
  margin-top: 0.3rem;
  display: block;
}

.auth-extra-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.25rem;
}

.auth-checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  color: #475569;
  cursor: pointer;
  user-select: none;
}

.auth-submit-btn {
  width: 100%;
  padding: 0.95rem 1.5rem;
  background: linear-gradient(135deg, #0284c7 0%, #0d9488 100%);
  color: #ffffff;
  border: none;
  border-radius: 0.85rem;
  font-size: 0.95rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(2, 132, 199, 0.4);
  transition: all 0.25s ease;
}

.auth-submit-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(2, 132, 199, 0.55);
}

.auth-switch-box {
  text-align: center;
  font-size: 0.85rem;
  color: #64748b;
  margin-top: 1.5rem;
}

.auth-switch-link {
  color: #0284c7;
  text-decoration: none;
}

.auth-switch-link:hover {
  text-decoration: underline;
}

.auth-security-footnote {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  font-size: 0.72rem;
  color: #94a3b8;
  margin-top: 1.25rem;
  text-align: center;
}

@media (max-width: 960px) {
  .auth-luxury-card { grid-template-columns: 1fr; }
  .auth-brand-side { display: none; }
  .auth-form-side { padding: 2.5rem 1.75rem; }
  .grid-cols-2 { grid-template-columns: 1fr; }
}
</style>

<script>
  function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!input || !icon) return;

    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'fa-regular fa-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'fa-regular fa-eye';
    }
  }

  function checkRegPasswordStrength(val) {
    const fill = document.getElementById('regPasswordMeterFill');
    const text = document.getElementById('regPasswordMeterText');
    if (!fill || !text) return;

    if (!val || val.length === 0) {
      fill.style.width = '0%';
      text.innerText = 'Kekuatan Sandi: Belum diisi';
      text.style.color = '#64748b';
      return;
    }

    let score = 0;
    if (val.length >= 6) score += 25;
    if (val.length >= 8) score += 25;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score += 25;
    if (/[0-9]/.test(val) || /[^A-Za-z0-9]/.test(val)) score += 25;

    fill.style.width = score + '%';

    if (score <= 25) {
      fill.style.backgroundColor = '#ef4444';
      text.innerText = 'Kekuatan Sandi: Sangat Lemah (min 6 karakter)';
      text.style.color = '#ef4444';
    } else if (score <= 50) {
      fill.style.backgroundColor = '#f59e0b';
      text.innerText = 'Kekuatan Sandi: Cukup (tambahkan angka/simbol)';
      text.style.color = '#d97706';
    } else if (score <= 75) {
      fill.style.backgroundColor = '#3b82f6';
      text.innerText = 'Kekuatan Sandi: Bagus & Aman';
      text.style.color = '#2563eb';
    } else {
      fill.style.backgroundColor = '#10b981';
      text.innerText = 'Kekuatan Sandi: Sangat Kuat & Optimal';
      text.style.color = '#059669';
    }
    checkRegPasswordMatch();
  }

  function checkRegPasswordMatch() {
    const p1 = document.getElementById('regPassword');
    const p2 = document.getElementById('regConfirmPassword');
    const msg = document.getElementById('regPasswordMatchMsg');
    if (!p1 || !p2 || !msg) return;

    if (!p2.value) {
      msg.innerHTML = '';
      return;
    }

    if (p1.value === p2.value) {
      msg.innerHTML = '<span class="text-success font-bold"><i class="fa-solid fa-check"></i> Konfirmasi kata sandi cocok!</span>';
    } else {
      msg.innerHTML = '<span class="text-danger font-bold"><i class="fa-solid fa-triangle-exclamation"></i> Kata sandi belum cocok.</span>';
    }
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
