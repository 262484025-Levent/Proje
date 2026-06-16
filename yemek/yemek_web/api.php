<?php
/*
Adı Soyadı: Levent KUBAŞIK
Öğrenci Numarası: 262484025
*/
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

mb_internal_encoding('UTF-8');

// Veritabanı bağlantısını dahil et
require_once 'includes/db.php';

$islem = $_POST['islem'] ?? $_GET['islem'] ?? '';

if (empty($islem)) {
    echo json_encode(['basarili' => false, 'hata' => 'islem parametresi eksik.']);
    exit;
}

try {
    switch ($islem) {
        // ==========================================
        // KULLANICI İŞLEMLERİ (AUTH)
        // ==========================================
        case 'giris':
            $eposta = trim($_POST['eposta'] ?? '');
            $sifre = $_POST['sifre'] ?? '';

            if (empty($eposta) || empty($sifre)) {
                echo json_encode(['basarili' => false, 'hata' => 'E-posta ve Şifre gereklidir.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE eposta = ? LIMIT 1");
            $stmt->execute([$eposta]);
            $user = $stmt->fetch();

            if ($user && password_verify($sifre, $user['sifre'])) {
                unset($user['sifre']);
                echo json_encode(['basarili' => true, 'kullanici' => $user]);
            } else {
                echo json_encode(['basarili' => false, 'hata' => 'E-posta veya şifre hatalı.']);
            }
            break;

        case 'kayit':
            $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
            $eposta = trim($_POST['eposta'] ?? '');
            $sifre = $_POST['sifre'] ?? '';

            if (empty($kullanici_adi) || empty($eposta) || empty($sifre)) {
                echo json_encode(['basarili' => false, 'hata' => 'Tüm alanları doldurun.']);
                exit;
            }

            if (strlen($sifre) < 6) {
                echo json_encode(['basarili' => false, 'hata' => 'Şifre en az 6 karakter olmalıdır.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE kullanici_adi = ? OR eposta = ? LIMIT 1");
            $stmt->execute([$kullanici_adi, $eposta]);
            if ($stmt->fetch()) {
                echo json_encode(['basarili' => false, 'hata' => 'Bu kullanıcı adı veya e-posta zaten kullanılıyor.']);
                exit;
            }

            $hashedPassword = password_hash($sifre, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO kullanicilar (kullanici_adi, eposta, sifre, profil_resmi) VALUES (?, ?, ?, 'default.png')");
            $stmt->execute([$kullanici_adi, $eposta, $hashedPassword]);
            $insertId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE id = ?");
            $stmt->execute([$insertId]);
            $user = $stmt->fetch();
            unset($user['sifre']);

            echo json_encode(['basarili' => true, 'kullanici' => $user]);
            break;

        // ==========================================
        // TARİF İŞLEMLERİ
        // ==========================================
        case 'tarifleri_getir':
            $search = trim($_POST['arama'] ?? $_GET['arama'] ?? '');
            $loggedInUserId = (int)($_POST['giris_yapan_kullanici_id'] ?? $_GET['giris_yapan_kullanici_id'] ?? 0);

            $query = "
                SELECT r.id, r.kullanici_id, r.baslik, r.aciklama, r.malzemeler, r.hazirlanisi, 
                       r.hazirlama_suresi, r.pisirme_suresi, r.kisi_sayisi, r.resim_url, r.olusturulma_tarihi, 
                       u.kullanici_adi as yazar_adi, u.profil_resmi as yazar_resmi,
                       (SELECT COUNT(*) FROM begeniler WHERE tarif_id = r.id) as begeni_sayisi,
                       (SELECT COUNT(*) FROM yorumlar WHERE tarif_id = r.id) as yorum_sayisi,
                       (SELECT AVG(puan) FROM tarif_puanlari WHERE tarif_id = r.id) as ortalama_puan,
                       (SELECT COUNT(*) FROM tarif_puanlari WHERE tarif_id = r.id) as puan_sayisi,
                       (SELECT COUNT(*) FROM begeniler WHERE kullanici_id = ? AND tarif_id = r.id) as begendi_mi
                FROM tarifler r
                JOIN kullanicilar u ON r.kullanici_id = u.id
            ";
            
            $params = [$loggedInUserId];

            if ($search !== '') {
                $query .= " WHERE r.baslik LIKE ? OR r.aciklama LIKE ? OR r.malzemeler LIKE ?";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            $query .= " ORDER BY r.olusturulma_tarihi DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $recipes = $stmt->fetchAll();

            foreach ($recipes as &$r) {
                $r['id'] = (int)$r['id'];
                $r['kullanici_id'] = (int)$r['kullanici_id'];
                $r['hazirlama_suresi'] = $r['hazirlama_suresi'] !== null ? (int)$r['hazirlama_suresi'] : null;
                $r['pisirme_suresi'] = $r['pisirme_suresi'] !== null ? (int)$r['pisirme_suresi'] : null;
                $r['kisi_sayisi'] = (int)$r['kisi_sayisi'];
                $r['begeni_sayisi'] = (int)$r['begeni_sayisi'];
                $r['yorum_sayisi'] = (int)$r['yorum_sayisi'];
                $r['ortalama_puan'] = $r['ortalama_puan'] !== null ? (double)$r['ortalama_puan'] : 0.0;
                $r['puan_sayisi'] = (int)$r['puan_sayisi'];
                $r['begendi_mi'] = (bool)$r['begendi_mi'];
            }

            echo json_encode(['basarili' => true, 'tarifler' => $recipes]);
            break;

        case 'en_iyileri_getir':
            $loggedInUserId = (int)($_POST['giris_yapan_kullanici_id'] ?? $_GET['giris_yapan_kullanici_id'] ?? 0);

            $query = "
                SELECT r.id, r.kullanici_id, r.baslik, r.aciklama, r.malzemeler, r.hazirlanisi, 
                       r.hazirlama_suresi, r.pisirme_suresi, r.kisi_sayisi, r.resim_url, r.olusturulma_tarihi, 
                       u.kullanici_adi as yazar_adi, u.profil_resmi as yazar_resmi,
                       (SELECT COUNT(*) FROM begeniler WHERE tarif_id = r.id) as begeni_sayisi,
                       (SELECT COUNT(*) FROM yorumlar WHERE tarif_id = r.id) as yorum_sayisi,
                       AVG(tp.puan) as ortalama_puan,
                       COUNT(tp.id) as puan_sayisi,
                       (SELECT COUNT(*) FROM begeniler WHERE kullanici_id = ? AND tarif_id = r.id) as begendi_mi
                FROM tarif_puanlari tp
                JOIN tarifler r ON tp.tarif_id = r.id
                JOIN kullanicilar u ON r.kullanici_id = u.id
                GROUP BY r.id, r.kullanici_id, r.baslik, r.aciklama, r.malzemeler, r.hazirlanisi, 
                         r.hazirlama_suresi, r.pisirme_suresi, r.kisi_sayisi, r.resim_url, r.olusturulma_tarihi, 
                         u.kullanici_adi, u.profil_resmi
                ORDER BY ortalama_puan DESC, puan_sayisi DESC, r.olusturulma_tarihi DESC
                LIMIT 10
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$loggedInUserId]);
            $recipes = $stmt->fetchAll();

            foreach ($recipes as &$r) {
                $r['id'] = (int)$r['id'];
                $r['kullanici_id'] = (int)$r['kullanici_id'];
                $r['hazirlama_suresi'] = $r['hazirlama_suresi'] !== null ? (int)$r['hazirlama_suresi'] : null;
                $r['pisirme_suresi'] = $r['pisirme_suresi'] !== null ? (int)$r['pisirme_suresi'] : null;
                $r['kisi_sayisi'] = (int)$r['kisi_sayisi'];
                $r['begeni_sayisi'] = (int)$r['begeni_sayisi'];
                $r['yorum_sayisi'] = (int)$r['yorum_sayisi'];
                $r['ortalama_puan'] = $r['ortalama_puan'] !== null ? (double)$r['ortalama_puan'] : 0.0;
                $r['puan_sayisi'] = (int)$r['puan_sayisi'];
                $r['begendi_mi'] = (bool)$r['begendi_mi'];
            }

            echo json_encode(['basarili' => true, 'tarifler' => $recipes]);
            break;

        case 'kullanici_tariflerini_getir':
            $userId = (int)$_POST['kullanici_id'];
            $loggedInUserId = (int)$_POST['giris_yapan_kullanici_id'];

            $query = "
                SELECT r.id, r.kullanici_id, r.baslik, r.aciklama, r.malzemeler, r.hazirlanisi, 
                       r.hazirlama_suresi, r.pisirme_suresi, r.kisi_sayisi, r.resim_url, r.olusturulma_tarihi, 
                       u.kullanici_adi as yazar_adi, u.profil_resmi as yazar_resmi,
                       (SELECT COUNT(*) FROM begeniler WHERE tarif_id = r.id) as begeni_sayisi,
                       (SELECT COUNT(*) FROM yorumlar WHERE tarif_id = r.id) as yorum_sayisi,
                       (SELECT AVG(puan) FROM tarif_puanlari WHERE tarif_id = r.id) as ortalama_puan,
                       (SELECT COUNT(*) FROM tarif_puanlari WHERE tarif_id = r.id) as puan_sayisi,
                       (SELECT COUNT(*) FROM begeniler WHERE kullanici_id = ? AND tarif_id = r.id) as begendi_mi
                FROM tarifler r
                JOIN kullanicilar u ON r.kullanici_id = u.id
                WHERE r.kullanici_id = ?
                ORDER BY r.olusturulma_tarihi DESC
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$loggedInUserId, $userId]);
            $recipes = $stmt->fetchAll();

            foreach ($recipes as &$r) {
                $r['id'] = (int)$r['id'];
                $r['kullanici_id'] = (int)$r['kullanici_id'];
                $r['hazirlama_suresi'] = $r['hazirlama_suresi'] !== null ? (int)$r['hazirlama_suresi'] : null;
                $r['pisirme_suresi'] = $r['pisirme_suresi'] !== null ? (int)$r['pisirme_suresi'] : null;
                $r['kisi_sayisi'] = (int)$r['kisi_sayisi'];
                $r['begeni_sayisi'] = (int)$r['begeni_sayisi'];
                $r['yorum_sayisi'] = (int)$r['yorum_sayisi'];
                $r['ortalama_puan'] = $r['ortalama_puan'] !== null ? (double)$r['ortalama_puan'] : 0.0;
                $r['puan_sayisi'] = (int)$r['puan_sayisi'];
                $r['begendi_mi'] = (bool)$r['begendi_mi'];
            }

            echo json_encode(['basarili' => true, 'tarifler' => $recipes]);
            break;

        case 'tarif_detaylarini_getir':
            $recipeId = (int)$_POST['tarif_id'];
            $loggedInUserId = (int)$_POST['giris_yapan_kullanici_id'];

            $query = "
                SELECT r.id, r.kullanici_id, r.baslik, r.aciklama, r.malzemeler, r.hazirlanisi, 
                       r.hazirlama_suresi, r.pisirme_suresi, r.kisi_sayisi, r.resim_url, r.olusturulma_tarihi, 
                       u.kullanici_adi as yazar_adi, u.profil_resmi as yazar_resmi,
                       (SELECT COUNT(*) FROM begeniler WHERE tarif_id = r.id) as begeni_sayisi,
                       (SELECT COUNT(*) FROM yorumlar WHERE tarif_id = r.id) as yorum_sayisi,
                       (SELECT AVG(puan) FROM tarif_puanlari WHERE tarif_id = r.id) as ortalama_puan,
                       (SELECT COUNT(*) FROM tarif_puanlari WHERE tarif_id = r.id) as puan_sayisi,
                       (SELECT COUNT(*) FROM begeniler WHERE kullanici_id = ? AND tarif_id = r.id) as begendi_mi
                FROM tarifler r
                JOIN kullanicilar u ON r.kullanici_id = u.id
                WHERE r.id = ?
                LIMIT 1
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$loggedInUserId, $recipeId]);
            $recipe = $stmt->fetch();

            if ($recipe) {
                $recipe['id'] = (int)$recipe['id'];
                $recipe['kullanici_id'] = (int)$recipe['kullanici_id'];
                $recipe['hazirlama_suresi'] = $recipe['hazirlama_suresi'] !== null ? (int)$recipe['hazirlama_suresi'] : null;
                $recipe['pisirme_suresi'] = $recipe['pisirme_suresi'] !== null ? (int)$recipe['pisirme_suresi'] : null;
                $recipe['kisi_sayisi'] = (int)$recipe['kisi_sayisi'];
                $recipe['begeni_sayisi'] = (int)$recipe['begeni_sayisi'];
                $recipe['yorum_sayisi'] = (int)$recipe['yorum_sayisi'];
                $recipe['ortalama_puan'] = $recipe['ortalama_puan'] !== null ? (double)$recipe['ortalama_puan'] : 0.0;
                $recipe['puan_sayisi'] = (int)$recipe['puan_sayisi'];
                $recipe['begendi_mi'] = (bool)$recipe['begendi_mi'];
                echo json_encode(['basarili' => true, 'tarif' => $recipe]);
            } else {
                echo json_encode(['basarili' => false, 'hata' => 'Tarif bulunamadÄ±.']);
            }
            break;

        case 'tarif_ekle':
            $userId = (int)$_POST['kullanici_id'];
            $title = trim($_POST['baslik'] ?? '');
            $description = trim($_POST['aciklama'] ?? '');
            $ingredients = trim($_POST['malzemeler'] ?? '');
            $instructions = trim($_POST['hazirlanisi'] ?? '');
            $prepTime = $_POST['hazirlama_suresi'] !== '' && $_POST['hazirlama_suresi'] !== null ? (int)$_POST['hazirlama_suresi'] : null;
            $cookTime = $_POST['pisirme_suresi'] !== '' && $_POST['pisirme_suresi'] !== null ? (int)$_POST['pisirme_suresi'] : null;
            $servings = (int)($_POST['kisi_sayisi'] ?? 1);
            
            $image_url = null;

            // Multipart fotoğraf yükleme (image_picker ile yüklenen dosya)
            if (!empty($_FILES['photo']['name'])) {
                $file = $_FILES['photo'];
                $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5MB

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (in_array(strtolower($file['type']), $allowed) || in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    if ($file['size'] <= $maxSize) {
                        $newName = 'recipe_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($ext);
                        $uploadDir = __DIR__ . '/uploads/recipes/';

                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $uploadPath = $uploadDir . $newName;

                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            $image_url = 'uploads/recipes/' . $newName;
                        }
                    }
                }
            } else if (!empty($_POST['resim_url'])) {
                $image_url = trim($_POST['resim_url']);
            }

            if (empty($title) || empty($ingredients) || empty($instructions)) {
                echo json_encode(['basarili' => false, 'hata' => 'Lütfen gerekli alanları doldurun.']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO tarifler (kullanici_id, baslik, aciklama, malzemeler, hazirlanisi, hazirlama_suresi, pisirme_suresi, kisi_sayisi, resim_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $title, $description, $ingredients, $instructions, $prepTime, $cookTime, $servings, $image_url]);
            $insertId = $pdo->lastInsertId();

            echo json_encode(['basarili' => true, 'eklenen_id' => (int)$insertId]);
            break;

        case 'tarif_sil':
            $recipeId = (int)$_POST['tarif_id'];
            $userId = (int)$_POST['kullanici_id'];

            $stmt = $pdo->prepare("SELECT id FROM tarifler WHERE id = ? AND kullanici_id = ? LIMIT 1");
            $stmt->execute([$recipeId, $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['basarili' => false, 'hata' => 'Bu tarifi silme yetkiniz yok.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM tarifler WHERE id = ?");
            $stmt->execute([$recipeId]);

            echo json_encode(['basarili' => true]);
            break;

        // ==========================================
        // BEĞENİ VE PUANLAMA
        // ==========================================
        case 'begeni_durumunu_degistir':
            $userId = (int)$_POST['kullanici_id'];
            $recipeId = (int)$_POST['tarif_id'];

            $stmt = $pdo->prepare("SELECT id FROM begeniler WHERE kullanici_id = ? AND tarif_id = ? LIMIT 1");
            $stmt->execute([$userId, $recipeId]);
            $liked = $stmt->fetch();

            if ($liked) {
                $stmt = $pdo->prepare("DELETE FROM begeniler WHERE kullanici_id = ? AND tarif_id = ?");
                $stmt->execute([$userId, $recipeId]);
                $isLikedNow = false;
            } else {
                $stmt = $pdo->prepare("INSERT INTO begeniler (kullanici_id, tarif_id) VALUES (?, ?)");
                $stmt->execute([$userId, $recipeId]);
                $isLikedNow = true;
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM begeniler WHERE tarif_id = ?");
            $stmt->execute([$recipeId]);
            $count = (int)$stmt->fetchColumn();

            echo json_encode(['basarili' => true, 'begendi_mi' => $isLikedNow, 'begeni_sayisi' => $count]);
            break;

        case 'tarif_puanla':
            $userId = (int)$_POST['kullanici_id'];
            $recipeId = (int)$_POST['tarif_id'];
            $rating = (int)$_POST['puan'];

            $stmt = $pdo->prepare("
                INSERT INTO tarif_puanlari (kullanici_id, tarif_id, puan) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE puan = ?
            ");
            $stmt->execute([$userId, $recipeId, $rating, $rating]);

            $stmt = $pdo->prepare("
                SELECT AVG(puan) as average_rating, COUNT(*) as rating_count 
                FROM tarif_puanlari 
                WHERE tarif_id = ?
            ");
            $stmt->execute([$recipeId]);
            $stats = $stmt->fetch();

            echo json_encode([
                'basarili' => true,
                'ortalama_puan' => $stats['average_rating'] !== null ? (double)$stats['average_rating'] : 0.0,
                'puan_sayisi' => (int)$stats['rating_count']
            ]);
            break;

        case 'kullanici_puanini_getir':
            $userId = (int)$_POST['kullanici_id'];
            $recipeId = (int)$_POST['tarif_id'];

            $stmt = $pdo->prepare("SELECT puan FROM tarif_puanlari WHERE kullanici_id = ? AND tarif_id = ? LIMIT 1");
            $stmt->execute([$userId, $recipeId]);
            $rating = (int)($stmt->fetchColumn() ?: 0);

            echo json_encode(['basarili' => true, 'puan' => $rating]);
            break;

        // ==========================================
        // YORUM İŞLEMLERİ
        // ==========================================
        case 'yorumlari_getir':
            $recipeId = (int)$_POST['tarif_id'];

            $stmt = $pdo->prepare("
                SELECT y.id, y.kullanici_id, y.tarif_id, y.icerik, y.olusturulma_tarihi, 
                       k.kullanici_adi as yazar_adi, k.profil_resmi as yazar_resmi
                FROM yorumlar y
                JOIN kullanicilar k ON y.kullanici_id = k.id
                WHERE y.tarif_id = ?
                ORDER BY y.olusturulma_tarihi DESC
            ");
            $stmt->execute([$recipeId]);
            $comments = $stmt->fetchAll();

            foreach ($comments as &$c) {
                $c['id'] = (int)$c['id'];
                $c['kullanici_id'] = (int)$c['kullanici_id'];
                $c['tarif_id'] = (int)$c['tarif_id'];

                // Alt cevapları (Yoruma yorum yapma) getir
                $replyStmt = $pdo->prepare("
                    SELECT yc.id, yc.kullanici_id, yc.yorum_id, yc.ust_cevap_id, yc.icerik, yc.olusturulma_tarihi,
                           k.kullanici_adi as yazar_adi, k.profil_resmi as yazar_resmi
                    FROM yorum_cevaplari yc
                    JOIN kullanicilar k ON yc.kullanici_id = k.id
                    WHERE yc.yorum_id = ?
                    ORDER BY yc.olusturulma_tarihi ASC
                ");
                $replyStmt->execute([$c['id']]);
                $replies = $replyStmt->fetchAll();

                foreach ($replies as &$r) {
                    $r['id'] = (int)$r['id'];
                    $r['kullanici_id'] = (int)$r['kullanici_id'];
                    $r['yorum_id'] = (int)$r['yorum_id'];
                    $r['ust_cevap_id'] = $r['ust_cevap_id'] !== null ? (int)$r['ust_cevap_id'] : null;
                }
                $c['cevaplar'] = $replies;
            }

            echo json_encode(['basarili' => true, 'yorumlar' => $comments]);
            break;

        case 'yorum_ekle':
            $userId = (int)$_POST['kullanici_id'];
            $recipeId = (int)$_POST['tarif_id'];
            $content = trim($_POST['icerik'] ?? '');

            if (empty($content)) {
                echo json_encode(['basarili' => false, 'hata' => 'Yorum iÃ§eriÄŸi boÅŸ olamaz.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO yorumlar (kullanici_id, tarif_id, icerik) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $recipeId, $content]);
            $insertId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                SELECT y.id, y.kullanici_id, y.tarif_id, y.icerik, y.olusturulma_tarihi, 
                       k.kullanici_adi as yazar_adi, k.profil_resmi as yazar_resmi
                FROM yorumlar y
                JOIN kullanicilar k ON y.kullanici_id = k.id
                WHERE y.id = ? LIMIT 1
            ");
            $stmt->execute([$insertId]);
            $comment = $stmt->fetch();

            if ($comment) {
                $comment['id'] = (int)$comment['id'];
                $comment['kullanici_id'] = (int)$comment['kullanici_id'];
                $comment['tarif_id'] = (int)$comment['tarif_id'];
                $comment['cevaplar'] = [];
                echo json_encode(['basarili' => true, 'yorum' => $comment]);
            } else {
                echo json_encode(['basarili' => false, 'hata' => 'Yorum eklenirken hata oluÅŸtu.']);
            }
            break;

        case 'yorum_cevap_ekle':
            $userId = (int)$_POST['kullanici_id'];
            $commentId = (int)$_POST['yorum_id'];
            $content = trim($_POST['icerik'] ?? '');
            $parentReplyId = $_POST['ust_cevap_id'] !== '' && $_POST['ust_cevap_id'] !== null ? (int)$_POST['ust_cevap_id'] : null;

            if (empty($content)) {
                echo json_encode(['basarili' => false, 'hata' => 'Cevap iÃ§eriÄŸi boÅŸ olamaz.']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO yorum_cevaplari (kullanici_id, yorum_id, ust_cevap_id, icerik) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $commentId, $parentReplyId, $content]);
            $insertId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                SELECT yc.id, yc.kullanici_id, yc.yorum_id, yc.ust_cevap_id, yc.icerik, yc.olusturulma_tarihi,
                       k.kullanici_adi as yazar_adi, k.profil_resmi as yazar_resmi
                FROM yorum_cevaplari yc
                JOIN kullanicilar k ON yc.kullanici_id = k.id
                WHERE yc.id = ? LIMIT 1
            ");
            $stmt->execute([$insertId]);
            $reply = $stmt->fetch();

            if ($reply) {
                $reply['id'] = (int)$reply['id'];
                $reply['kullanici_id'] = (int)$reply['kullanici_id'];
                $reply['yorum_id'] = (int)$reply['yorum_id'];
                $reply['ust_cevap_id'] = $reply['ust_cevap_id'] !== null ? (int)$reply['ust_cevap_id'] : null;
                echo json_encode(['basarili' => true, 'cevap' => $reply]);
            } else {
                echo json_encode(['basarili' => false, 'hata' => 'Cevap eklenirken hata oluÅŸtu.']);
            }
            break;

        // ==========================================
        // PROFİL İŞLEMLERİ
        // ==========================================
        case 'kullanici_profilini_getir':
            $userId = (int)$_POST['kullanici_id'];

            $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user) {
                unset($user['sifre']);
                echo json_encode(['basarili' => true, 'kullanici' => $user]);
            } else {
                echo json_encode(['basarili' => false, 'hata' => 'KullanÄ±cÄ± bulunamadÄ±.']);
            }
            break;

        case 'profil_guncelle':
            $userId = (int)$_POST['kullanici_id'];
            $username = trim($_POST['kullanici_adi'] ?? '');
            $bio = trim($_POST['biyografi'] ?? '');
            
            $avatar = null;

            // Profil fotoğrafı yükleme işlemi
            if (!empty($_FILES['photo']['name'])) {
                $file = $_FILES['photo'];
                $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5MB

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (in_array(strtolower($file['type']), $allowed) || in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    if ($file['size'] <= $maxSize) {
                        $newName = 'avatar_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($ext);
                        $uploadDir = __DIR__ . '/uploads/avatars/';

                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $uploadPath = $uploadDir . $newName;

                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            $avatar = 'uploads/avatars/' . $newName;
                        }
                    }
                }
            } else if (!empty($_POST['profil_resmi'])) {
                $avatar = trim($_POST['profil_resmi']);
            }

            if (empty($username)) {
                echo json_encode(['basarili' => false, 'hata' => 'Kullanıcı  adı boş bırakılamaz.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE kullanici_adi = ? AND id != ? LIMIT 1");
            $stmt->execute([$username, $userId]);
            if ($stmt->fetch()) {
                echo json_encode(['basarili' => false, 'hata' => 'Bu kullanıcı adı zaten başka bir üye tarafından kullanılıyor.']);
                exit;
            }

            if ($avatar !== null) {
                $stmt = $pdo->prepare("UPDATE kullanicilar SET kullanici_adi = ?, biyografi = ?, profil_resmi = ? WHERE id = ?");
                $stmt->execute([$username, $bio, $avatar, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE kullanicilar SET kullanici_adi = ?, biyografi = ? WHERE id = ?");
                $stmt->execute([$username, $bio, $userId]);
            }

            $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            unset($user['sifre']);

            echo json_encode(['basarili' => true, 'kullanici' => $user]);
            break;

        default:
            echo json_encode(['basarili' => false, 'hata' => 'Bilinmeyen islem parametresi.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['basarili' => false, 'hata' => 'Sunucu hatasÄ±: ' . $e->getMessage()]);
}
?>