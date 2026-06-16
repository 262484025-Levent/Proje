<?php
/*
Adı Soyadı: Levent KUBAŞIK
Öğrenci Numarası: 262484025
*/
require_once 'includes/db.php';

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Yemek Paylaşım - Kurulum</title>
    <link rel='stylesheet' href='css/style.css'>
</head>
<body>
    <div class='container' style='margin-top: 3rem; max-width: 600px;'>
        <h1>Yemek Paylaşım - Kurulum</h1>
        <div class='auth-container'>";

try {
    // Biyografi sütununu ekle
    $result = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'biyografi'");
    if ($result->rowCount() === 0) {
        $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN biyografi TEXT DEFAULT NULL");
        echo "<div class='alert alert-success'>✅ Biyografi sütunu eklendi</div>";
    } else {
        echo "<div class='alert alert-success'>✅ Biyografi sütunu zaten var</div>";
    }

    // Profil fotoğrafı sütununu kontrol et ve genişlet
    $result = $pdo->query("SHOW COLUMNS FROM kullanicilar WHERE Field = 'profil_resmi'");
    $column = $result->fetch();
    if ($column) {
        if (strpos($column['Type'], '500') === false) {
            $pdo->exec("ALTER TABLE kullanicilar MODIFY COLUMN profil_resmi VARCHAR(500) DEFAULT 'default.png'");
            echo "<div class='alert alert-success'>✅ Profil fotoğrafı sütunu genişletildi</div>";
        } else {
            echo "<div class='alert alert-success'>✅ Profil fotoğrafı sütunu zaten genişletilmiş</div>";
        }
    }

    // Tarif görseli sütununu ekle
    $result = $pdo->query("SHOW COLUMNS FROM tarifler LIKE 'resim_url'");
    if ($result->rowCount() === 0) {
        $pdo->exec("ALTER TABLE tarifler ADD COLUMN resim_url VARCHAR(500) DEFAULT NULL");
        echo "<div class='alert alert-success'>✅ Tarif görseli sütunu eklendi</div>";
    } else {
        echo "<div class='alert alert-success'>✅ Tarif görseli sütunu zaten var</div>";
    }
    
    // Yorum beğenileri tablosu
    $result = $pdo->query("SHOW TABLES LIKE 'yorum_begenileri'");
    if ($result->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE yorum_begenileri (
              id INT AUTO_INCREMENT PRIMARY KEY,
              kullanici_id INT NOT NULL,
              yorum_id INT NOT NULL,
              olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY unique_comment_like (kullanici_id, yorum_id),
              FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
              FOREIGN KEY (yorum_id) REFERENCES yorumlar(id) ON DELETE CASCADE
            )
        ");
        echo "<div class='alert alert-success'>✅ Yorum beğenileri tablosu eklendi</div>";
    } else {
        echo "<div class='alert alert-success'>✅ Yorum beğenileri tablosu zaten var</div>";
    }

    // Yorum cevapları tablosu
    $result = $pdo->query("SHOW TABLES LIKE 'yorum_cevaplari'");
    if ($result->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE yorum_cevaplari (
              id INT AUTO_INCREMENT PRIMARY KEY,
              kullanici_id INT NOT NULL,
              yorum_id INT NOT NULL,
              ust_cevap_id INT DEFAULT NULL,
              icerik TEXT NOT NULL,
              olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
              FOREIGN KEY (yorum_id) REFERENCES yorumlar(id) ON DELETE CASCADE
            )
        ");
        echo "<div class='alert alert-success'>✅ Yorum cevapları tablosu eklendi</div>";
    } else {
        echo "<div class='alert alert-success'>✅ Yorum cevapları tablosu zaten var</div>";
    }

    $result = $pdo->query("SHOW COLUMNS FROM yorum_cevaplari LIKE 'ust_cevap_id'");
    if ($result->rowCount() === 0) {
        $pdo->exec("ALTER TABLE yorum_cevaplari ADD COLUMN ust_cevap_id INT DEFAULT NULL AFTER yorum_id");
        echo "<div class='alert alert-success'>✅ Üst cevap sütunu eklendi</div>";
    } else {
        echo "<div class='alert alert-success'>✅ Üst cevap sütunu zaten var</div>";
    }

    // Cevaplara beğeni tablosu
    $result = $pdo->query("SHOW TABLES LIKE 'cevap_begenileri'");
    if ($result->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE cevap_begenileri (
              id INT AUTO_INCREMENT PRIMARY KEY,
              kullanici_id INT NOT NULL,
              cevap_id INT NOT NULL,
              olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY unique_reply_like (kullanici_id, cevap_id),
              FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
              FOREIGN KEY (cevap_id) REFERENCES yorum_cevaplari(id) ON DELETE CASCADE
            )
        ");
        echo "<div class='alert alert-success'>✅ Cevap beğenileri tablosu eklendi</div>";
    } else {
        echo "<div class='alert alert-success'>✅ Cevap beğenileri tablosu zaten var</div>";
    }

    // Tarif yıldız puanları tablosu
    $result = $pdo->query("SHOW TABLES LIKE 'tarif_puanlari'");
    if ($result->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE tarif_puanlari (
              id INT AUTO_INCREMENT PRIMARY KEY,
              kullanici_id INT NOT NULL,
              tarif_id INT NOT NULL,
              puan TINYINT NOT NULL,
              olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              guncellenme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY unique_recipe_rating (kullanici_id, tarif_id),
              FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
              FOREIGN KEY (tarif_id) REFERENCES tarifler(id) ON DELETE CASCADE
            )
        ");
        echo "<div class='alert alert-success'>✅ Tarif puanları tablosu eklendi</div>";
    } else {
        echo "<div class='alert alert-success'>✅ Tarif puanları tablosu zaten var</div>";
    }
    echo "<div class='alert alert-success' style='margin-top: 1rem;'>
        <h3>✅ Kurulum/Güncelleme Tamamlandı!</h3>
        <p>Tüm veritabanı şemaları hazır ve Türkçe'leştirildi. Siteyi kullanmaya başlayabilirsiniz.</p>
        <a href='index.php' class='btn btn-primary' style='display: inline-block; margin-top: 1rem;'>Ana Sayfaya Git</a>
    </div>";

} catch (Exception $e) {
    echo "<div class='alert alert-error'>
        <h3>Hata Oluştu</h3>
        <p><strong>Hata Mesajı:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}

echo "</div></div></body></html>";
