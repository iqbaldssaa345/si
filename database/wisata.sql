-- =======================================================
-- SQL SCHEMA & DATA SEEDER LENGKAP: SISTEM INFORMASI WISATA (9 TABEL)
-- Database: wisata
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =======================================================

CREATE DATABASE IF NOT EXISTS `wisata` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `wisata`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `transaksi_log`;
DROP TABLE IF EXISTS `presensi_kunjungan`;
DROP TABLE IF EXISTS `pemesanan`;
DROP TABLE IF EXISTS `ulasan`;
DROP TABLE IF EXISTS `destinasi_foto`;
DROP TABLE IF EXISTS `destinasi`;
DROP TABLE IF EXISTS `kategori_wisata`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `pengaturan_web`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- 1. TABEL USERS (Admin, Petugas Loket/Lapangan, Pengunjung)
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `no_telp` VARCHAR(30) DEFAULT NULL,
  `role` ENUM('admin', 'petugas', 'pengunjung') NOT NULL DEFAULT 'pengunjung',
  `foto` VARCHAR(255) DEFAULT 'default_avatar.png',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. TABEL KATEGORI WISATA
-- --------------------------------------------------------
CREATE TABLE `kategori_wisata` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kategori` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL UNIQUE,
  `icon` VARCHAR(50) DEFAULT 'fa-tree',
  `deskripsi` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. TABEL DESTINASI WISATA
-- --------------------------------------------------------
CREATE TABLE `destinasi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kategori_id` INT NOT NULL,
  `nama_destinasi` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(180) NOT NULL UNIQUE,
  `deskripsi` TEXT NOT NULL,
  `lokasi` VARCHAR(255) NOT NULL,
  `maps_embed` TEXT DEFAULT NULL,
  `harga_tiket` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `diskon_rombongan` INT DEFAULT 15 COMMENT 'Diskon persen untuk rombongan',
  `min_rombongan` INT DEFAULT 10 COMMENT 'Minimal pengunjung untuk rombongan',
  `jam_buka` TIME DEFAULT '08:00:00',
  `jam_tutup` TIME DEFAULT '17:00:00',
  `hari_operasional` VARCHAR(100) DEFAULT 'Setiap Hari (Senin - Minggu)',
  `fasilitas` TEXT DEFAULT NULL COMMENT 'Comma-separated fasilitas',
  `status` ENUM('buka', 'tutup', 'pemeliharaan') DEFAULT 'buka',
  `rating` DECIMAL(3,2) DEFAULT 4.80,
  `foto_utama` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`kategori_id`) REFERENCES `kategori_wisata`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. TABEL DESTINASI FOTO (Galeri Tambahan)
-- --------------------------------------------------------
CREATE TABLE `destinasi_foto` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `destinasi_id` INT NOT NULL,
  `foto_url` VARCHAR(255) NOT NULL,
  `caption` VARCHAR(150) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`destinasi_id`) REFERENCES `destinasi`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. TABEL ULASAN (Rating & Komentar Pengunjung)
-- --------------------------------------------------------
CREATE TABLE `ulasan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `destinasi_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `rating` INT NOT NULL DEFAULT 5,
  `komentar` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`destinasi_id`) REFERENCES `destinasi`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. TABEL PEMESANAN (Transaksi Tiket Pengunjung)
-- --------------------------------------------------------
CREATE TABLE `pemesanan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_booking` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `destinasi_id` INT NOT NULL,
  `tanggal_kunjungan` DATE NOT NULL,
  `tipe_rombongan` ENUM('sendiri', 'rombongan') NOT NULL DEFAULT 'sendiri',
  `jumlah_tiket` INT NOT NULL DEFAULT 1,
  `harga_satuan` DECIMAL(12,2) NOT NULL,
  `diskon_didapat` DECIMAL(12,2) DEFAULT 0.00,
  `total_bayar` DECIMAL(12,2) NOT NULL,
  `status_bayar` ENUM('pending', 'lunas', 'batal') NOT NULL DEFAULT 'pending',
  `metode_pembayaran` VARCHAR(50) DEFAULT 'QRIS',
  `bukti_bayar` VARCHAR(255) DEFAULT NULL,
  `qr_code` VARCHAR(255) DEFAULT NULL,
  `status_kunjungan` ENUM('belum_digunakan', 'sudah_digunakan', 'hangus') DEFAULT 'belum_digunakan',
  `waktu_checkin` DATETIME DEFAULT NULL,
  `nama_pemesan` VARCHAR(100) DEFAULT NULL,
  `no_telp` VARCHAR(30) DEFAULT NULL,
  `catatan` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`destinasi_id`) REFERENCES `destinasi`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. TABEL PRESENSI KUNJUNGAN (Input Petugas Lapangan)
-- --------------------------------------------------------
CREATE TABLE `presensi_kunjungan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `petugas_id` INT NOT NULL,
  `destinasi_id` INT NOT NULL,
  `tanggal_kunjungan` DATE NOT NULL,
  `hari` VARCHAR(20) NOT NULL,
  `jumlah_pengunjung` INT NOT NULL,
  `jenis_kunjungan` ENUM('sendiri', 'rombongan') NOT NULL DEFAULT 'sendiri',
  `keterangan` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`petugas_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`destinasi_id`) REFERENCES `destinasi`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. TABEL TRANSAKSI LOG (Audit Log Pembayaran)
-- --------------------------------------------------------
CREATE TABLE `transaksi_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pemesanan_id` INT NOT NULL,
  `nominal` DECIMAL(12,2) NOT NULL,
  `metode` VARCHAR(50) NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `waktu_bayar` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `catatan` TEXT DEFAULT NULL,
  FOREIGN KEY (`pemesanan_id`) REFERENCES `pemesanan`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. TABEL PENGATURAN WEB (Profil & Info Sistem)
-- --------------------------------------------------------
CREATE TABLE `pengaturan_web` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_sistem` VARCHAR(100) NOT NULL DEFAULT 'Pesona Nusantara',
  `tagline` VARCHAR(200) DEFAULT 'Sistem Informasi & Reservasi Wisata Berkelas Indonesia',
  `deskripsi` TEXT DEFAULT NULL,
  `kontak_email` VARCHAR(100) DEFAULT 'kontak@pesonanusantara.id',
  `kontak_telp` VARCHAR(30) DEFAULT '+62 821-9988-7766',
  `alamat` TEXT DEFAULT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `banner` VARCHAR(255) DEFAULT NULL,
  `sosmed_instagram` VARCHAR(100) DEFAULT 'pesonanusantara.id',
  `sosmed_facebook` VARCHAR(100) DEFAULT 'pesonanusantara.official',
  `sosmed_youtube` VARCHAR(100) DEFAULT 'PesonaNusantaraTV',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- DATA SEEDER SUPER LENGKAP & BERKELAS (BCRYPT TERVERIFIKASI)
-- ========================================================

-- 1. SEED USERS
-- Admin: admin123
-- Petugas: petugas123
-- Pengunjung: user123
INSERT INTO `users` (`id`, `nama`, `email`, `password`, `no_telp`, `role`, `foto`) VALUES
(1, 'Administrator Utama', 'admin@wisata.com', '$2y$10$F5nYapE8/hC9wP2vDXf3Ae3wu6dGUpFxiqn7naAzL43WZtHti9PCm', '081234567890', 'admin', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150'),
(2, 'Budi Santoso (Petugas Loket Bromo)', 'petugas@wisata.com', '$2y$10$iBiRhuN1nZ8Z.dsE66ic4.akk9wUtOnB9gy29Qt3XpV.FhGd4WNDy', '082345678901', 'petugas', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150'),
(3, 'Rina Anggraini (Petugas Loket Bali)', 'petugas2@wisata.com', '$2y$10$iBiRhuN1nZ8Z.dsE66ic4.akk9wUtOnB9gy29Qt3XpV.FhGd4WNDy', '082345678902', 'petugas', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150'),
(4, 'Hendro Wibowo (Petugas Borobudur)', 'petugas3@wisata.com', '$2y$10$iBiRhuN1nZ8Z.dsE66ic4.akk9wUtOnB9gy29Qt3XpV.FhGd4WNDy', '082345678903', 'petugas', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150'),
(5, 'Dimas Pratama (Wisatawan VIP)', 'user@wisata.com', '$2y$10$3GSlAaAjr.4fS3FGFwQ4HuugCfdVJDn/e6NFfFSpB0oAhwEOKf6RG', '083456789012', 'pengunjung', 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=150'),
(6, 'Siti Rahmawati (Tour Leader)', 'siti@gmail.com', '$2y$10$3GSlAaAjr.4fS3FGFwQ4HuugCfdVJDn/e6NFfFSpB0oAhwEOKf6RG', '085678901234', 'pengunjung', 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=150'),
(7, 'Ahmad Fauzi (Traveler)', 'ahmad@yahoo.com', '$2y$10$3GSlAaAjr.4fS3FGFwQ4HuugCfdVJDn/e6NFfFSpB0oAhwEOKf6RG', '081298765432', 'pengunjung', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=150'),
(8, 'Jessica Tanujaya (Family Trip)', 'jessica@gmail.com', '$2y$10$3GSlAaAjr.4fS3FGFwQ4HuugCfdVJDn/e6NFfFSpB0oAhwEOKf6RG', '087812345678', 'pengunjung', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150');

-- 2. SEED KATEGORI WISATA
INSERT INTO `kategori_wisata` (`id`, `nama_kategori`, `slug`, `icon`, `deskripsi`) VALUES
(1, 'Wisata Alam & Pegunungan', 'wisata-alam', 'fa-mountain-sun', 'Eksplorasi panorama perbukitan sejuk, kawah vulkanik megah, dan hutan pinus asri.'),
(2, 'Bahari & Pantai Eksotis', 'bahari-pantai', 'fa-umbrella-beach', 'Pesona hamparan pasir putih, air laut toska, terumbu karang, dan sunset memukau.'),
(3, 'Budaya & Sejarah', 'budaya-sejarah', 'fa-landmark-dome', 'Kemegahan candi warisan dunia, keraton megah, museum arsitektur purba, dan tradisi leluhur.'),
(4, 'Taman Hiburan & Rekreasi', 'taman-hiburan', 'fa-ferris-wheel', 'Wahana seru keluarga, waterpark modern kelas dunia, dan atraksi karnaval.'),
(5, 'Edukasi & Konservasi', 'edukasi-konservasi', 'fa-leaf', 'Taman safari satwa liar, kebun raya botani, penangkaran satwa langka, dan agrowisata terpadu.');

-- 3. SEED DESTINASI WISATA (10 DESTINASI LENGKAP)
INSERT INTO `destinasi` (`id`, `kategori_id`, `nama_destinasi`, `slug`, `deskripsi`, `lokasi`, `maps_embed`, `harga_tiket`, `diskon_rombongan`, `min_rombongan`, `jam_buka`, `jam_tutup`, `hari_operasional`, `fasilitas`, `status`, `rating`, `foto_utama`) VALUES
(1, 1, 'Gunung Bromo Sunrise Panorama', 'gunung-bromo-sunrise-panorama', 'Gunung Bromo menawarkan pemandangan spektakuler dengan kaldera tengger yang ikonik, lautan pasir berbisik, serta momen sunrise emas terbaik di Asia Tenggara. Dilengkapi dengan fasilitas jeep tour dan spot foto eksklusif.', 'Taman Nasional Bromo Tengger Semeru, Jawa Timur', '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3953.0783178229864!2d112.94639907476595!3d-7.942493579116851!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2dd637aaab7f7343%3A0x6a2c2ef975988114!2sMount%20Bromo!5e0!3m2!1sen!2sid!4v1700000000000!5m2!1sen!2sid" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy"></iframe>', 35000.00, 15, 10, '03:00:00', '18:00:00', 'Setiap Hari (Senin - Minggu)', 'Parkir Luas,Toilet VIP,Musholla,Jeep Tour 4x4,Restoran & Kafe,Spot Foto Instagramable,Pusat Oleh-oleh,Gazebo', 'buka', 4.95, 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?w=800'),
(2, 2, 'Pantai Kelingking Nusa Penida', 'pantai-kelingking-nusa-penida', 'Dikenal sebagai T-Rex Cliff karena tebing kapur purba yang menyerupai dinosaurus raksasa dengan latar birunya samudra Hindia yang sangat jernih. Salah satu destinasi pantai terindah dan paling viral di dunia.', 'Nusa Penida, Klungkung, Bali', '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3942.876251842886!2d115.48831007477759!3d-8.750519391300067!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2dc3763f915729d7%3A0x334460f38b15d2a6!2sKelingking%20Beach!5e0!3m2!1sen!2sid!4v1700000000001!5m2!1sen!2sid" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy"></iframe>', 50000.00, 20, 8, '06:00:00', '18:30:00', 'Setiap Hari (Senin - Minggu)', 'Pemandu Lokal,Restoran Tepi Tebing,Area Foto Deck,Penyewaan Motor,Toilet Bersih,Toko Souvenir Bali', 'buka', 4.98, 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=800'),
(3, 3, 'Candi Borobudur Heritage', 'candi-borobudur-heritage', 'Monumen Buddha terbesar di dunia yang merupakan warisan agung UNESCO abad ke-8. Dikelilingi panorama perbukitan Menoreh nan asri dengan relief sejarah bernilai seni tinggi.', 'Magelang, Jawa Tengah', '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3954.7972049681804!2d110.20150997476395!3d-7.607873775200236!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e7a8cf009a7d361%3A0x9d123d3208e64e!2sBorobudur%20Temple!5e0!3m2!1sen!2sid!4v1700000000002!5m2!1sen!2sid" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy"></iframe>', 75000.00, 10, 15, '07:00:00', '17:00:00', 'Setiap Hari (Senin - Minggu)', 'Audio Guide Multilingual,Mobil Listrik Ramah Lingkungan,Museum Arkeologi,Food Court,Jalur Kursi Roda', 'buka', 4.90, 'https://images.unsplash.com/photo-1596402184320-417e7178b2cd?w=800'),
(4, 4, 'Batu Night Spectacular Theme Park', 'batu-night-spectacular', 'Taman hiburan malam spektakuler dengan ratusan lampion artistik, wahana adrenalin tinggi, laser show memukau, bioskop 4D, dan karnaval malam ceria untuk seluruh anggota keluarga.', 'Kota Batu, Jawa Timur', '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3952.1965876378454!2d112.5283431747669!3d-7.891147578500206!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e78873099955555%3A0xa6468494025a1768!2sBatu%20Night%20Spectacular!5e0!3m2!1sen!2sid!4v1700000000003!5m2!1sen!2sid" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy"></iframe>', 45000.00, 15, 10, '15:00:00', '23:00:00', 'Setiap Hari (Senin - Minggu)', 'Wahana Anak & Dewasa,Food Garden,Lampion Garden,Panggung Musik Live,Area Parkir Bertingkat,Klinik P3K', 'buka', 4.80, 'https://images.unsplash.com/photo-1513889961551-628c1e5e2ee9?w=800'),
(5, 5, 'Taman Safari Konservasi Alam Nusantara', 'taman-safari-konservasi-alam', 'Kawasan konservasi fauna seluas ratusan hektar di mana Anda dapat berinteraksi langsung dengan ribuan satwa liar dari 5 benua dalam habitat alaminya, didampingi pertunjukan edukasi ramah anak.', 'Cisarua, Bogor, Jawa Barat', '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3963.023245464522!2d106.94523317475535!3d-6.643981864947936!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69b616ec480ab3%3A0xb30f890cfb6c0032!2sTaman%20Safari%20Indonesia%20Bogor!5e0!3m2!1sen!2sid!4v1700000000004!5m2!1sen!2sid" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy"></iframe>', 120000.00, 20, 12, '08:30:00', '17:00:00', 'Setiap Hari (Senin - Minggu)', 'Safari Bus Ber-AC,Baby Zoo,Waterpark Anak,Kereta Gantung,Restoran Rimba,Masjid Terpadu,Hotel Resort', 'buka', 4.93, 'https://images.unsplash.com/photo-1534567153574-2b12153a87f0?w=800'),
(6, 1, 'Kawah Putih Ciwidey Exotic Crater', 'kawah-putih-ciwidey', 'Danau kawah vulkanik dengan air berwarna putih kehijauan yang memukau dikelilingi hutan cantigi yang magis. Suhu udara sejuk pegunungan memberikan ketenangan sempurna.', 'Ciwidey, Bandung, Jawa Barat', '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3958.8247941094056!2d107.4001923747599!3d-7.166978470356515!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e688c227bf7c5bf%3A0xd64197e937d97607!2sKawah%20Putih!5e0!3m2!1sen!2sid!4v1700000000005!5m2!1sen!2sid" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy"></iframe>', 40000.00, 15, 10, '07:30:00', '17:00:00', 'Setiap Hari (Senin - Minggu)', 'Ontang-anting (Shuttle),Jembatan Apung,Skywalk Cantigi,Musholla,Warung Kuliner Hangat,Area Parkir', 'buka', 4.85, 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=800'),
(7, 2, 'Raja Ampat Wayag Lagoon Paradise', 'raja-ampat-wayag-lagoon', 'Gugusan pulau karang atol berbentuk kerucut di tengah air laut toska jernih. Surga bawah laut terkaya di planet bumi dengan keanekaragaman terumbu karang dan biota laut tiada tara.', 'Raja Ampat, Papua Barat Daya', '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.816655512211!2d130.01666677472064!3d-0.15000004999999998!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2d5c4146a86f9f3f%3A0x868c2d6dc0bb8ec4!2sWayag%20Island!5e0!3m2!1sen!2sid!4v1700000000006!5m2!1sen!2sid" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy"></iframe>', 250000.00, 15, 6, '06:00:00', '18:00:00', 'Setiap Hari (Senin - Minggu)', 'Speedboat Tour,Peralatan Snorkeling & Diving,Pemandu Berlisensi,Dermaga Apung,Pos Informasi Konservasi', 'buka', 5.00, 'https://images.unsplash.com/photo-1516690561799-46d8f74f9abf?w=800'),
(8, 3, 'Candi Prambanan Megah Hindu Heritage', 'candi-prambanan-heritage', 'Kompleks candi Hindu terindah dan terbesar di Indonesia dengan arsitektur ramping menjulang setinggi 47 meter yang didedikasikan untuk Trimurti. Dilengkapi panggung megah Ramayana Ballet.', 'Sleman, D.I. Yogyakarta', '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3953.3551528615024!2d110.48927877476562!3d-7.752020579177579!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e7a5ae3dbd850d9%3A0x5a5078512190b4d4!2sPrambanan%20Temple!5e0!3m2!1sen!2sid!4v1700000000007!5m2!1sen!2sid" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy"></iframe>', 50000.00, 15, 10, '06:30:00', '17:00:00', 'Setiap Hari (Senin - Minggu)', 'Panggung Ramayana,Taman Rindang,Area Golf Car,Museum Sejarah,Pusat Kerajinan Perak & Batik', 'buka', 4.88, 'https://images.unsplash.com/photo-1578632767115-351597cf2477?w=800'),
(9, 2, 'Labuan Bajo & Pulau Padar Komodo', 'labuan-bajo-pulau-padar', 'Bukit ikonik dengan pemandangan 3 teluk berpasir warna merah muda, putih, dan abu-abu arang. Gerbang menuju habitat asli kadal purba komodo di perairan jernih Flores.', 'Manggarai Barat, Nusa Tenggara Timur', '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3946.064115456487!2d119.57053507477382!3d-8.63782759141019!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2db4677943d03cb9%3A0x7c73336717a61d15!2sPadar%20Island!5e0!3m2!1sen!2sid!4v1700000000008!5m2!1sen!2sid" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy"></iframe>', 150000.00, 15, 8, '05:30:00', '18:00:00', 'Setiap Hari (Senin - Minggu)', 'Trekking Staircase,Speedboat Dock,Ranger Kawasan,Spot Foto Sunset 360 Derajat,Peralatan Snorkeling', 'buka', 4.96, 'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=800'),
(10, 1, 'Dataran Tinggi Dieng & Telaga Warna', 'dataran-tinggi-dieng', 'Negeri di atas awan dengan keunikan fenomena telaga tiga warna, kawah belerang aktif Sikidang, dan kompleks candi Arjuna tertua di tanah Jawa di ketinggian 2.000 mdpl.', 'Wonosobo & Banjarnegara, Jawa Tengah', '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3958.077227768564!2d109.91444107476077!3d-7.231998570954002!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e700d115e4a8677%3A0x2fbf155dc6188448!2sTelaga%20Warna!5e0!3m2!1sen!2sid!4v1700000000009!5m2!1sen!2sid" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy"></iframe>', 30000.00, 10, 10, '06:00:00', '17:30:00', 'Setiap Hari (Senin - Minggu)', 'Gardu Pandang Tieng,Area Perkemahan,Kuliner Mie Ongklok,Museum Kailasa,Pemandian Air Hangat Alami', 'buka', 4.82, 'https://images.unsplash.com/photo-1518548419970-58e3b4079ab2?w=800');

-- 4. SEED DESTINASI FOTO (GALERI HD)
INSERT INTO `destinasi_foto` (`destinasi_id`, `foto_url`, `caption`) VALUES
(1, 'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=800', 'Lautan Pasir Bromo di Pagi Hari'),
(1, 'https://images.unsplash.com/photo-1518548419970-58e3b4079ab2?w=800', 'Kawah Vulkanik Bromo yang Megah'),
(1, 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?w=800', 'Sunrise Emas dari Penanjakan 1'),
(2, 'https://images.unsplash.com/photo-1518548419970-58e3b4079ab2?w=800', 'Pemandangan Tebing T-Rex dan Laut Biru'),
(2, 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=800', 'Hamparan Pasir Putih di Bawah Tebing'),
(3, 'https://images.unsplash.com/photo-1578632767115-351597cf2477?w=800', 'Stupa Borobudur Saat Fajar'),
(3, 'https://images.unsplash.com/photo-1596402184320-417e7178b2cd?w=800', 'Relief Dinding Candi yang Bersejarah'),
(4, 'https://images.unsplash.com/photo-1568832359672-e36cf5d74f54?w=800', 'Lampion Malam Gemerlap Warna-Warni'),
(4, 'https://images.unsplash.com/photo-1513889961551-628c1e5e2ee9?w=800', 'Wahana Laser Show Malam Hari'),
(5, 'https://images.unsplash.com/photo-1546182990-dffeafbe841d?w=800', 'Satwa Harimau di Kawasan Konservasi'),
(5, 'https://images.unsplash.com/photo-1534567153574-2b12153a87f0?w=800', 'Pengalaman Safari Feeding Jerapah'),
(7, 'https://images.unsplash.com/photo-1516690561799-46d8f74f9abf?w=800', 'Puncak Wayag Menatap Laguna Karang'),
(8, 'https://images.unsplash.com/photo-1578632767115-351597cf2477?w=800', 'Kemegahan Candi Siwa Prambanan 47 Meter'),
(9, 'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=800', 'Trekking Menakjubkan di Pulau Padar'),
(10, 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=800', 'Danau Telaga Warna yang Berubah Warna');

-- 5. SEED ULASAN
INSERT INTO `ulasan` (`destinasi_id`, `user_id`, `rating`, `komentar`, `created_at`) VALUES
(1, 5, 5, 'Pemandangan sunrise di Bromo beneran luar biasa indah! Pelayanan tiket online di website ini sangat memudahkan rombongan kami.', NOW() - INTERVAL 1 DAY),
(1, 6, 5, 'Sangat recommended! Udara dingin segar, jeep tour teratur rapi dan barcode tiket langsung lolos di pos gerbang.', NOW() - INTERVAL 2 DAY),
(2, 5, 5, 'Spot foto paling epic di dunia! Air lautnya sebening kaca dan tebingnya sangat megah.', NOW() - INTERVAL 3 DAY),
(3, 7, 5, 'Bangunan candi sangat megah dan penuh nilai sejarah. Sangat cocok bawa keluarga berlibur.', NOW() - INTERVAL 4 DAY),
(4, 8, 5, 'Wahana malam di Batu seru banget! Anak-anak sangat gembira menikmati festival lampion.', NOW() - INTERVAL 5 DAY),
(5, 5, 5, 'Anak-anak sangat senang bisa memberi makan satwa langsung dari mobil. Pelayanan ramah!', NOW() - INTERVAL 6 DAY),
(7, 6, 5, 'Raja Ampat benar-benar surga dunia! Pengalaman diving terbaik yang tak terlupakan.', NOW() - INTERVAL 7 DAY),
(9, 7, 5, 'Pemandangan dari atas bukit Pulau Padar benar-benar magis. Sangat sepadan dengan perjalanan trekkingnya.', NOW() - INTERVAL 8 DAY);

-- 6. SEED PEMESANAN (BOOKING TIKET DENGAN DETAIL LENGKAP)
INSERT INTO `pemesanan` (`id`, `kode_booking`, `user_id`, `destinasi_id`, `tanggal_kunjungan`, `tipe_rombongan`, `jumlah_tiket`, `harga_satuan`, `diskon_didapat`, `total_bayar`, `status_bayar`, `metode_pembayaran`, `bukti_bayar`, `qr_code`, `status_kunjungan`, `waktu_checkin`, `nama_pemesan`, `no_telp`, `catatan`, `created_at`) VALUES
(1, 'BK-20260901-001', 5, 1, CURDATE() + INTERVAL 2 DAY, 'rombongan', 12, 35000.00, 63000.00, 357000.00, 'lunas', 'QRIS Instant', 'qris_success.png', 'QR-BK-20260901-001', 'belum_digunakan', NULL, 'Dimas Pratama', '083456789012', 'Rombongan Komunitas Fotografi (Jeep 2 Unit)', NOW() - INTERVAL 1 DAY),
(2, 'BK-20260902-002', 6, 2, CURDATE() + INTERVAL 5 DAY, 'sendiri', 2, 50000.00, 0.00, 100000.00, 'lunas', 'Transfer BCA', 'bukti_transfer_sample.png', 'QR-BK-20260902-002', 'belum_digunakan', NULL, 'Siti Rahmawati', '085678901234', 'Trip pasangan honeymoon', NOW() - INTERVAL 2 DAY),
(3, 'BK-20260903-003', 5, 3, CURDATE() - INTERVAL 1 DAY, 'sendiri', 1, 75000.00, 0.00, 75000.00, 'lunas', 'E-Wallet GoPay', 'gopay_success.png', 'QR-BK-20260903-003', 'sudah_digunakan', NOW() - INTERVAL 1 DAY, 'Dimas Pratama', '083456789012', 'Kunjungan pagi sunrise', NOW() - INTERVAL 2 DAY),
(4, 'BK-20260904-004', 6, 5, CURDATE() + INTERVAL 7 DAY, 'rombongan', 15, 120000.00, 360000.00, 1440000.00, 'pending', 'Transfer Mandiri', NULL, 'QR-BK-20260904-004', 'belum_digunakan', NULL, 'Siti Rahmawati', '085678901234', 'Study Tour SD Teladan (Bus Besar)', NOW()),
(5, 'BK-20260905-005', 7, 8, CURDATE() + INTERVAL 3 DAY, 'sendiri', 4, 50000.00, 0.00, 200000.00, 'lunas', 'QRIS Instant', 'qris_success.png', 'QR-BK-20260905-005', 'belum_digunakan', NULL, 'Ahmad Fauzi', '081298765432', 'Keluarga liburan akhir pekan', NOW() - INTERVAL 6 HOUR),
(6, 'BK-20260906-006', 8, 4, CURDATE() + INTERVAL 4 DAY, 'rombongan', 10, 45000.00, 67500.00, 382500.00, 'lunas', 'Transfer BCA', 'bukti_transfer_sample.png', 'QR-BK-20260906-006', 'belum_digunakan', NULL, 'Jessica Tanujaya', '087812345678', 'Gathering Kantor Malang', NOW() - INTERVAL 3 HOUR);

-- 7. SEED PRESENSI KUNJUNGAN (INPUT PETUGAS LOKET & CHECK-IN)
INSERT INTO `presensi_kunjungan` (`id`, `petugas_id`, `destinasi_id`, `tanggal_kunjungan`, `hari`, `jumlah_pengunjung`, `jenis_kunjungan`, `keterangan`, `created_at`) VALUES
(1, 2, 1, CURDATE() - INTERVAL 5 DAY, 'Sabtu', 350, 'sendiri', 'Pengunjung akhir pekan pagi hari sangat padat dan tertib.', NOW() - INTERVAL 5 DAY),
(2, 2, 1, CURDATE() - INTERVAL 5 DAY, 'Sabtu', 220, 'rombongan', 'Rombongan Komunitas Jeep Jawa Timur 15 Mobil.', NOW() - INTERVAL 5 DAY),
(3, 2, 1, CURDATE() - INTERVAL 4 DAY, 'Minggu', 410, 'sendiri', 'Kepadatan puncak di Penanjakan 1.', NOW() - INTERVAL 4 DAY),
(4, 3, 2, CURDATE() - INTERVAL 3 DAY, 'Senin', 180, 'sendiri', 'Wisatawan mancanegara dominan.', NOW() - INTERVAL 3 DAY),
(5, 3, 2, CURDATE() - INTERVAL 3 DAY, 'Senin', 95, 'rombongan', 'Rombongan tour guide dari Australia 3 grup.', NOW() - INTERVAL 3 DAY),
(6, 4, 3, CURDATE() - INTERVAL 2 DAY, 'Selasa', 290, 'sendiri', 'Kunjungan edukasi cagar budaya.', NOW() - INTERVAL 2 DAY),
(7, 4, 3, CURDATE() - INTERVAL 2 DAY, 'Selasa', 380, 'rombongan', 'Studi Tour Universitas Gadjah Mada 6 Bus.', NOW() - INTERVAL 2 DAY),
(8, 2, 1, CURDATE() - INTERVAL 1 DAY, 'Rabu', 210, 'sendiri', 'Cuaca cerah berawan sejuk.', NOW() - INTERVAL 1 DAY),
(9, 3, 2, CURDATE() - INTERVAL 1 DAY, 'Rabu', 160, 'sendiri', 'Kunjungan sunset pantai Nusa Penida.', NOW() - INTERVAL 1 DAY),
(10, 2, 1, CURDATE(), 'Kamis', 315, 'sendiri', 'Kunjungan hari ini terpantau ramai kondusif.', NOW()),
(11, 4, 3, CURDATE(), 'Kamis', 175, 'rombongan', 'Rombongan Dinas Pariwisata Daerah.', NOW());

-- 8. SEED TRANSAKSI LOG (AUDIT LOG PEMBAYARAN RESMI)
INSERT INTO `transaksi_log` (`pemesanan_id`, `nominal`, `metode`, `status`, `waktu_bayar`, `catatan`) VALUES
(1, 357000.00, 'QRIS Instant', 'LUNAS', NOW() - INTERVAL 1 DAY, 'Pembayaran berhasil melalui scan QRIS Bank Indonesia'),
(2, 100000.00, 'Transfer BCA', 'LUNAS', NOW() - INTERVAL 2 DAY, 'Verifikasi manual transfer bank BCA Rekening Pusat'),
(3, 75000.00, 'E-Wallet GoPay', 'LUNAS', NOW() - INTERVAL 2 DAY, 'Instant payment gateway callback terkonfirmasi'),
(5, 200000.00, 'QRIS Instant', 'LUNAS', NOW() - INTERVAL 6 HOUR, 'Instant QR Code payment approval'),
(6, 382500.00, 'Transfer BCA', 'LUNAS', NOW() - INTERVAL 3 HOUR, 'Pembayaran tiket rombongan 10 orang berhasil');

-- 9. SEED PENGATURAN WEB (BRANDING & KONTAK RESMI)
INSERT INTO `pengaturan_web` (`id`, `nama_sistem`, `tagline`, `deskripsi`, `kontak_email`, `kontak_telp`, `alamat`, `logo`, `banner`, `sosmed_instagram`, `sosmed_facebook`, `sosmed_youtube`) VALUES
(1, 'Pesona Nusantara', 'Sistem Informasi & Reservasi Wisata Berkelas Indonesia', 'Platform digital terpadu untuk menjelajahi keindahan destinasi wisata nusantara, pemesanan e-ticket instan untuk perorangan maupun rombongan, serta pengelolaan data kunjungan wisatawan secara akurat dan terpercaya.', 'kontak@pesonanusantara.id', '+62 821-9988-7766', 'Kawasan Pusat Pariwisata Indonesia, Jl. Sunset Boulevard No. 108, Nusantara', 'assets/images/logo.png', 'assets/images/hero_banner.jpg', 'pesonanusantara.id', 'pesonanusantara.official', 'PesonaNusantaraTV');
