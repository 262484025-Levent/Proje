<?php
/*
Adı Soyadı: Levent KUBAŞIK
Öğrenci Numarası: 262484025
*/
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
require_once 'includes/db.php';
require_once 'includes/auth.php';
girisGerekli();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baslik = trim($_POST['baslik'] ?? '');
    $malzemeler = trim($_POST['malzemeler'] ?? '');
    $hazirlanisi = trim($_POST['hazirlanisi'] ?? '');
    $hazirlama_suresi = !empty($_POST['hazirlama_suresi']) ? (int)$_POST['hazirlama_suresi'] : null;
    $pisirme_suresi = !empty($_POST['pisirme_suresi']) ? (int)$_POST['pisirme_suresi'] : null;
    $kisi_sayisi = !empty($_POST['kisi_sayisi']) ? (int)$_POST['kisi_sayisi'] : 1;
    $resim_url = null;

    if (empty($baslik) || empty($malzemeler) || empty($hazirlanisi)) {
        $error = 'Başlık, malzemeler ve yapılış zorunludur';
    } else {
        // Fotoğraf yükleme
        if (!empty($_FILES['photo']['name'])) {
            $file = $_FILES['photo'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed)) {
                $error = 'Sadece JPG, PNG, GIF veya WebP yükleyebilirsiniz';
            } elseif ($file['size'] > $maxSize) {
                $error = 'Dosya en fazla 5MB olabilir';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = 'recipe_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($ext);
                $uploadDir = __DIR__ . '/uploads/recipes/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $uploadPath = $uploadDir . $newName;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $resim_url = 'uploads/recipes/' . $newName;
                } else {
                    $error = 'Fotoğraf yüklenemedi';
                }
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare("
                INSERT INTO tarifler (kullanici_id, baslik, malzemeler, hazirlanisi, hazirlama_suresi, pisirme_suresi, kisi_sayisi, resim_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['kullanici_id'], $baslik, $malzemeler, $hazirlanisi, $hazirlama_suresi, $pisirme_suresi, $kisi_sayisi, $resim_url]);
            header('Location: tarif-detay.php?id=' . $pdo->lastInsertId());
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarif Ekle - Yemek Paylaşım</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">Yemek<span>Paylaşım</span></a>
        <div class="nav-links">
            <a href="index.php">Ana Sayfa</a>
            <?php if (girisYapildiMi()): ?>
                <a href="tarif-ekle.php" class="btn btn-primary nav-add-btn" style="background: var(--primary-dark);">+ Tarif Ekle</a>
                <a href="profil.php" class="nav-avatar-link" title="Profilim">
                    <?php if (!empty($_SESSION['profil_resmi'])): ?>
                        <img src="<?= htmlspecialchars($_SESSION['profil_resmi']) ?>" class="nav-avatar" alt="Profil">
                    <?php else: ?>
                        <div class="nav-avatar nav-avatar-placeholder"><?= strtoupper(mb_substr($_SESSION['kullanici_adi'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <span class="nav-username"><?= htmlspecialchars($_SESSION['kullanici_adi']) ?></span>
                </a>
                <a href="logout.php" class="btn btn-outline">Çıkış</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline">Giriş</a>
                <a href="register.php" class="btn btn-primary">Kayıt Ol</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <div class="auth-container" style="max-width: 600px; margin: 2rem auto;">
            <h2>Yeni Tarif Ekle</h2>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" class="add-recipe-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Tarif Başlığı *</label>
                    <input type="text" name="baslik" required value="<?= htmlspecialchars($_POST['baslik'] ?? '') ?>" placeholder="Örn: Mercimek Çorbası">
                </div>
                <div class="form-group">
                    <label>Tarif Fotoğrafı</label>
                    <input type="file" name="photo" accept="image/*">
                    <small style="color:var(--text-muted)">JPG, PNG, GIF veya WebP — Maks. 5MB</small>
                </div>
                <div class="form-group">
                    <label>Malzemeler * (Her satıra bir malzeme)</label>
                    <textarea name="malzemeler" required placeholder="1 su bardağı mercimek&#10;1 soğan&#10;2 yemek kaşığı yağ"><?= htmlspecialchars($_POST['malzemeler'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Yapılışı * (Her satıra bir adım)</label>
                    <textarea name="hazirlanisi" required placeholder="1. Mercimeği yıkayın...&#10;2. Soğanı doğrayın..."><?= htmlspecialchars($_POST['hazirlanisi'] ?? '') ?></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Hazırlık Süresi (dk)</label>
                        <input type="number" name="hazirlama_suresi" min="0" value="<?= htmlspecialchars($_POST['hazirlama_suresi'] ?? '') ?>" placeholder="15">
                    </div>
                    <div class="form-group">
                        <label>Pişirme Süresi (dk)</label>
                        <input type="number" name="pisirme_suresi" min="0" value="<?= htmlspecialchars($_POST['pisirme_suresi'] ?? '') ?>" placeholder="30">
                    </div>
                </div>
                <div class="form-group">
                    <label>Kişi Sayısı</label>
                    <input type="number" name="kisi_sayisi" min="1" value="<?= htmlspecialchars($_POST['kisi_sayisi'] ?? '4') ?>" placeholder="4">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.9rem;">Tarifi Paylaş</button>
            </form>
        </div>
    </div>
</body>
</html>
