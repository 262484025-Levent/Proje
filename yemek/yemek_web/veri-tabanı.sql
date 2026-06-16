/*
Adı Soyadı: Levent KUBAŞIK
Öğrenci Numarası: 262484025
*/
-- Yemek Paylaşım Sitesi - SQL Şeması
-- phpMyAdmin veya MySQL Workbench ile import edin

CREATE DATABASE IF NOT EXISTS yemek_paylasim;
USE yemek_paylasim;

CREATE TABLE IF NOT EXISTS kullanicilar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_adi VARCHAR(50) UNIQUE NOT NULL,
  eposta VARCHAR(100) UNIQUE NOT NULL,
  sifre VARCHAR(255) NOT NULL,
  profil_resmi VARCHAR(255) DEFAULT 'default.png',
  biyografi TEXT DEFAULT NULL,
  olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tarifler (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_id INT NOT NULL,
  baslik VARCHAR(200) NOT NULL,
  aciklama TEXT,
  malzemeler TEXT NOT NULL,
  hazirlanisi TEXT NOT NULL,
  hazirlama_suresi INT,
  pisirme_suresi INT,
  kisi_sayisi INT DEFAULT 1,
  resim_url VARCHAR(500),
  olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS begeniler (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_id INT NOT NULL,
  tarif_id INT NOT NULL,
  olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_like (kullanici_id, tarif_id),
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  FOREIGN KEY (tarif_id) REFERENCES tarifler(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS yorumlar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_id INT NOT NULL,
  tarif_id INT NOT NULL,
  icerik TEXT NOT NULL,
  olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  FOREIGN KEY (tarif_id) REFERENCES tarifler(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS yorum_begenileri (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_id INT NOT NULL,
  yorum_id INT NOT NULL,
  olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_comment_like (kullanici_id, yorum_id),
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  FOREIGN KEY (yorum_id) REFERENCES yorumlar(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS yorum_cevaplari (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_id INT NOT NULL,
  yorum_id INT NOT NULL,
  ust_cevap_id INT DEFAULT NULL,
  icerik TEXT NOT NULL,
  olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  FOREIGN KEY (yorum_id) REFERENCES yorumlar(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cevap_begenileri (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_id INT NOT NULL,
  cevap_id INT NOT NULL,
  olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_reply_like (kullanici_id, cevap_id),
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  FOREIGN KEY (cevap_id) REFERENCES yorum_cevaplari(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tarif_puanlari (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_id INT NOT NULL,
  tarif_id INT NOT NULL,
  puan TINYINT NOT NULL,
  olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  guncellenme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_recipe_rating (kullanici_id, tarif_id),
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  FOREIGN KEY (tarif_id) REFERENCES tarifler(id) ON DELETE CASCADE
);
