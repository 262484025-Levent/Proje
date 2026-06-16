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

$userId = $_SESSION['kullanici_id'];

// Profil sahibi kimdir? Başkasının profiline bakılıyor mu?
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : $userId;
$isOwn = ($profileId === $userId);

// Beğeni işlemi (feed üzerinden)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_recipe_id'])) {
    $recipe_id = (int)$_POST['like_recipe_id'];
    $check = $pdo->prepare("SELECT id FROM begeniler WHERE kullanici_id = ? AND tarif_id = ?");
    $check->execute([$_SESSION['kullanici_id'], $recipe_id]);
    $liked = false;
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM begeniler WHERE kullanici_id = ? AND tarif_id = ?")->execute([$_SESSION['kullanici_id'], $recipe_id]);
    } else {
        $pdo->prepare("INSERT INTO begeniler (kullanici_id, tarif_id) VALUES (?, ?)")->execute([$_SESSION['kullanici_id'], $recipe_id]);
        $liked = true;
    }
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM begeniler WHERE tarif_id = ?");
        $countStmt->execute([$recipe_id]);
        echo json_encode(['success' => true, 'liked' => $liked, 'count' => $countStmt->fetchColumn()]);
        exit;
    }
    
    header('Location: profil.php' . ($isOwn ? '' : '?id='.$profileId) . '#post-' . $recipe_id);
    exit;
}

// Yorum ekleme (feed üzerinden)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_recipe_id']) && isset($_POST['comment_text'])) {
    $recipe_id = (int)$_POST['comment_recipe_id'];
    $content = trim($_POST['comment_text']);
    if (!empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO yorumlar (kullanici_id, tarif_id, icerik) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['kullanici_id'], $recipe_id, $content]);
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM yorumlar WHERE tarif_id = ?");
                $countStmt->execute([$recipe_id]);
                echo json_encode(['success' => true, 'count' => $countStmt->fetchColumn(), 'comment' => $content, 'author' => $_SESSION['kullanici_adi']]);
                exit;
            }
            
            header('Location: profil.php' . ($isOwn ? '' : '?id='.$profileId) . '#post-' . $recipe_id);
            exit;
        } catch(Exception $e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }
    }
}

// Kullanıcı bilgileri
$stmt = $pdo->prepare("SELECT id, kullanici_adi, eposta, profil_resmi, biyografi, olusturulma_tarihi FROM kullanicilar WHERE id = ?");
$stmt->execute([$profileId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

// Kullanıcının tarifleri
$recipeIdColumn = tarifIdKolonunuGetir($pdo);
$recipeIdExpr = 'r.' . $recipeIdColumn;

$stmt = $pdo->prepare("
    SELECT r.*, $recipeIdExpr as id,
    (SELECT COUNT(*) FROM begeniler WHERE tarif_id = $recipeIdExpr) as like_count,
    (SELECT COUNT(*) FROM yorumlar WHERE tarif_id = $recipeIdExpr) as comment_count,
    (SELECT icerik FROM yorumlar WHERE tarif_id = $recipeIdExpr ORDER BY olusturulma_tarihi DESC LIMIT 1) as latest_comment,
    (SELECT kullanicilar.kullanici_adi FROM yorumlar JOIN kullanicilar ON yorumlar.kullanici_id = kullanicilar.id WHERE yorumlar.tarif_id = $recipeIdExpr ORDER BY yorumlar.olusturulma_tarihi DESC LIMIT 1) as latest_comment_author,
    (SELECT id FROM begeniler WHERE kullanici_id = ? AND tarif_id = $recipeIdExpr) as is_liked

    FROM tarifler r
    WHERE r.kullanici_id = ?
    ORDER BY r.olusturulma_tarihi DESC
");
$stmt->execute([$userId, $profileId]);
$userRecipes = $stmt->fetchAll();

foreach ($userRecipes as &$r) {
    $r['is_liked'] = (bool) $r['is_liked'];
}
unset($r);

// İstatistikler
$totalRecipes = count($userRecipes);
$totalLikes = array_sum(array_column($userRecipes, 'like_count'));

$success = isset($_GET['success']);
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['kullanici_adi']) ?> - Profil</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand" onclick="localStorage.removeItem('scrollpos');">Yemek<span>Paylaşım</span></a>
        <div class="nav-links">
            <a href="index.php" onclick="localStorage.removeItem('scrollpos');">Ana Sayfa</a>
            <?php if (girisYapildiMi()): ?>
                <a href="tarif-ekle.php" class="btn btn-primary nav-add-btn">+ Tarif Ekle</a>
                <a href="profil.php" class="nav-avatar-link nav-active" title="Profilim" onclick="localStorage.removeItem('scrollpos');">
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

    <div class="profile-banner"></div>

    <div class="container">
        <div class="profile-card">
            <div class="profile-avatar-wrap">
                <?php if (!empty($user['profil_resmi'])): ?>
                    <img src="<?= htmlspecialchars($user['profil_resmi']) ?>" class="profile-avatar" alt="Profil fotoğrafı" id="avatarPreview">
                <?php else: ?>
                    <div class="profile-avatar profile-avatar-default" id="avatarPreview">
                        <?= strtoupper(mb_substr($user['kullanici_adi'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <?php if ($isOwn): ?>
                    <label for="avatarInput" class="avatar-edit-btn" title="Fotoğraf değiştir">📷</label>
                <?php endif; ?>
            </div>

            <div class="profile-info">
                <h1 class="profile-username">@<?= htmlspecialchars($user['kullanici_adi']) ?></h1>
                <?php if (!empty($user['biyografi'])): ?>
                    <p class="profile-bio"><?= nl2br(htmlspecialchars($user['biyografi'])) ?></p>
                <?php elseif ($isOwn): ?>
                    <p class="profile-bio profile-bio-empty">Henüz bir biyografi eklemediniz. Aşağıdan düzenleyebilirsiniz.</p>
                <?php endif; ?>
                <div class="profile-stats">
                    <div class="profile-stat">
                        <span class="stat-number"><?= $totalRecipes ?></span>
                        <span class="stat-label">Tarif</span>
                    </div>
                    <div class="profile-stat">
                        <span class="stat-number"><?= $totalLikes ?></span>
                        <span class="stat-label">Beğeni</span>
                    </div>
                    <div class="profile-stat">
                        <span class="stat-number"><?= date('Y', strtotime($user['olusturulma_tarihi'])) ?></span>
                        <span class="stat-label">Üye yılı</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isOwn): ?>
        <!-- Profil Düzenleme Formu -->
        <div class="profile-edit-section" id="editSection" style="display:none;">
            <div class="profile-edit-card">
                <h3>Profili Düzenle</h3>
                <?php if ($success): ?>
                    <div class="alert alert-success">✅ Profil güncellendi!</div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="profil-guncelle.php" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Profil Fotoğrafı</label>
                        <input type="file" name="avatar" id="avatarInput" accept="image/*" onchange="profilFotografiOnizle(this)">
                        <small style="color:var(--text-muted)">JPG, PNG, GIF veya WebP — Maks. 5MB</small>
                    </div>
                    <div class="form-group">
                        <label>Biyografi</label>
                        <textarea name="bio" placeholder="Kendinizi tanıtın..." rows="3"><?= htmlspecialchars($user['biyografi'] ?? '') ?></textarea>
                    </div>
                    <div style="display:flex; gap:0.8rem;">
                        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                        <button type="button" class="btn btn-outline" onclick="duzenlemeyiDegistir()">İptal</button>
                    </div>
                </form>
            </div>
        </div>

        <div style="text-align:center; margin: 1rem 0;">
            <button class="btn btn-outline" onclick="duzenlemeyiDegistir()" id="editToggleBtn">Profili Düzenle</button>
        </div>
        <?php endif; ?>

        <!-- Kullanıcının Tarifleri -->
        <div class="profile-recipes-section">
            <h2 class="section-title">
                <?= $isOwn ? 'Paylaşımlarım' : htmlspecialchars($user['kullanici_adi']) . ' adlı kullanıcının tarifleri' ?>
            </h2>

            <?php if (empty($userRecipes)): ?>
                <div class="empty-state">
                    <div style="font-size:3rem; margin-bottom:1rem;">&#127869;&#65039;</div>
                    <h3><?= $isOwn ? 'Henüz tarif paylaşmadınız' : 'Henüz tarif yok' ?></h3>
                    <?php if ($isOwn): ?>
                        <p>İlk tarifinizi paylaşın ve lezzetlerinizi dünyayla buluşturun!</p>
                        <a href="tarif-ekle.php" class="btn btn-primary" style="margin-top:1rem;">+ Tarif Ekle</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="recipes-grid profile-recipes-grid">
                    <?php foreach ($userRecipes as $r): ?>
                    <div class="recipe-card-wrapper" style="position: relative;" id="post-<?= $r['id'] ?>">
                        <a href="tarif-detay.php?id=<?= $r['id'] ?>" class="recipe-card-link" style="display: block; position: relative; z-index: 1;">
                            <article class="recipe-card">
                                <div class="recipe-card-image">
                                    <?php if (!empty($r['resim_url'])): ?>
                                    <img src="<?= htmlspecialchars($r['resim_url']) ?>" alt="<?= htmlspecialchars($r['baslik']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                    &#127869;&#65039;
                                    <?php endif; ?>
                                </div>
                                <div class="recipe-card-body">
                                    <h3><?= htmlspecialchars($r['baslik']) ?></h3>
                                    <p class="meta"><?= date('d.m.Y', strtotime($r['olusturulma_tarihi'])) ?></p>
                                    <p class="description"><?= htmlspecialchars(mb_substr($r['aciklama'] ?? '', 0, 90)) ?></p>
                                    
                                    <div class="recipe-actions-bar" onclick="event.stopPropagation();" style="display: flex; gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); position: relative; z-index: 10;">
                                        <?php if (girisYapildiMi()): ?>
                                        <form method="POST" style="margin: 0;" id="like-form-<?= $r['id'] ?>">
                                            <input type="hidden" name="like_recipe_id" value="<?= $r['id'] ?>">
                                            <button type="button" onclick="begeniGonder(event, <?= $r['id'] ?>)" class="action-btn <?= $r['is_liked'] ? 'liked' : '' ?>" id="like-btn-<?= $r['id'] ?>" style="background: none; border: none; cursor: pointer; color: var(--text-muted); font-weight: 500; font-family: inherit; font-size: 0.95rem;">
                                                <span style="color: <?= $r['is_liked'] ? 'var(--primary-dark)' : 'inherit' ?>; font-size: 1.1rem; vertical-align: middle;" id="like-icon-<?= $r['id'] ?>"><?= $r['is_liked'] ? '➤︞' : '🤞' ?></span> <span id="like-count-<?= $r['id'] ?>"><?= $r['like_count'] ?></span>
                                            </button>
                                        </form>
                                        <button type="button" class="action-btn" onclick="event.preventDefault(); event.stopPropagation(); yorumKutusuDegistir(<?= $r['id'] ?>)" style="background: none; border: none; cursor: pointer; color: var(--text-muted); font-weight: 500; font-family: inherit; font-size: 0.95rem;">
                                            <span style="font-size: 1.1rem; vertical-align: middle;">💬</span> Yorum Yap (<span id="comment-metrics-<?= $r['id'] ?>"><?= $r['comment_count'] ?></span>)
                                        </button>
                                        <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.9rem;">➤︞ <?= $r['like_count'] ?></span>
                                        <span style="color: var(--text-muted); font-size: 0.9rem;">💬 <?= $r['comment_count'] ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (girisYapildiMi()): ?>
                                    <div class="inline-comment-box" id="comment-box-<?= $r['id'] ?>" onclick="event.preventDefault(); event.stopPropagation();" style="display: none; padding-top: 1rem; margin-top: 0.5rem; position: relative; z-index: 10;">
                                        <form method="POST" style="display: flex; gap: 0.5rem; flex-direction: column;" id="comment-form-<?= $r['id'] ?>">
                                            <input type="hidden" name="comment_recipe_id" value="<?= $r['id'] ?>">
                                            <textarea name="comment_text" rows="2" placeholder="Yorumunu yaz..." required style="width: 100%; padding: 0.8rem; border: 2px solid var(--border); border-radius: 8px; font-family: inherit; resize: vertical;" onclick="event.preventDefault(); event.stopPropagation();"></textarea>
                                            <button type="button" onclick="yorumGonder(event, <?= $r['id'] ?>)" class="btn btn-primary" style="align-self: flex-start; padding: 0.5rem 1rem; font-size: 0.9rem;">Kaydet</button>
                                        </form>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($r['latest_comment'])): ?>
                                    <div class="latest-comment" id="latest-comment-bind-<?= $r['id'] ?>" onclick="event.preventDefault(); event.stopPropagation();" style="margin-top: 1rem; padding: 0.8rem; background: var(--bg); border-radius: 8px; font-size: 0.9rem; position: relative; z-index: 10;">
                                        <strong style="color: var(--primary);" id="latest-comment-author-<?= $r['id'] ?>">@<?= htmlspecialchars($r['latest_comment_author']) ?>:</strong> <span style="color: var(--text);" id="latest-comment-content-<?= $r['id'] ?>"><?= htmlspecialchars($r['latest_comment']) ?></span>
                                    </div>
                                    <?php else: ?>
                                    <div class="latest-comment" id="latest-comment-bind-<?= $r['id'] ?>" onclick="event.preventDefault(); event.stopPropagation();" style="display:none; margin-top: 1rem; padding: 0.8rem; background: var(--bg); border-radius: 8px; font-size: 0.9rem; position: relative; z-index: 10;">
                                        <strong style="color: var(--primary);" id="latest-comment-author-<?= $r['id'] ?>"></strong> <span style="color: var(--text);" id="latest-comment-content-<?= $r['id'] ?>"></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function duzenlemeyiDegistir() {
        const section = document.getElementById('editSection');
        const btn = document.getElementById('editToggleBtn');
        if (section.style.display === 'none') {
            section.style.display = 'block';
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            btn.textContent = '✕ Kapat';
        } else {
            section.style.display = 'none';
            btn.textContent = 'Profili Düzenle';
        }
    }

    function profilFotografiOnizle(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('avatarPreview');
                if (preview.tagName === 'IMG') {
                    preview.src = e.target.result;
                } else {
                    // div'i img'ye çevir
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'profile-avatar';
                    img.id = 'avatarPreview';
                    img.alt = 'Profil fotoğrafı';
                    preview.parentNode.replaceChild(img, preview);
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Başarı mesajı varsa edit formu açık göster
    <?php if ($success || $error): ?>
    document.addEventListener('DOMContentLoaded', () => duzenlemeyiDegistir());
    <?php endif; ?>

    function yorumKutusuDegistir(recipeId) {
        const box = document.getElementById('comment-box-' + recipeId);
        if (box) {
            if (box.style.display === 'none') {
                box.style.display = 'block';
                box.querySelector('textarea').focus();
            } else {
                box.style.display = 'none';
            }
        }
    }

    function begeniGonder(e, recipeId) {
        e.preventDefault();
        e.stopPropagation();
        
        const form = document.getElementById('like-form-' + recipeId);
        const formData = new FormData(form);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const icon = document.getElementById('like-icon-' + recipeId);
                const countSpan = document.getElementById('like-count-' + recipeId);
                
                if (data.liked) {
                    icon.innerHTML = '➤︞';
                    icon.style.color = 'var(--primary-dark)';
                } else {
                    icon.innerHTML = '🤞';
                    icon.style.color = 'inherit';
                }
                countSpan.innerText = data.count;
            }
        });
    }

    function yorumGonder(e, recipeId) {
        e.preventDefault();
        e.stopPropagation();
        
        const form = document.getElementById('comment-form-' + recipeId);
        const formData = new FormData(form);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch(e) {
                console.error("JSON olmayan yanıt:", text);
                throw e;
            }
        })
        .then(data => {
            if (data.success) {
                // Yorum sayısını güncelle
                document.getElementById('comment-metrics-' + recipeId).innerText = data.count;
                
                // Son yorum alanını güncelle
                const commentBind = document.getElementById('latest-comment-bind-' + recipeId);
                document.getElementById('latest-comment-author-' + recipeId).innerText = '@' + data.author + ':';
                document.getElementById('latest-comment-content-' + recipeId).innerText = data.comment;
                commentBind.style.display = 'block';

                // Formu temizle ve gizle
                form.reset();
                document.getElementById('comment-box-' + recipeId).style.display = 'none';
            } else {
                alert('Yorum kaydedilemedi: ' + (data.error || 'Bilinmeyen hata'));
            }
        })
        .catch(err => {
            console.error("Yorum gönderme hatası:", err);
            alert('Bağlantı hatası: Yorum gönderilemedi. Lütfen sayfayı yenileyip tekrar deneyin.');
        });
    }
    </script>
</body>
</html>
