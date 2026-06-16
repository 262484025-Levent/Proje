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

// Tarifi kontrol et ve kullanıcısı doğru mu kontrol et
$stmt = $pdo->prepare("SELECT kullanici_id FROM tarifler WHERE $recipeIdColumn = ?");
$stmt->execute([$id]);
$recipe = $stmt->fetch();

if (!$recipe || (int)$recipe['kullanici_id'] !== (int)$_SESSION['kullanici_id']) {
    header('Location: index.php');
    exit;
}

// Tarifi sil (beğeni ve yorumlar cascade DELETE ile silinir)
$stmt = $pdo->prepare("DELETE FROM tarifler WHERE $recipeIdColumn = ? AND kullanici_id = ?");
$stmt->execute([$id, $_SESSION['kullanici_id']]);

header('Location: profil.php?deleted=1');
exit;
