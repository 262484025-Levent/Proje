<?php
/*
Adý Soyadý: Levent KUBAÞIK
Öðrenci Numarasý: 262484025
*/
session_start();
require_once __DIR__ . '/db.php';

function girisYapildiMi() {
    return isset($_SESSION['kullanici_id']);
}

function aktifKullaniciyiGetir() {
    if (!girisYapildiMi()) return null;
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, kullanici_adi, eposta, profil_resmi, biyografi FROM kullanicilar WHERE id = ?");
    $stmt->execute([$_SESSION['kullanici_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['profil_resmi'] = $user['profil_resmi'];
        $_SESSION['kullanici_adi'] = $user['kullanici_adi'];
    }
    return $user;
}

function girisGerekli() {
    if (!girisYapildiMi()) {
        header('Location: login.php');
        exit;
    }
}
