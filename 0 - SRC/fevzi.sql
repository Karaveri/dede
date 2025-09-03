-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 17 Ağu 2025, 19:10:02
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `fevzi`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ayarlar`
--

CREATE TABLE `ayarlar` (
  `id` int(11) NOT NULL,
  `anahtar` varchar(100) DEFAULT NULL,
  `deger` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kategoriler`
--

CREATE TABLE `kategoriler` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `ad` varchar(120) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `durum` tinyint(1) NOT NULL DEFAULT 1,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silindi` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `kategoriler`
--

INSERT INTO `kategoriler` (`id`, `parent_id`, `ad`, `slug`, `aciklama`, `durum`, `olusturma_tarihi`, `guncelleme_tarihi`, `silindi`) VALUES
(1, NULL, 'Alia11112', 'alia1111', 'asdasd11', 1, '2025-08-14 22:57:16', '2025-08-17 15:12:27', 1),
(2, 1, 'Semantik SEO Uyumlu - Rakip Analizli ÖZGÜN Makale Hizmeti', 'semantik-seo-uyumlu-rakip-analizli-ozgun-makale-hizmeti', 'asdasd', 1, '2025-08-14 22:57:35', '2025-08-14 22:57:49', 1),
(4, NULL, 'dede', 'dede', 'sdfg', 1, '2025-08-17 10:48:03', '2025-08-17 10:48:03', 0),
(5, NULL, 'Ali', 'ali', 'sadasdasd', 1, '2025-08-17 16:49:49', '2025-08-17 16:49:49', 0),
(6, 4, 'Semantik SEO', 'semantik-seo', NULL, 1, '2025-08-17 16:54:04', '2025-08-17 16:54:04', 0);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kullanicilar`
--

CREATE TABLE `kullanicilar` (
  `id` int(11) NOT NULL,
  `ad_soyad` varchar(100) NOT NULL,
  `eposta` varchar(150) NOT NULL,
  `sifre_hash` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `rol_id` int(11) NOT NULL,
  `durum` enum('aktif','pasif') NOT NULL DEFAULT 'aktif',
  `beceri_yuzde` tinyint(4) DEFAULT 0,
  `sosyal_twitter` varchar(255) DEFAULT NULL,
  `sosyal_instagram` varchar(255) DEFAULT NULL,
  `sosyal_linkedin` varchar(255) DEFAULT NULL,
  `banli` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `kullanicilar`
--

INSERT INTO `kullanicilar` (`id`, `ad_soyad`, `eposta`, `sifre_hash`, `avatar`, `aciklama`, `rol_id`, `durum`, `beceri_yuzde`, `sosyal_twitter`, `sosyal_instagram`, `sosyal_linkedin`, `banli`, `created_at`, `updated_at`) VALUES
(1, 'Yonetici', 'admin@local.test', '$2y$10$CwX5NDSCXA2mswNOMok/aebEq4ChCjHZOoUfP0Mh9xleiv9bszLqy', NULL, NULL, 1, 'aktif', 0, NULL, NULL, NULL, 0, '2025-08-14 19:56:46', NULL),
(2, 'Yönetici', 'admin@example.com', '$2y$10$CwX5NDSCXA2mswNOMok/aebEq4ChCjHZOoUfP0Mh9xleiv9bszLqy', NULL, NULL, 1, 'aktif', 0, NULL, NULL, NULL, 0, '2025-08-16 21:03:43', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `medya`
--

CREATE TABLE `medya` (
  `id` int(11) NOT NULL,
  `yol` varchar(255) NOT NULL,
  `mime` varchar(50) NOT NULL,
  `boyut` int(11) NOT NULL,
  `hash` char(64) NOT NULL,
  `genislik` int(11) DEFAULT NULL,
  `yukseklik` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `medya`
--

INSERT INTO `medya` (`id`, `yol`, `mime`, `boyut`, `hash`, `genislik`, `yukseklik`, `created_at`) VALUES
(6, '/uploads/editor/logo-20250816-160634-9756dc.png', 'image/png', 52695, 'eea767ba4f18d710726f79c2be97539c333ac83ee74a108f1de528206435e50f', 493, 500, '2025-08-16 13:06:34'),
(9, '/uploads/editor/avatar5-20250816-234745-627527.jpg', 'image/jpeg', 9294, '61ab4196c884a89f3c2478e23b4c3a0f0f4bc70afa9a3eaeec1351a5c36dd7a3', 225, 225, '2025-08-16 20:47:45'),
(10, '/uploads/editor/avatar3-20250817-151336-edc593.jpg', 'image/jpeg', 13296, '62946b945466796e631ea29a8e626445385f3ee4e78e1882dd0b3c1cac1a341d', 225, 225, '2025-08-17 12:13:36'),
(11, '/uploads/editor/avatar7-20250817-165337-a6a5a4.jpg', 'image/jpeg', 30784, '7b2404b905aaef617cedbdfba19b06854d6cd57dcb81c463f3459d2c13c9959f', 300, 300, '2025-08-17 13:53:37');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `roller`
--

CREATE TABLE `roller` (
  `id` int(11) NOT NULL,
  `ad` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `roller`
--

INSERT INTO `roller` (`id`, `ad`) VALUES
(1, 'admin'),
(2, 'editor'),
(3, 'yazar'),
(4, 'uye');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sayfalar`
--

CREATE TABLE `sayfalar` (
  `id` int(10) UNSIGNED NOT NULL,
  `baslik` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `icerik` longtext NOT NULL,
  `ozet` text DEFAULT NULL,
  `kapak_gorsel` varchar(255) DEFAULT NULL,
  `durum` enum('taslak','yayinda') NOT NULL DEFAULT 'taslak',
  `meta_baslik` varchar(255) DEFAULT NULL,
  `meta_aciklama` varchar(255) DEFAULT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `silindi` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `sayfalar`
--

INSERT INTO `sayfalar` (`id`, `baslik`, `slug`, `icerik`, `ozet`, `kapak_gorsel`, `durum`, `meta_baslik`, `meta_aciklama`, `olusturma_tarihi`, `guncelleme_tarihi`, `created_at`, `updated_at`, `silindi`) VALUES
(3, 'Kahvaltının Önemi ve Türk Kültüründeki Yeri', 'kahvaltinin-onemi-ve-turk-kulturundeki-yeri', '<p><img src=\"http://localhost/fevzi/public/uploads/editor/avatar1-20250815-103219-713a19.jpg\" alt=\"dede\" width=\"1080\" height=\"608\"></p>', 'dedede', NULL, 'yayinda', NULL, NULL, '2025-08-15 10:32:29', '2025-08-15 10:59:25', '2025-08-15 07:32:29', NULL, 1),
(4, 'Modern Tasarım ve Hızlı Çözümlera', 'modern-tasarim-ve-hizli-cozumler', '<p>asdfasdf<img src=\"http://localhost/fevzi/public/uploads/editor/avatar5-20250816-234745-627527.jpg?v=1755377265\" alt=\"\" width=\"225\" height=\"225\">sdfg</p>', 'dedede', NULL, 'yayinda', 'sdfgsdfgsdfg', 'sdfgsdfgsdfg', '2025-08-15 11:30:01', '2025-08-17 15:10:49', '2025-08-15 08:30:01', '2025-08-17 12:10:49', 0),
(5, 'Modern Tasarım ve Hızlı Çözümlera', 'modern-tasarim-ve-hizli-cozumler-1', '<p>asdasdasd</p>', 'Modern Tasarım ve Hızlı Çözümler', NULL, 'yayinda', 'Kahvaltının Sağlık ve Kültürel Boyutu', 'asdfsadf asd f asd fasd fsad f', '2025-08-15 11:33:21', '2025-08-17 15:10:45', '2025-08-15 08:33:21', '2025-08-17 12:10:45', 0),
(6, 'Asit Reflü Ve Mide Ekşimesi Neyden Kaynaklanır Neler Yapılabilir', 'asit-refl-u-ve-mide-eksimesi-neyden-kaynaklanir-neler-yapilabilir', '<p>dsafasdf asdf asd fasdf</p>\r\n<p><img src=\"http://localhost/fevzi/public/uploads/editor/avatar7-20250817-165337-a6a5a4.jpg?v=1755438817\" alt=\"\" width=\"300\" height=\"300\"></p>', NULL, NULL, 'yayinda', 'asdf asf', 'asd f asd', '2025-08-17 16:53:14', '2025-08-17 16:53:40', '2025-08-17 13:53:14', '2025-08-17 13:53:40', 0);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `yazilar`
--

CREATE TABLE `yazilar` (
  `id` int(11) NOT NULL,
  `baslik` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `icerik` longtext NOT NULL,
  `kapak_gorsel` varchar(255) DEFAULT NULL,
  `kategori_id` int(10) UNSIGNED DEFAULT NULL,
  `yazar_id` int(11) NOT NULL,
  `durum` enum('taslak','yayinda') DEFAULT 'taslak',
  `yayin_tarihi` datetime DEFAULT NULL,
  `meta_baslik` varchar(255) DEFAULT NULL,
  `meta_aciklama` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `ayarlar`
--
ALTER TABLE `ayarlar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `anahtar` (`anahtar`);

--
-- Tablo için indeksler `kategoriler`
--
ALTER TABLE `kategoriler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_kategoriler_slug` (`slug`),
  ADD KEY `idx_parent_id` (`parent_id`);

--
-- Tablo için indeksler `kullanicilar`
--
ALTER TABLE `kullanicilar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `eposta` (`eposta`),
  ADD KEY `rol_id` (`rol_id`);

--
-- Tablo için indeksler `medya`
--
ALTER TABLE `medya`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `yol` (`yol`);

--
-- Tablo için indeksler `roller`
--
ALTER TABLE `roller`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `sayfalar`
--
ALTER TABLE `sayfalar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sayfalar_slug` (`slug`),
  ADD KEY `idx_durum` (`durum`),
  ADD KEY `idx_silindi` (`silindi`);

--
-- Tablo için indeksler `yazilar`
--
ALTER TABLE `yazilar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `kategori_id` (`kategori_id`),
  ADD KEY `yazar_id` (`yazar_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `ayarlar`
--
ALTER TABLE `ayarlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `kategoriler`
--
ALTER TABLE `kategoriler`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Tablo için AUTO_INCREMENT değeri `kullanicilar`
--
ALTER TABLE `kullanicilar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `medya`
--
ALTER TABLE `medya`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Tablo için AUTO_INCREMENT değeri `roller`
--
ALTER TABLE `roller`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `sayfalar`
--
ALTER TABLE `sayfalar`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Tablo için AUTO_INCREMENT değeri `yazilar`
--
ALTER TABLE `yazilar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `kategoriler`
--
ALTER TABLE `kategoriler`
  ADD CONSTRAINT `fk_kategoriler_parent` FOREIGN KEY (`parent_id`) REFERENCES `kategoriler` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `kullanicilar`
--
ALTER TABLE `kullanicilar`
  ADD CONSTRAINT `kullanicilar_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roller` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `yazilar`
--
ALTER TABLE `yazilar`
  ADD CONSTRAINT `yazilar_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategoriler` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `yazilar_ibfk_2` FOREIGN KEY (`yazar_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
