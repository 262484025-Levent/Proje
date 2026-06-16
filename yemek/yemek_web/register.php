<?php
/*
Adı Soyadı: Levent KUBAŞIK
Öğrenci Numarası: 262484025
*/
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (girisYapildiMi()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
    $eposta = trim($_POST['eposta'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    
    if (empty($kullanici_adi) || empty($eposta) || empty($sifre)) {
        $error = 'Tüm alanları doldurun';
    } elseif (strlen($sifre) < 6) {
        $error = 'Şifre en az 6 karakter olmalı';
    } else {
        $hashedPassword = password_hash($sifre, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO kullanicilar (kullanici_adi, eposta, sifre) VALUES (?, ?, ?)");
            $stmt->execute([$kullanici_adi, $eposta, $hashedPassword]);
            $_SESSION['kullanici_id'] = $pdo->lastInsertId();
            $_SESSION['kullanici_adi'] = $kullanici_adi;
            session_regenerate_id(true);
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Bu kullanıcı adı veya e-posta zaten kullanılıyor';
            } else {
                $error = 'Kayıt sırasında hata oluştu';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Yemek Paylaşım</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">Yemek<span>Paylaşım</span></a>
    </nav>

    <div class="auth-container">
        <h2>Kayıt Ol</h2>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" name="kullanici_adi" required value="<?= htmlspecialchars($_POST['kullanici_adi'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>E-posta</label>
                <input type="email" name="eposta" required value="<?= htmlspecialchars($_POST['eposta'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Şifre (en az 6 karakter)</label>
                <input type="password" name="sifre" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary">Kayıt Ol</button>
        </form>
        <p>Zaten hesabın var mı? <a href="login.php">Giriş yap</a></p>
    </div>
</body>
</html>
