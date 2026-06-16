<?php
/*
Ad� Soyad�: Levent KUBA�IK
��renci Numaras�: 262484025
*/
require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    // UTF-8 desteğini sağla
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

function tarifIdKolonunuGetir(PDO $pdo): string {
    static $recipeIdColumn = null;

    if ($recipeIdColumn !== null) {
        return $recipeIdColumn;
    }

    foreach (['id', 'tarif_id', 'recipe_id'] as $columnName) {
        $result = $pdo->query("SHOW COLUMNS FROM tarifler LIKE " . $pdo->quote($columnName));
        if ($result && $result->fetch()) {
            return $recipeIdColumn = $columnName;
        }
    }

    return $recipeIdColumn = 'id';
}

// Kolon ismini güvenli şekilde değiştirme yardımcısı
function kolonVarsaDegistir(PDO $pdo, string $tablo, string $eskiKolon, string $yeniKolon, string $tanim) {
    try {
        $result = $pdo->query("SHOW COLUMNS FROM `$tablo` LIKE " . $pdo->quote($eskiKolon));
        if ($result && $result->fetch()) {
            $newResult = $pdo->query("SHOW COLUMNS FROM `$tablo` LIKE " . $pdo->quote($yeniKolon));
            if (!$newResult || !$newResult->fetch()) {
                $pdo->exec("ALTER TABLE `$tablo` CHANGE `$eskiKolon` `$yeniKolon` $tanim");
            }
        }
    } catch (Exception $e) {
        error_log("Kolon migrasyon hatası ($tablo -> $eskiKolon): " . $e->getMessage());
    }
}

// Kolon yoksa ekleme yardımcısı
function kolonEkleEgerYoksa(PDO $pdo, string $tablo, string $kolon, string $tanim) {
    try {
        $result = $pdo->query("SHOW COLUMNS FROM `$tablo` LIKE " . $pdo->quote($kolon));
        if (!$result || !$result->fetch()) {
            $pdo->exec("ALTER TABLE `$tablo` ADD COLUMN `$kolon` $tanim");
        }
    } catch (Exception $e) {
        error_log("Kolon ekleme hatası ($tablo -> $kolon): " . $e->getMessage());
    }
}

// Veritabanı geçişleri
try {
    // Yabancı anahtar kontrollerini geçici olarak kapat
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $tableRenames = [
        'users' => 'kullanicilar',
        'recipes' => 'tarifler',
        'likes' => 'begeniler',
        'comments' => 'yorumlar',
        'comment_likes' => 'yorum_begenileri',
        'comment_replies' => 'yorum_cevaplari',
        'reply_likes' => 'cevap_begenileri',
        'recipe_ratings' => 'tarif_puanlari',
    ];

    $renameStatements = [];
    foreach ($tableRenames as $oldTable => $newTable) {
        $oldExists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($oldTable))->fetch();
        $newExists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($newTable))->fetch();

        if ($oldExists && !$newExists) {
            $renameStatements[] = "`$oldTable` TO `$newTable`";
        }
    }

    if (!empty($renameStatements)) {
        $pdo->exec('RENAME TABLE ' . implode(', ', $renameStatements));
    }

    // Tablo kolonlarını Türkçe'leştirme işlemleri
    
    // 1. kullanicilar
    kolonVarsaDegistir($pdo, 'kullanicilar', 'username', 'kullanici_adi', 'VARCHAR(50) NOT NULL');
    kolonVarsaDegistir($pdo, 'kullanicilar', 'email', 'eposta', 'VARCHAR(100) NOT NULL');
    kolonVarsaDegistir($pdo, 'kullanicilar', 'password', 'sifre', 'VARCHAR(255) NOT NULL');
    kolonVarsaDegistir($pdo, 'kullanicilar', 'avatar', 'profil_resmi', 'VARCHAR(500) DEFAULT \'default.png\'');
    kolonVarsaDegistir($pdo, 'kullanicilar', 'created_at', 'olusturulma_tarihi', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    kolonVarsaDegistir($pdo, 'kullanicilar', 'bio', 'biyografi', 'TEXT DEFAULT NULL');
    kolonEkleEgerYoksa($pdo, 'kullanicilar', 'biyografi', 'TEXT DEFAULT NULL');

    // 2. tarifler
    kolonVarsaDegistir($pdo, 'tarifler', 'user_id', 'kullanici_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'tarifler', 'title', 'baslik', 'VARCHAR(200) NOT NULL');
    kolonVarsaDegistir($pdo, 'tarifler', 'description', 'aciklama', 'TEXT DEFAULT NULL');
    kolonVarsaDegistir($pdo, 'tarifler', 'ingredients', 'malzemeler', 'TEXT NOT NULL');
    kolonVarsaDegistir($pdo, 'tarifler', 'instructions', 'hazirlanisi', 'TEXT NOT NULL');
    kolonVarsaDegistir($pdo, 'tarifler', 'prep_time', 'hazirlama_suresi', 'INT DEFAULT NULL');
    kolonVarsaDegistir($pdo, 'tarifler', 'cook_time', 'pisirme_suresi', 'INT DEFAULT NULL');
    kolonVarsaDegistir($pdo, 'tarifler', 'servings', 'kisi_sayisi', 'INT DEFAULT 1');
    kolonVarsaDegistir($pdo, 'tarifler', 'image_url', 'resim_url', 'VARCHAR(500) DEFAULT NULL');
    kolonEkleEgerYoksa($pdo, 'tarifler', 'resim_url', 'VARCHAR(500) DEFAULT NULL');
    kolonVarsaDegistir($pdo, 'tarifler', 'created_at', 'olusturulma_tarihi', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

    // 3. begeniler
    kolonVarsaDegistir($pdo, 'begeniler', 'user_id', 'kullanici_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'begeniler', 'recipe_id', 'tarif_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'begeniler', 'created_at', 'olusturulma_tarihi', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

    // 4. yorumlar
    kolonVarsaDegistir($pdo, 'yorumlar', 'user_id', 'kullanici_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'yorumlar', 'recipe_id', 'tarif_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'yorumlar', 'content', 'icerik', 'TEXT NOT NULL');
    kolonVarsaDegistir($pdo, 'yorumlar', 'created_at', 'olusturulma_tarihi', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

    // 5. yorum_begenileri
    kolonVarsaDegistir($pdo, 'yorum_begenileri', 'user_id', 'kullanici_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'yorum_begenileri', 'comment_id', 'yorum_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'yorum_begenileri', 'created_at', 'olusturulma_tarihi', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

    // 6. yorum_cevaplari
    kolonVarsaDegistir($pdo, 'yorum_cevaplari', 'user_id', 'kullanici_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'yorum_cevaplari', 'comment_id', 'yorum_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'yorum_cevaplari', 'parent_reply_id', 'ust_cevap_id', 'INT DEFAULT NULL');
    kolonEkleEgerYoksa($pdo, 'yorum_cevaplari', 'ust_cevap_id', 'INT DEFAULT NULL AFTER yorum_id');
    kolonVarsaDegistir($pdo, 'yorum_cevaplari', 'content', 'icerik', 'TEXT NOT NULL');
    kolonVarsaDegistir($pdo, 'yorum_cevaplari', 'created_at', 'olusturulma_tarihi', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

    // 7. cevap_begenileri
    kolonVarsaDegistir($pdo, 'cevap_begenileri', 'user_id', 'kullanici_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'cevap_begenileri', 'reply_id', 'cevap_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'cevap_begenileri', 'created_at', 'olusturulma_tarihi', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

    // 8. tarif_puanlari
    kolonVarsaDegistir($pdo, 'tarif_puanlari', 'user_id', 'kullanici_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'tarif_puanlari', 'recipe_id', 'tarif_id', 'INT NOT NULL');
    kolonVarsaDegistir($pdo, 'tarif_puanlari', 'rating', 'puan', 'TINYINT NOT NULL');
    kolonVarsaDegistir($pdo, 'tarif_puanlari', 'created_at', 'olusturulma_tarihi', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    kolonVarsaDegistir($pdo, 'tarif_puanlari', 'updated_at', 'guncellenme_tarihi', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

    // Yabancı anahtar kontrollerini geri aç
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

} catch (Exception $e) {
    // Hata günlüğüne yaz ama sayfayı kırma
    error_log("Veritabanı geçiş hatası: " . $e->getMessage());
}

