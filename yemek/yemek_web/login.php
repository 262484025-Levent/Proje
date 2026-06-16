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
    $eposta = trim($_POST['eposta'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    
    if (empty($eposta) || empty($sifre)) {
        $error = 'E-posta ve şifre gerekli';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE eposta = ?");
        $stmt->execute([$eposta]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($sifre, $user['sifre'])) {
            $_SESSION['kullanici_id'] = $user['id'];
            $_SESSION['kullanici_adi'] = $user['kullanici_adi'];
            session_regenerate_id(true);
            header('Location: index.php');
            exit;
        } else {
            $error = 'E-posta veya şifre hatalı';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Yemek Paylaşım</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">Yemek<span>Paylaşım</span></a>
    </nav>

    <div class="auth-container">
        <h2>Giriş Yap</h2>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>E-posta</label>
                <input type="email" name="eposta" required value="<?= htmlspecialchars($_POST['eposta'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" name="sifre" required>
            </div>
            <button type="submit" class="btn btn-primary">Giriş Yap</button>
        </form>
        <p>Hesabın yok mu? <a href="register.php">Kayıt ol</a></p>
    </div>
</body>
</html>
