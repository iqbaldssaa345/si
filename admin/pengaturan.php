<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('admin');

$msg = '';
$msgType = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nama_sistem = trim($_POST['nama_sistem'] ?? '');
    $tagline = trim($_POST['tagline'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $kontak_telp = trim($_POST['kontak_telp'] ?? '');
    $kontak_email = trim($_POST['kontak_email'] ?? '');
    $sosmed_instagram = trim($_POST['sosmed_instagram'] ?? '');
    $sosmed_facebook = trim($_POST['sosmed_facebook'] ?? '');
    $sosmed_youtube = trim($_POST['sosmed_youtube'] ?? '');

    $cek = $pdo->query("SELECT id FROM pengaturan_web LIMIT 1")->fetch();

    if ($cek) {
        $upd = $pdo->prepare("UPDATE pengaturan_web SET 
            nama_sistem = ?, tagline = ?, deskripsi = ?, alamat = ?, kontak_telp = ?, kontak_email = ?, 
            sosmed_instagram = ?, sosmed_facebook = ?, sosmed_youtube = ?, updated_at = NOW() 
            WHERE id = ?");
        $upd->execute([
            $nama_sistem, $tagline, $deskripsi, $alamat, $kontak_telp, $kontak_email,
            $sosmed_instagram, $sosmed_facebook, $sosmed_youtube, $cek['id']
        ]);
    } else {
        $ins = $pdo->prepare("INSERT INTO pengaturan_web 
            (nama_sistem, tagline, deskripsi, alamat, kontak_telp, kontak_email, sosmed_instagram, sosmed_facebook, sosmed_youtube) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([
            $nama_sistem, $tagline, $deskripsi, $alamat, $kontak_telp, $kontak_email,
            $sosmed_instagram, $sosmed_facebook, $sosmed_youtube
        ]);
    }

    $msg = "Pengaturan website & identitas sistem berhasil diperbarui secara real-time!";
    $msgType = "success";
    $settings = getSettings($pdo); // refresh data terbaru
}

$pageTitle = "Pengaturan Website";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
  /* Custom Luxury Styling for Pengaturan */
  .settings-card-luxury {
    background: #ffffff;
    border-radius: 0.85rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    margin-bottom: 1.25rem;
    overflow: hidden;
    transition: all 0.2s ease;
  }
  .settings-card-luxury:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
  }
  .settings-card-header {
    padding: 0.85rem 1.15rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    background: #fafafa;
  }
  .settings-header-icon {
    width: 34px;
    height: 34px;
    border-radius: 0.55rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
  }
  .settings-card-body {
    padding: 1.15rem;
  }
  .luxury-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
  }
  .luxury-input-icon {
    position: absolute;
    left: 0.85rem;
    color: #94a3b8;
    font-size: 0.9rem;
    pointer-events: none;
    transition: color 0.2s ease;
  }
  .luxury-input {
    width: 100%;
    padding: 0.55rem 0.85rem 0.55rem 2.5rem;
    border: 1.5px solid #cbd5e1;
    border-radius: 0.6rem;
    font-size: 0.85rem;
    color: #1e293b;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 600;
    transition: all 0.2s ease;
    background: #ffffff;
    box-sizing: border-box;
  }
  .luxury-input:focus {
    outline: none;
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
    background: #ffffff;
  }
  .luxury-input:focus + .luxury-input-icon,
  .luxury-input-wrap:focus-within .luxury-input-icon {
    color: #0284c7;
  }
  .luxury-textarea {
    width: 100%;
    padding: 0.65rem 0.85rem;
    border: 1.5px solid #cbd5e1;
    border-radius: 0.6rem;
    font-size: 0.85rem;
    color: #1e293b;
    font-family: 'Plus Jakarta Sans', sans-serif;
    line-height: 1.5;
    transition: all 0.2s ease;
    background: #ffffff;
    resize: vertical;
    box-sizing: border-box;
  }
  .luxury-textarea:focus {
    outline: none;
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
  }
  .form-label-luxury {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.78rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 0.35rem;
  }
  .preview-glass-card {
    background: linear-gradient(145deg, #090d16 0%, #0f172a 50%, #1e293b 100%);
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
    padding: 1.25rem;
    color: #ffffff;
    position: relative;
    overflow: hidden;
  }
  .preview-glass-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(13, 148, 136, 0.2) 0%, transparent 70%);
    pointer-events: none;
  }
  .preview-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    padding: 0.25rem 0.6rem;
    border-radius: 0.5rem;
    color: #e2e8f0;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .unsaved-dot-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #f59e0b;
    display: inline-block;
    box-shadow: 0 0 8px #f59e0b;
    animation: pulse 1.5s infinite;
  }
</style>

<main class="admin-main">
  
  <!-- Topbar Luxury Header -->
  <div class="admin-topbar-luxury">
    <div>
      <div style="display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.15rem;">
        <span class="badge-luxury badge-luxury-primary">
          <span style="font-size: 0.85rem;">⚙️</span> Pusat Konfigurasi Global
        </span>
        <span style="font-size: 0.75rem; color: #16a34a; font-weight: 700; background: #dcfce7; padding: 0.15rem 0.55rem; border-radius: 9999px; border: 1px solid #bbf7d0; display: inline-flex; align-items: center; gap: 0.3rem;">
          <span style="width: 5px; height: 5px; border-radius: 50%; background: #16a34a; display: inline-block;"></span>
          Tersinkronisasi Real-Time
        </span>
      </div>
      <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Pengaturan Website & Identitas Platform 🌐
      </h1>
      <p style="font-size: 0.78rem; color: #64748b; margin: 0.1rem 0 0 0;">
        Sesuaikan nama sistem, slogan resmi, saluran kontak darurat, dan jejaring sosial Pesona Nusantara.
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
      <a href="<?= BASE_URL ?>index.php" target="_blank" class="btn btn-secondary btn-sm" style="font-weight: 700; border-radius: 0.55rem;" title="Buka Portal Wisata Publik">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> Cek Portal
      </a>
      <button type="submit" form="formPengaturan" class="btn btn-primary btn-sm" style="font-weight: 800; border-radius: 0.55rem; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);">
        <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan
      </button>
    </div>
  </div>

  <!-- Alert Flash Message -->
  <?php if ($msg): ?>
    <div style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 1px solid #6ee7b7; color: #065f46; padding: 0.75rem 1.15rem; border-radius: 0.75rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);">
      <div style="display: flex; align-items: center; gap: 0.65rem;">
        <span style="font-size: 1.25rem;">✨</span>
        <div>
          <strong style="font-size: 0.85rem; display: block;"><?= htmlspecialchars($msg) ?></strong>
          <span style="font-size: 0.72rem; color: #047857;">Perubahan telah diterapkan ke seluruh portal publik, navigasi, dan struk tiket wisata.</span>
        </div>
      </div>
      <button type="button" onclick="this.parentElement.remove()" style="background: transparent; border: none; font-size: 1rem; color: #047857; cursor: pointer;">&times;</button>
    </div>
  <?php endif; ?>

  <!-- Unsaved Changes Notice (Dynamic JS) -->
  <div id="unsavedNotice" style="display: none; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 0.55rem 1rem; border-radius: 0.65rem; margin-bottom: 1rem; font-size: 0.78rem; font-weight: 700; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 0.45rem;">
      <span class="unsaved-dot-pulse"></span>
      <span>Ada perubahan data yang belum disimpan. Tekan tombol <strong>"Simpan Pengaturan"</strong> untuk memperbarui sistem.</span>
    </div>
    <button type="submit" form="formPengaturan" class="btn btn-primary btn-xs" style="padding: 0.2rem 0.6rem; font-size: 0.72rem;">
      Simpan Sekarang
    </button>
  </div>

  <!-- Form Layout: 2 Columns Architecture -->
  <form id="formPengaturan" action="<?= BASE_URL ?>admin/pengaturan.php" method="POST">
    
    <div style="display: grid; grid-template-columns: 1.55fr 1fr; gap: 1.25rem; align-items: start;">
      
      <!-- LEFT COLUMN: Form Input Cards -->
      <div>

        <!-- CARD 1: Identitas & Branding Utama -->
        <div class="settings-card-luxury">
          <div class="settings-card-header">
            <div class="settings-header-icon" style="background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3);">
              <span>👑</span>
            </div>
            <div>
              <h3 style="font-size: 0.92rem; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Outfit', sans-serif;">
                Identitas & Branding Utama
              </h3>
              <p style="font-size: 0.72rem; color: #64748b; margin: 0.1rem 0 0 0;">
                Nama platform dan slogan resmi yang ditampilkan pada header portal, struk e-tiket, dan pencarian web.
              </p>
            </div>
          </div>

          <div class="settings-card-body">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
              
              <!-- Nama Sistem -->
              <div>
                <label class="form-label-luxury">
                  <span>Nama Website / Sistem <span style="color: #ef4444;">*</span></span>
                  <span style="font-size: 0.68rem; color: #0284c7; font-weight: 600;">Wajib Diisi</span>
                </label>
                <div class="luxury-input-wrap">
                  <span class="luxury-input-icon"><i class="fa-solid fa-crown text-amber"></i></span>
                  <input type="text" name="nama_sistem" id="inpNama" class="luxury-input" value="<?= htmlspecialchars($settings['nama_sistem'] ?? '') ?>" placeholder="Cth: Pesona Nusantara" required>
                </div>
              </div>

              <!-- Tagline Slogan -->
              <div>
                <label class="form-label-luxury">
                  <span>Tagline & Slogan Resmi</span>
                  <span style="font-size: 0.68rem; color: #64748b;">Motto Portal</span>
                </label>
                <div class="luxury-input-wrap">
                  <span class="luxury-input-icon"><i class="fa-solid fa-sparkles text-amber-500"></i></span>
                  <input type="text" name="tagline" id="inpTagline" class="luxury-input" value="<?= htmlspecialchars($settings['tagline'] ?? '') ?>" placeholder="Cth: Eksplorasi Keindahan Alam Indonesia">
                </div>
              </div>

            </div>

            <!-- Deskripsi Platform -->
            <div>
              <label class="form-label-luxury">
                <span>Deskripsi Singkat Platform</span>
                <span id="charCount" style="font-size: 0.68rem; color: #64748b;">0 karakter</span>
              </label>
              <textarea name="deskripsi" id="inpDeskripsi" rows="3" class="luxury-textarea" placeholder="Tuliskan gambaran ringkas visi dan layanan pariwisata yang diberikan kepada wisatawan..."><?= htmlspecialchars($settings['deskripsi'] ?? '') ?></textarea>
              <span style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.25rem; display: block;">
                Teks deskripsi ini digunakan untuk meta deskripsi SEO dan penjelasan singkat di footer website.
              </span>
            </div>

          </div>
        </div>

        <!-- CARD 2: Informasi Kontak & Kantor Operasional -->
        <div class="settings-card-luxury">
          <div class="settings-card-header">
            <div class="settings-header-icon" style="background: rgba(13, 148, 136, 0.15); color: #0d9488; border: 1px solid rgba(13, 148, 136, 0.3);">
              <span>🏢</span>
            </div>
            <div>
              <h3 style="font-size: 0.92rem; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Outfit', sans-serif;">
                Saluran Kontak & Kantor Pusat
              </h3>
              <p style="font-size: 0.72rem; color: #64748b; margin: 0.1rem 0 0 0;">
                Kontak resmi untuk konfirmasi pembayaran tiket, bantuan customer service, dan alamat kantor layanan.
              </p>
            </div>
          </div>

          <div class="settings-card-body">
            
            <!-- Alamat Kantor -->
            <div style="margin-bottom: 0.85rem;">
              <label class="form-label-luxury">
                <span>Alamat Kantor Pusat / Loket Sekretariat</span>
              </label>
              <div class="luxury-input-wrap">
                <span class="luxury-input-icon"><i class="fa-solid fa-location-dot text-rose-500"></i></span>
                <input type="text" name="alamat" id="inpAlamat" class="luxury-input" value="<?= htmlspecialchars($settings['alamat'] ?? '') ?>" placeholder="Cth: Jl. Raya Pariwisata No. 88, Denpasar, Bali">
              </div>
            </div>

            <!-- Grid Kontak -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
              
              <div>
                <label class="form-label-luxury">
                  <span>Nomor Telepon / WhatsApp Resmi</span>
                </label>
                <div class="luxury-input-wrap">
                  <span class="luxury-input-icon"><i class="fa-brands fa-whatsapp text-emerald-600"></i></span>
                  <input type="text" name="kontak_telp" id="inpTelp" class="luxury-input" value="<?= htmlspecialchars($settings['kontak_telp'] ?? '') ?>" placeholder="Cth: +62 821-9988-7766">
                </div>
              </div>

              <div>
                <label class="form-label-luxury">
                  <span>Email Bantuan & Pengaduan</span>
                </label>
                <div class="luxury-input-wrap">
                  <span class="luxury-input-icon"><i class="fa-solid fa-envelope text-sky-600"></i></span>
                  <input type="email" name="kontak_email" id="inpEmail" class="luxury-input" value="<?= htmlspecialchars($settings['kontak_email'] ?? '') ?>" placeholder="Cth: support@pesonanusantara.id">
                </div>
              </div>

            </div>

          </div>
        </div>

        <!-- CARD 3: Ekosistem Sosial Media Resmi -->
        <div class="settings-card-luxury">
          <div class="settings-card-header">
            <div class="settings-header-icon" style="background: rgba(2, 132, 199, 0.15); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.3);">
              <span>🌐</span>
            </div>
            <div>
              <h3 style="font-size: 0.92rem; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Outfit', sans-serif;">
                Jejaring Sosial Media Resmi
              </h3>
              <p style="font-size: 0.72rem; color: #64748b; margin: 0.1rem 0 0 0;">
                Tautkan akun sosial media pariwisata agar pengunjung dapat mengikuti kabar promo tiket dan festival terbaru.
              </p>
            </div>
          </div>

          <div class="settings-card-body">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.85rem;">
              
              <!-- Instagram -->
              <div>
                <label class="form-label-luxury">
                  <span>Instagram</span>
                </label>
                <div class="luxury-input-wrap">
                  <span class="luxury-input-icon"><i class="fa-brands fa-instagram" style="color: #e1306c;"></i></span>
                  <input type="text" name="sosmed_instagram" id="inpIg" class="luxury-input" value="<?= htmlspecialchars($settings['sosmed_instagram'] ?? '') ?>" placeholder="pesonanusantara">
                </div>
              </div>

              <!-- Facebook -->
              <div>
                <label class="form-label-luxury">
                  <span>Facebook</span>
                </label>
                <div class="luxury-input-wrap">
                  <span class="luxury-input-icon"><i class="fa-brands fa-facebook" style="color: #1877f2;"></i></span>
                  <input type="text" name="sosmed_facebook" id="inpFb" class="luxury-input" value="<?= htmlspecialchars($settings['sosmed_facebook'] ?? '') ?>" placeholder="pesonanusantara.id">
                </div>
              </div>

              <!-- YouTube -->
              <div>
                <label class="form-label-luxury">
                  <span>YouTube</span>
                </label>
                <div class="luxury-input-wrap">
                  <span class="luxury-input-icon"><i class="fa-brands fa-youtube" style="color: #ff0000;"></i></span>
                  <input type="text" name="sosmed_youtube" id="inpYt" class="luxury-input" value="<?= htmlspecialchars($settings['sosmed_youtube'] ?? '') ?>" placeholder="PesonaNusantaraTV">
                </div>
              </div>

            </div>

          </div>
        </div>

        <!-- Submit Button Bottom Action Bar -->
        <div style="display: flex; align-items: center; justify-content: space-between; background: #ffffff; padding: 1rem 1.25rem; border-radius: 0.85rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 4px rgba(0,0,0,0.02);">
          <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.76rem; color: #64748b;">
            <i class="fa-solid fa-shield-halved text-emerald-600" style="font-size: 1rem;"></i>
            <span>Setiap perubahan pengaturan tersimpan permanen dan tercatat di log sistem.</span>
          </div>
          <button type="submit" class="btn btn-primary" style="font-weight: 800; padding: 0.6rem 1.5rem; border-radius: 0.65rem; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4); display: inline-flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan Pengaturan
          </button>
        </div>

      </div>

      <!-- RIGHT COLUMN: Interactive Live Preview & Metadata -->
      <div>
        
        <!-- CARD 1: Live Mockup Card Preview -->
        <div style="background: #ffffff; border-radius: 0.85rem; border: 1px solid #e2e8f0; padding: 1rem; margin-bottom: 1.25rem; box-shadow: 0 1px 4px rgba(0,0,0,0.02);">
          
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
            <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #0284c7; display: flex; align-items: center; gap: 0.35rem;">
              <span style="width: 7px; height: 7px; border-radius: 50%; background: #0284c7; display: inline-block;"></span>
              Live Pratinjau Identitas
            </span>
            <span style="font-size: 0.68rem; color: #64748b; background: #f1f5f9; padding: 0.15rem 0.5rem; border-radius: 9999px;">
              Real-Time
            </span>
          </div>

          <!-- The Glassmorphism Mockup Card -->
          <div class="preview-glass-card">
            
            <!-- Brand Badge Row -->
            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.85rem;">
              <div style="width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.25) 0%, rgba(217, 119, 6, 0.15) 100%); border: 1px solid rgba(245, 158, 11, 0.4); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; box-shadow: 0 0 14px rgba(245, 158, 11, 0.3); flex-shrink: 0;">
                <span>👑</span>
              </div>
              <div style="overflow: hidden; flex: 1;">
                <h4 id="prevNama" style="font-size: 1.05rem; font-weight: 800; color: #ffffff; margin: 0; font-family: 'Outfit', sans-serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                  <?= htmlspecialchars($settings['nama_sistem'] ?? 'Pesona Nusantara') ?>
                </h4>
                <span id="prevTagline" style="display: block; font-size: 0.7rem; color: #38bdf8; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                  <?= htmlspecialchars($settings['tagline'] ?? 'Jelajahi Pesona Keindahan Wisata Terbaik di Indonesia') ?>
                </span>
              </div>
            </div>

            <!-- Description Box -->
            <p id="prevDeskripsi" style="font-size: 0.75rem; color: #cbd5e1; line-height: 1.45; margin: 0 0 0.85rem 0; background: rgba(255, 255, 255, 0.04); padding: 0.55rem 0.75rem; border-radius: 0.55rem; border: 1px solid rgba(255, 255, 255, 0.07);">
              <?= htmlspecialchars($settings['deskripsi'] ?? 'Platform informasi dan pemesanan tiket wisata terintegrasi dan berkelas.') ?>
            </p>

            <!-- Contact List Preview -->
            <div style="display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 0.85rem;">
              <div class="preview-pill">
                <i class="fa-solid fa-location-dot text-rose-400"></i>
                <span id="prevAlamat"><?= htmlspecialchars($settings['alamat'] ?? 'Jl. Pariwisata No. 88, Indonesia') ?></span>
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.35rem;">
                <div class="preview-pill">
                  <i class="fa-brands fa-whatsapp text-emerald-400"></i>
                  <span id="prevTelp"><?= htmlspecialchars($settings['kontak_telp'] ?? '+62 821-9988-7766') ?></span>
                </div>
                <div class="preview-pill">
                  <i class="fa-solid fa-envelope text-sky-400"></i>
                  <span id="prevEmail"><?= htmlspecialchars($settings['kontak_email'] ?? 'kontak@wisata.id') ?></span>
                </div>
              </div>
            </div>

            <!-- Social Media Handles Preview -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 0.65rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.68rem; color: #94a3b8;">
              <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
                <i class="fa-brands fa-instagram text-pink-400"></i> <span id="prevIg">@<?= htmlspecialchars($settings['sosmed_instagram'] ?? 'pesonanusantara') ?></span>
              </span>
              <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
                <i class="fa-brands fa-facebook text-blue-400"></i> <span id="prevFb"><?= htmlspecialchars($settings['sosmed_facebook'] ?? 'official') ?></span>
              </span>
              <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
                <i class="fa-brands fa-youtube text-red-400"></i> <span id="prevYt"><?= htmlspecialchars($settings['sosmed_youtube'] ?? 'PesonaTV') ?></span>
              </span>
            </div>

          </div>

          <div style="margin-top: 0.65rem; font-size: 0.7rem; color: #64748b; text-align: center;">
            Pratinjau visual di atas berubah otomatis secara langsung saat Anda mengetik data.
          </div>

        </div>

        <!-- CARD 2: Cakupan Sinkronisasi Data -->
        <div style="background: #ffffff; border-radius: 0.85rem; border: 1px solid #e2e8f0; padding: 1.15rem; margin-bottom: 1.25rem; box-shadow: 0 1px 4px rgba(0,0,0,0.02);">
          
          <h3 style="font-size: 0.88rem; font-weight: 800; color: #0f172a; margin: 0 0 0.65rem 0; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 0.4rem;">
            <span>🛡️</span> Dampak Konfigurasi Global
          </h3>

          <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.55rem; font-size: 0.76rem; color: #475569;">
            <li style="display: flex; align-items: center; gap: 0.5rem;">
              <i class="fa-solid fa-circle-check text-emerald-600" style="font-size: 0.85rem;"></i>
              <span><strong>Navbar Publik:</strong> Logo crown, judul sistem, dan tautan kontak langsung.</span>
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem;">
              <i class="fa-solid fa-circle-check text-emerald-600" style="font-size: 0.85rem;"></i>
              <span><strong>E-Tiket & Struk Invoice:</strong> Keterangan penerbit tiket dan narahubung resmi.</span>
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem;">
              <i class="fa-solid fa-circle-check text-emerald-600" style="font-size: 0.85rem;"></i>
              <span><strong>Footer Portal:</strong> Hak cipta resmi, info kantor, dan sosial media.</span>
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem;">
              <i class="fa-solid fa-circle-check text-emerald-600" style="font-size: 0.85rem;"></i>
              <span><strong>Sidebar Panel:</strong> Brand header pada Admin, Petugas, dan Pengunjung.</span>
            </li>
          </ul>

        </div>

        <!-- CARD 3: Info Lingkungan & Server -->
        <div style="background: #f8fafc; border-radius: 0.85rem; border: 1px solid #e2e8f0; padding: 1rem; font-size: 0.72rem; color: #64748b;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
            <span>Arsitektur Sistem:</span>
            <strong style="color: #0f172a;">Pesona Nusantara Enterprise v2.4</strong>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
            <span>Mesin Database:</span>
            <strong style="color: #0f172a;">MySQL PDO UTF8MB4</strong>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
            <span>Runtime Engine:</span>
            <strong style="color: #0f172a;">PHP <?= phpversion() ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Waktu Sinkronisasi:</span>
            <strong style="color: #0f172a;"><?= date('d M Y, H:i') ?> WIB</strong>
          </div>
        </div>

      </div>

    </div>

  </form>

</main>

<script>
  // Live Interactive Preview Binding
  const inpNama = document.getElementById('inpNama');
  const inpTagline = document.getElementById('inpTagline');
  const inpDeskripsi = document.getElementById('inpDeskripsi');
  const inpAlamat = document.getElementById('inpAlamat');
  const inpTelp = document.getElementById('inpTelp');
  const inpEmail = document.getElementById('inpEmail');
  const inpIg = document.getElementById('inpIg');
  const inpFb = document.getElementById('inpFb');
  const inpYt = document.getElementById('inpYt');

  const prevNama = document.getElementById('prevNama');
  const prevTagline = document.getElementById('prevTagline');
  const prevDeskripsi = document.getElementById('prevDeskripsi');
  const prevAlamat = document.getElementById('prevAlamat');
  const prevTelp = document.getElementById('prevTelp');
  const prevEmail = document.getElementById('prevEmail');
  const prevIg = document.getElementById('prevIg');
  const prevFb = document.getElementById('prevFb');
  const prevYt = document.getElementById('prevYt');

  const charCount = document.getElementById('charCount');
  const unsavedNotice = document.getElementById('unsavedNotice');

  function updateCharCounter() {
    if (inpDeskripsi && charCount) {
      charCount.textContent = `${inpDeskripsi.value.length} karakter`;
    }
  }

  function triggerUnsaved() {
    if (unsavedNotice) {
      unsavedNotice.style.display = 'flex';
    }
  }

  // Bind inputs
  if (inpNama) {
    inpNama.addEventListener('input', function() {
      prevNama.textContent = this.value.trim() || 'Pesona Nusantara';
      triggerUnsaved();
    });
  }

  if (inpTagline) {
    inpTagline.addEventListener('input', function() {
      prevTagline.textContent = this.value.trim() || 'Jelajahi Pesona Keindahan Wisata Terbaik di Indonesia';
      triggerUnsaved();
    });
  }

  if (inpDeskripsi) {
    inpDeskripsi.addEventListener('input', function() {
      prevDeskripsi.textContent = this.value.trim() || 'Platform informasi dan pemesanan tiket wisata terintegrasi dan berkelas.';
      updateCharCounter();
      triggerUnsaved();
    });
  }

  if (inpAlamat) {
    inpAlamat.addEventListener('input', function() {
      prevAlamat.textContent = this.value.trim() || 'Jl. Pariwisata No. 88, Indonesia';
      triggerUnsaved();
    });
  }

  if (inpTelp) {
    inpTelp.addEventListener('input', function() {
      prevTelp.textContent = this.value.trim() || '+62 821-9988-7766';
      triggerUnsaved();
    });
  }

  if (inpEmail) {
    inpEmail.addEventListener('input', function() {
      prevEmail.textContent = this.value.trim() || 'kontak@wisata.id';
      triggerUnsaved();
    });
  }

  if (inpIg) {
    inpIg.addEventListener('input', function() {
      prevIg.textContent = this.value.trim() ? `@${this.value.trim()}` : '@pesonanusantara';
      triggerUnsaved();
    });
  }

  if (inpFb) {
    inpFb.addEventListener('input', function() {
      prevFb.textContent = this.value.trim() || 'official';
      triggerUnsaved();
    });
  }

  if (inpYt) {
    inpYt.addEventListener('input', function() {
      prevYt.textContent = this.value.trim() || 'PesonaTV';
      triggerUnsaved();
    });
  }

  // Init
  updateCharCounter();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
