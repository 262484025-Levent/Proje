<?php
/*
Adı Soyadı: Levent KUBAŞIK
Öğrenci Numarası: 262484025
*/
// Tüm sayfalarda ortak navbar - include et
// $activeNav değişkeni ile aktif sayfayı belirtin ('home', 'add', 'profile')
$activeNav = $activeNav ?? '';
$currentUser = girisYapildiMi() ? null : null;
?>
<nav class="navbar">
    <a href="index.php" class="navbar-brand">🞽︞ Yemek<span>Paylaşım</span></a>
    <div class="nav-links">
        <a href="index.php" class="<?= $activeNav === 'home' ? 'nav-active' : '' ?>">Ana Sayfa</a>
        <?php if (girisYapildiMi()): ?>
            <a href="tarif-ekle.php" class="btn btn-primary nav-add-btn <?= $activeNav === 'add' ? 'active' : '' ?>">+ Tarif Ekle</a>
            <a href="profil.php" class="nav-avatar-link <?= $activeNav === 'profile' ? 'nav-active' : '' ?>" title="Profilim">
                <?php if (!empty($_SESSION['avatar'])): ?>
                    <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" class="nav-avatar" alt="Profil">
                <?php else: ?>
                    <div class="nav-avatar nav-avatar-placeholder"><?= strtoupper(mb_substr($_SESSION['username'] ?? 'U', 0, 1)) ?></div>
                <?php endif; ?>
                <span class="nav-username"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
            </a>
            <a href="logout.php" class="btn btn-outline">Çıkış</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-outline">Giriş</a>
            <a href="register.php" class="btn btn-primary">Kayıt Ol</a>
        <?php endif; ?>
    </div>
</nav>
