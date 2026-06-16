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

// Beğeni işlemi (feed üzerinden)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_recipe_id'])) {
    $tarif_id = (int)$_POST['like_recipe_id'];
    $check = $pdo->prepare("SELECT id FROM begeniler WHERE kullanici_id = ? AND tarif_id = ?");
    $check->execute([$_SESSION['kullanici_id'], $tarif_id]);
    $liked = false;
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM begeniler WHERE kullanici_id = ? AND tarif_id = ?")->execute([$_SESSION['kullanici_id'], $tarif_id]);
    } else {
        $pdo->prepare("INSERT INTO begeniler (kullanici_id, tarif_id) VALUES (?, ?)")->execute([$_SESSION['kullanici_id'], $tarif_id]);
        $liked = true;
    }
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM begeniler WHERE tarif_id = ?");
        $countStmt->execute([$tarif_id]);
        echo json_encode(['success' => true, 'liked' => $liked, 'count' => $countStmt->fetchColumn()]);
        exit;
    }
    
    header('Location: index.php#' . $tarif_id);
    exit;
}

// Yorum ekleme (feed üzerinden)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_recipe_id']) && isset($_POST['comment_text'])) {
    $tarif_id = (int)$_POST['comment_recipe_id'];
    $content = trim($_POST['comment_text']);
    if (!empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO yorumlar (kullanici_id, tarif_id, icerik) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['kullanici_id'], $tarif_id, $content]);
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM yorumlar WHERE tarif_id = ?");
                $countStmt->execute([$tarif_id]);
                echo json_encode(['success' => true, 'count' => $countStmt->fetchColumn(), 'comment' => $content, 'author' => $_SESSION['kullanici_adi']]);
                exit;
            }
            header('Location: index.php#' . $tarif_id);
            exit;
        } catch(Exception $e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }
    }
}

// Oturuma profil fotoğrafı bilgisini yükle
if (girisYapildiMi() && empty($_SESSION['profil_resmi_yuklendi'])) {
    $s = $pdo->prepare("SELECT profil_resmi FROM kullanicilar WHERE id = ?");
    $s->execute([$_SESSION['kullanici_id']]);
    $row = $s->fetch();
    $_SESSION['profil_resmi'] = $row['profil_resmi'] ?? null;
    $_SESSION['profil_resmi_yuklendi'] = true;
}

function anaSayfaTarifleriniGetir(PDO $pdo, string $search, int $userId, int $limit, int $offset): array {
    $limit = max(1, min(20, $limit));
    $offset = max(0, $offset);
    $recipeIdExpr = 'r.' . tarifIdKolonunuGetir($pdo);

    $query = "
        SELECT $recipeIdExpr as id, r.kullanici_id, r.baslik as title, r.aciklama as description, r.malzemeler as ingredients, r.hazirlanisi as instructions, r.resim_url as image_url, r.olusturulma_tarihi as created_at, u.kullanici_adi as author_name, u.profil_resmi as author_avatar,
        (SELECT COUNT(*) FROM begeniler WHERE tarif_id = $recipeIdExpr) as like_count,
        (SELECT COUNT(*) FROM yorumlar WHERE tarif_id = $recipeIdExpr) as comment_count,
        (SELECT c.icerik FROM yorumlar c WHERE c.tarif_id = $recipeIdExpr ORDER BY (SELECT COUNT(*) FROM yorum_begenileri WHERE yorum_id = c.id) DESC, c.olusturulma_tarihi DESC LIMIT 1) as latest_comment,
        (SELECT c.kullanici_id FROM yorumlar c WHERE c.tarif_id = $recipeIdExpr ORDER BY (SELECT COUNT(*) FROM yorum_begenileri WHERE yorum_id = c.id) DESC, c.olusturulma_tarihi DESC LIMIT 1) as latest_comment_user_id,
        (SELECT u2.kullanici_adi FROM yorumlar c JOIN kullanicilar u2 ON c.kullanici_id = u2.id WHERE c.tarif_id = $recipeIdExpr ORDER BY (SELECT COUNT(*) FROM yorum_begenileri WHERE yorum_id = c.id) DESC, c.olusturulma_tarihi DESC LIMIT 1) as latest_comment_author,
        (SELECT COUNT(*) FROM yorum_begenileri WHERE yorum_id = (SELECT c.id FROM yorumlar c WHERE c.tarif_id = $recipeIdExpr ORDER BY (SELECT COUNT(*) FROM yorum_begenileri WHERE yorum_id = c.id) DESC, c.olusturulma_tarihi DESC LIMIT 1)) as top_comment_like_count,
        (SELECT AVG(puan) FROM tarif_puanlari WHERE tarif_id = $recipeIdExpr) as average_rating,
        (SELECT COUNT(*) FROM tarif_puanlari WHERE tarif_id = $recipeIdExpr) as rating_count,
        (SELECT id FROM begeniler WHERE kullanici_id = ? AND tarif_id = $recipeIdExpr) as is_liked
        FROM tarifler r
        JOIN kullanicilar u ON r.kullanici_id = u.id
    ";
    $params = [$userId];

    if ($search !== '') {
        $query .= " WHERE r.baslik LIKE ? OR r.aciklama LIKE ? OR r.malzemeler LIKE ?";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY r.olusturulma_tarihi DESC, $recipeIdExpr DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll();

    foreach ($recipes as &$r) {
        $r['is_liked'] = (bool) $r['is_liked'];
    }
    unset($r);

    return $recipes;
}

function enYuksekPuanliTarifleriGetir(PDO $pdo, int $limit = 5): array {
    $limit = max(1, min(10, $limit));
    $recipeIdExpr = 'r.' . tarifIdKolonunuGetir($pdo);
    $stmt = $pdo->query("
        SELECT $recipeIdExpr as id, r.baslik as title, r.kullanici_id, r.olusturulma_tarihi as created_at, u.kullanici_adi as author_name,
        AVG(rr.puan) as average_rating,
        COUNT(rr.id) as rating_count
        FROM tarif_puanlari rr
        JOIN tarifler r ON rr.tarif_id = $recipeIdExpr
        JOIN kullanicilar u ON r.kullanici_id = u.id
        GROUP BY $recipeIdExpr, r.baslik, r.kullanici_id, r.olusturulma_tarihi, u.kullanici_adi
        ORDER BY average_rating DESC, rating_count DESC, r.olusturulma_tarihi DESC
        LIMIT $limit
    ");

    return $stmt->fetchAll();
}

function tarifKartiniOlustur(array $r): string {
    ob_start();
    ?>
    <div class="recipe-card-wrapper" style="position: relative;" id="post-<?= $r['id'] ?>">
        <a href="tarif-detay.php?id=<?= $r['id'] ?>" class="recipe-card-link" style="display: block; position: relative; z-index: 1;">
            <article class="recipe-card">
                <div class="recipe-card-image">
                    <?php if (!empty($r['image_url'])): ?>
                    <img src="<?= htmlspecialchars($r['image_url']) ?>" alt="<?= htmlspecialchars($r['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                    🞽︞
                    <?php endif; ?>
                </div>
                <div class="recipe-card-body">
                    <div class="recipe-card-top">
                        <div class="recipe-author">
                            <?php if (!empty($r['author_avatar'])): ?>
                                <img src="<?= htmlspecialchars($r['author_avatar']) ?>" alt="Profil fotoğrafı" class="recipe-author-avatar">
                            <?php else: ?>
                                <div class="recipe-author-avatar recipe-author-placeholder">
                                    <?= strtoupper(mb_substr($r['author_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div class="recipe-author-info">
                                <span class="author-link recipe-author-name" onclick="event.preventDefault(); location.href='profil.php?id=<?= $r['kullanici_id'] ?>';">
                                    @<?= htmlspecialchars($r['author_name']) ?>
                                </span>
                                <span class="recipe-date">
                                    <?= date('d.m.Y H:i', strtotime($r['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <span class="recipe-chip">Tarif</span>
                    </div>
                    <h3><?= htmlspecialchars($r['title']) ?></h3>
                    <div class="recipe-rating-line">
                        <span class="rating-stars-mini">&#9733;</span>
                        <strong><?= number_format((float)($r['average_rating'] ?? 0), 1) ?></strong>
                        <span><?= (int)($r['rating_count'] ?? 0) ?> oy</span>
                    </div>
                    <p class="description"><?= htmlspecialchars(mb_substr($r['description'] ?? '', 0, 100)) ?></p>
                    
                    <div class="recipe-actions-bar" onclick="event.stopPropagation();" style="display: flex; gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); position: relative; z-index: 10;">
                        <?php if (girisYapildiMi()): ?>
                        <form method="POST" style="margin: 0;" id="like-form-<?= $r['id'] ?>">
                            <input type="hidden" name="like_recipe_id" value="<?= $r['id'] ?>">
                            <button type="button" onclick="begeniGonder(event, <?= $r['id'] ?>)" class="action-btn <?= $r['is_liked'] ? 'liked' : '' ?>" id="like-btn-<?= $r['id'] ?>" style="background: none; border: none; cursor: pointer; color: var(--text-muted); font-weight: 500; font-family: inherit; font-size: 0.95rem;">
                                <span style="color: <?= $r['is_liked'] ? 'var(--primary-dark)' : 'inherit' ?>; font-size: 1.1rem; vertical-align: middle;" id="like-icon-<?= $r['id'] ?>"><?= $r['is_liked'] ? '&#10084;&#65039;' : '&#129505;' ?></span> <span id="like-count-<?= $r['id'] ?>"><?= $r['like_count'] ?></span>
                            </button>
                        </form>
                        <button type="button" class="action-btn" onclick="event.preventDefault(); event.stopPropagation(); yorumKutusuDegistir(<?= $r['id'] ?>)" style="background: none; border: none; cursor: pointer; color: var(--text-muted); font-weight: 500; font-family: inherit; font-size: 0.95rem;">
                            <span style="font-size: 1.1rem; vertical-align: middle;">💬</span> Yorum Yap (<span id="comment-metrics-<?= $r['id'] ?>"><?= $r['comment_count'] ?></span>)
                        </button>
                        <?php else: ?>
                        <span style="color: var(--text-muted); font-size: 0.9rem;">&#10084;&#65039; <?= $r['like_count'] ?></span>
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
                    <div class="latest-comment" id="latest-comment-bind-<?= $r['id'] ?>" onclick="event.preventDefault(); event.stopPropagation();" style="margin-top: 1rem; padding: 0.8rem; background: var(--bg); border-radius: 8px; font-size: 0.85rem; position: relative; z-index: 10; border-left: 3px solid var(--primary);">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.3rem;">
                            <span style="background: var(--primary); color: white; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">&#11088; En Beğenilen</span>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">&#10084;&#65039; <?= $r['top_comment_like_count'] ?? 0 ?> beğeni</span>
                        </div>
                        <strong style="color: var(--primary);" id="latest-comment-author-<?= $r['id'] ?>">@<?= htmlspecialchars($r['latest_comment_author']) ?>:</strong> <span style="color: var(--text);" id="latest-comment-content-<?= $r['id'] ?>"><?= htmlspecialchars(mb_substr($r['latest_comment'], 0, 120)) ?><?= strlen($r['latest_comment']) > 120 ? '...' : '' ?></span>
                    </div>
                    <?php else: ?>
                    <div class="latest-comment" id="latest-comment-bind-<?= $r['id'] ?>" onclick="event.preventDefault(); event.stopPropagation();" style="display:none; margin-top: 1rem; padding: 0.8rem; background: var(--bg); border-radius: 8px; font-size: 0.85rem; position: relative; z-index: 10;">
                        <strong style="color: var(--primary);" id="latest-comment-author-<?= $r['id'] ?>"></strong> <span style="color: var(--text);" id="latest-comment-content-<?= $r['id'] ?>"></span>
                    </div>
                    <?php endif; ?>
                </div>
            </article>
        </a>
    </div>
    <?php
    return ob_get_clean();
}

$recipesPerPage = 3;
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$recipes = anaSayfaTarifleriniGetir($pdo, $search, (int)($_SESSION['kullanici_id'] ?? 0), $recipesPerPage, ($page - 1) * $recipesPerPage);
$topRatedRecipes = enYuksekPuanliTarifleriGetir($pdo);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'recipes') {
    header('Content-Type: application/json; charset=utf-8');
    $html = '';
    foreach ($recipes as $r) {
        $html .= tarifKartiniOlustur($r);
    }
    echo json_encode([
        'success' => true,
        'html' => $html,
        'hasMore' => count($recipes) === $recipesPerPage,
        'nextPage' => $page + 1,
    ]);
    exit;
}


?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yemek Paylaşım - Ana Sayfa</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="home-page">
        <div class="home-layout">
            <aside class="home-left-sidebar">
                <div class="home-menu-card">
                    <a href="index.php" class="home-sidebar-brand" onclick="localStorage.removeItem('scrollpos');">Yemek<span>Paylaşım</span></a>

                    <form method="GET" class="home-sidebar-search">
                        <input type="text" name="search" placeholder="Tarif ara..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button type="submit">Ara</button>
                    </form>

                    <a href="index.php" class="home-menu-item active">
                        <span>⌂</span>
                        Ana Sayfa
                    </a>
                    <a href="profil.php" class="home-menu-item">
                        <span>◞</span>
                        Profil
                    </a>
                    <a href="tarif-ekle.php" class="home-menu-item">
                        <span>＋</span>
                        Tarif Ekle
                    </a>
                    <a href="logout.php" class="home-menu-item">
                        <span>↪</span>
                        Çıkış
                    </a>
                </div>
            </aside>

            <main class="home-feed">
                <section class="feed-section-header">
                    <div>
                        <div class="section-kicker">Ana Sayfa</div>
                        <h2>Tarif Akışı</h2>
                    </div>
                    <span>Aşağı indikçe yeni tarifler yüklenir</span>
                </section>

                <div class="feed-composer-card">
                    <div class="feed-composer-avatar">
                        <?php if (!empty($_SESSION['profil_resmi'])): ?>
                            <img src="<?= htmlspecialchars($_SESSION['profil_resmi']) ?>" alt="Profil">
                        <?php else: ?>
                            <?= strtoupper(mb_substr($_SESSION['kullanici_adi'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <a href="tarif-ekle.php" class="feed-composer-link">Bugün hangi tarifi paylaşacaksın?</a>
                </div>

                <div class="recipes-grid" id="recipes-grid">
                    <?php if (empty($recipes)): ?>
                        <div class="empty-state">
                            <h3>Henüz tarif yok</h3>
                            <p>İlk tarifi sen paylaş!</p>
                            <a href="tarif-ekle.php" class="btn btn-primary">Tarif Ekle</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recipes as $r): ?>
                        <?= tarifKartiniOlustur($r) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="recipes-loader" style="display: none; text-align: center; color: var(--text-muted); padding: 1.5rem;">Tarifler yükleniyor...</div>
                <div id="recipes-end" style="display: <?= count($recipes) < $recipesPerPage && !empty($recipes) ? 'block' : 'none' ?>; text-align: center; color: var(--text-muted); padding: 1.5rem;">Tüm tarifler yüklendi.</div>
                <div id="recipes-sentinel" style="height: 1px;"></div>
            </main>

            <aside class="top-rated-sidebar">
                <section class="top-rated-card">
                    <div class="section-kicker">Sıralama</div>
                    <div class="top-rated-header">
                        <div>
                            <h3>En İyi Tarifler</h3>
                            <p>En yüksek yıldız puanına göre lider tarifler</p>
                        </div>
                    </div>

                    <?php if (empty($topRatedRecipes)): ?>
                        <p class="top-rated-empty">Henüz puan verilmiş tarif yok.</p>
                    <?php else: ?>
                        <ol class="top-rated-list">
                            <?php foreach ($topRatedRecipes as $index => $topRecipe): ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <div class="top-rated-link">
                                    <a href="tarif-detay.php?id=<?= $topRecipe['id'] ?>" class="top-rated-title">
                                    <strong><?= htmlspecialchars($topRecipe['title']) ?></strong>
                                    </a>
                                    <a href="profil.php?id=<?= $topRecipe['kullanici_id'] ?>" class="top-rated-author">@<?= htmlspecialchars($topRecipe['author_name']) ?></a>
                                </div>
                                <span class="top-rated-score">
                                    &#9733; <?= number_format((float)$topRecipe['average_rating'], 1) ?>
                                    <small><?= (int)$topRecipe['rating_count'] ?> oy</small>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
    <script>
        const recipesGrid = document.getElementById('recipes-grid');
        const recipesLoader = document.getElementById('recipes-loader');
        const recipesEnd = document.getElementById('recipes-end');
        const recipesSentinel = document.getElementById('recipes-sentinel');
        const recipesSearch = <?= json_encode($search, JSON_UNESCAPED_UNICODE) ?>;
        let nextRecipesPage = 2;
        let hasMoreRecipes = <?= count($recipes) === $recipesPerPage ? 'true' : 'false' ?>;
        let recipesLoading = false;

        function tarifHtmlEkle(html) {
            const template = document.createElement('template');
            template.innerHTML = html.trim();
            recipesGrid.append(...template.content.childNodes);
        }

        function dahaFazlaTarifYukle() {
            if (recipesLoading || !hasMoreRecipes || !recipesGrid) {
                return;
            }

            recipesLoading = true;
            recipesLoader.style.display = 'block';

            const params = new URLSearchParams({
                ajax: 'recipes',
                page: String(nextRecipesPage)
            });

            if (recipesSearch) {
                params.set('search', recipesSearch);
            }

            fetch('index.php?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.html) {
                    tarifHtmlEkle(data.html);
                }

                hasMoreRecipes = Boolean(data.hasMore);
                nextRecipesPage = data.nextPage || nextRecipesPage + 1;

                if (!hasMoreRecipes) {
                    recipesEnd.style.display = recipesGrid.children.length ? 'block' : 'none';
                }
            })
            .catch(err => {
                console.error('Tarifler yüklenemedi:', err);
            })
            .finally(() => {
                recipesLoading = false;
                recipesLoader.style.display = 'none';
            });
        }

        function gerekirseTarifYukle() {
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 300) {
                dahaFazlaTarifYukle();
            }
        }

        if (hasMoreRecipes && recipesSentinel) {
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    if (entries.some(entry => entry.isIntersecting)) {
                        dahaFazlaTarifYukle();
                    }
                }, { rootMargin: '300px' });
                observer.observe(recipesSentinel);
            } else {
                window.addEventListener('scroll', gerekirseTarifYukle);
                gerekirseTarifYukle();
            }
        }

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
                        icon.innerHTML = '&#10084;&#65039;';
                        icon.style.color = 'var(--primary-dark)';
                    } else {
                        icon.innerHTML = '&#129505;';
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
