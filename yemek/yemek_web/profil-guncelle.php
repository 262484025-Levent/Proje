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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profil.php');
    exit;
}

$kullaniciId = $_SESSION['kullanici_id'];
$biyografi = trim($_POST['bio'] ?? '');
$profilResmiYolu = null;

// Profil fotoğrafı yükleme
if (!empty($_FILES['avatar']['name'])) {
    $file = $_FILES['avatar'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed)) {
        header('Location: profil.php?error=Sadece JPG, PNG, GIF veya WebP yükleyebilirsiniz');
        exit;
    }

    if ($file['size'] > $maxSize) {
        header('Location: profil.php?error=Dosya en fazla 5MB olabilir');
        exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = 'avatar_' . $kullaniciId . '_' . time() . '.' . strtolower($ext);
    $uploadDir = __DIR__ . '/uploads/avatars/';

    // Klasörü oluştur (eğer yoksa)
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadPath = $uploadDir . $newName;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $profilResmiYolu = 'uploads/avatars/' . $newName;
    } else {
        header('Location: profil.php?error=Dosya yüklenemedi');
        exit;
    }
}

// Veritabanı güncelleme
if ($profilResmiYolu) {
    $stmt = $pdo->prepare("UPDATE kullanicilar SET biyografi = ?, profil_resmi = ? WHERE id = ?");
    $stmt->execute([$biyografi, $profilResmiYolu, $kullaniciId]);
} else {
    $stmt = $pdo->prepare("UPDATE kullanicilar SET biyografi = ? WHERE id = ?");
    $stmt->execute([$biyografi, $kullaniciId]);
}

$_SESSION['profil_resmi'] = $profilResmiYolu ?? $_SESSION['profil_resmi'] ?? null;

header('Location: profil.php?success=1');
exit;
