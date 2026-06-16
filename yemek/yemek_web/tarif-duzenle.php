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

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

$recipeIdColumn = tarifIdKolonunuGetir($pdo);

$stmt = $pdo->prepare("SELECT * FROM tarifler WHERE $recipeIdColumn = ? AND kullanici_id = ?");
$stmt->execute([$id, $_SESSION['kullanici_id']]);
$recipe = $stmt->fetch();

if (!$recipe) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baslik = trim($_POST['baslik'] ?? '');
    $malzemeler = trim($_POST['malzemeler'] ?? '');
    $hazirlanisi = trim($_POST['hazirlanisi'] ?? '');
    $hazirlama_suresi = !empty($_POST['hazirlama_suresi']) ? (int)$_POST['hazirlama_suresi'] : null;
    $pisirme_suresi = !empty($_POST['pisirme_suresi']) ? (int)$_POST['pisirme_suresi'] : null;
    $kisi_sayisi = !empty($_POST['kisi_sayisi']) ? (int)$_POST['kisi_sayisi'] : 1;

    if (empty($baslik) || empty($malzemeler) || empty($hazirlanisi)) {
        $error = 'Başlık, malzemeler ve yapılış zorunludur';
    } else {
        $stmt = $pdo->prepare("
            UPDATE tarifler SET baslik = ?, malzemeler = ?, hazirlanisi = ?, hazirlama_suresi = ?, pisirme_suresi = ?, kisi_sayisi = ?
            WHERE $recipeIdColumn = ? AND kullanici_id = ?
        ");
        $stmt->execute([$baslik, $malzemeler, $hazirlanisi, $hazirlama_suresi, $pisirme_suresi, $kisi_sayisi, $id, $_SESSION['kullanici_id']]);
        header('Location: tarif-detay.php?id=' . $id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarif Düzenle - Yemek Paylaşım</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">Yemek<span>Paylaşım</span></a>
        <div class="nav-links">
            <a href="index.php">Ana Sayfa</a>
            <?php if (girisYapildiMi()): ?>
                <a href="tarif-ekle.php" class="btn btn-primary nav-add-btn">+ Tarif Ekle</a>
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
            <h2>Tarifi Düzenle</h2>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" class="add-recipe-form">
                <div class="form-group">
                    <label>Tarif Başlığı *</label>
                    <input type="text" name="baslik" required value="<?= htmlspecialchars($recipe['baslik']) ?>" placeholder="Örn: Mercimek Çorbası">
                </div>
                <div class="form-group">
                    <label>Malzemeler * (Her satıra bir malzeme)</label>
                    <textarea name="malzemeler" required placeholder="1 su bardağı mercimek&#10;1 soğan&#10;2 yemek kaşığı yağ"><?= htmlspecialchars($recipe['malzemeler']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Yapılışı * (Her satıra bir adım)</label>
                    <textarea name="hazirlanisi" required placeholder="1. Mercimeği yıkayın...&#10;2. Soğanı doğrayın..."><?= htmlspecialchars($recipe['hazirlanisi']) ?></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Hazırlık Süresi (dk)</label>
                        <input type="number" name="hazirlama_suresi" min="0" value="<?= htmlspecialchars($recipe['hazirlama_suresi'] ?? '') ?>" placeholder="15">
                    </div>
                    <div class="form-group">
                        <label>Pişirme Süresi (dk)</label>
                        <input type="number" name="pisirme_suresi" min="0" value="<?= htmlspecialchars($recipe['pisirme_suresi'] ?? '') ?>" placeholder="30">
                    </div>
                </div>
                <div class="form-group">
                    <label>Kişi Sayısı</label>
                    <input type="number" name="kisi_sayisi" min="1" value="<?= htmlspecialchars($recipe['kisi_sayisi'] ?? '4') ?>" placeholder="4">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.9rem;">💾 Güncelle</button>
            </form>
        </div>
    </div>
</body>
</html>
