<?php
/*
Adı Soyadı: Levent KUBAŞIK
Öğrenci Numarası: 262484025
*/
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
require_once 'includes/db.php';
require_once 'includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

const REPLIES_PREVIEW_LIMIT = 2;

$recipeIdColumn = tarifIdKolonunuGetir($pdo);
$recipeIdExpr = 'r.' . $recipeIdColumn;

$stmt = $pdo->prepare("
    SELECT r.*, $recipeIdExpr as id, u.kullanici_adi as author_name,
    (SELECT COUNT(*) FROM begeniler WHERE tarif_id = $recipeIdExpr) as like_count,
    (SELECT AVG(puan) FROM tarif_puanlari WHERE tarif_id = $recipeIdExpr) as average_rating,
    (SELECT COUNT(*) FROM tarif_puanlari WHERE tarif_id = $recipeIdExpr) as rating_count,
    (SELECT puan FROM tarif_puanlari WHERE kullanici_id = ? AND tarif_id = $recipeIdExpr) as user_rating
    FROM tarifler r
    JOIN kullanicilar u ON r.kullanici_id = u.id
    WHERE $recipeIdExpr = ?
");
$stmt->execute([girisYapildiMi() ? $_SESSION['kullanici_id'] : -1, $id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    header('Location: index.php');
    exit;
}

$isLiked = false;
if (girisYapildiMi()) {
    $check = $pdo->prepare("SELECT id FROM begeniler WHERE kullanici_id = ? AND tarif_id = ?");
    $check->execute([$_SESSION['kullanici_id'], $id]);
    $isLiked = (bool) $check->fetch();
}

function yorumAvatariniOlustur(?string $avatar, string $authorName, string $className = 'social-comment-avatar'): string {
    if (!empty($avatar)) {
        return '<img src="' . htmlspecialchars($avatar) . '" class="' . $className . '" alt="Profil fotoğrafı">';
    }

    return '<div class="' . $className . ' social-comment-placeholder">' . strtoupper(mb_substr($authorName, 0, 1)) . '</div>';
}

function cevapAgaciniOlustur(array $replies): array {
    $byParent = [];
    foreach ($replies as $reply) {
        $parentId = (int)($reply['ust_cevap_id'] ?? 0);
        $byParent[$parentId][] = $reply;
    }

    return $byParent;
}

function cevapOgesiniOlustur(array $r, array $repliesByParent, int $currentUserId, int $level = 0, bool $isInitiallyHidden = false): string {
    ob_start();
    $replyId = (int)$r['id'];
    $commentId = (int)$r['yorum_id'];
    $children = $repliesByParent[$replyId] ?? [];
    ?>
    <div class="social-reply reply-item" data-reply-id="<?= $replyId ?>"<?= $isInitiallyHidden ? ' hidden' : '' ?>>
        <?= yorumAvatariniOlustur($r['avatar'] ?? null, $r['author_name'], 'social-reply-avatar') ?>
        <div class="social-comment-main">
            <div class="social-comment-bubble">
                <div class="social-comment-head">
                    <a href="profil.php?id=<?= $r['kullanici_id'] ?>" class="social-comment-author">@<?= htmlspecialchars($r['author_name']) ?></a>
                    <span class="social-comment-date"><?= date('d.m.Y H:i', strtotime($r['olusturulma_tarihi'])) ?></span>
                </div>
                <p class="social-comment-text"><?= nl2br(htmlspecialchars($r['icerik'])) ?></p>
            </div>
            <div class="social-comment-actions">
                <?php if (girisYapildiMi()): ?>
                <button type="button" class="social-action reply-like-btn <?= !empty($r['is_liked']) ? 'liked' : '' ?>" data-reply-id="<?= $replyId ?>">
                    <span class="like-icon"><?= !empty($r['is_liked']) ? '❤️' : '🧡' ?></span>
                    <span class="reply-like-count"><?= (int)$r['like_count'] ?></span>
                </button>
                <button type="button" class="social-action nested-reply-btn" data-reply-id="<?= $replyId ?>">
                    Cevapla
                </button>
                <?php else: ?>
                <a href="login.php" class="social-action">🧡 <?= (int)$r['like_count'] ?></a>
                <?php endif; ?>

                <?php if ($currentUserId && $currentUserId === (int)$r['kullanici_id']): ?>
                <button type="button" onclick="cevapSil(<?= $replyId ?>, event)" class="social-action danger">Sil</button>
                <?php endif; ?>
            </div>

            <?php if (girisYapildiMi()): ?>
            <div class="social-reply-form nested-reply-form-<?= $replyId ?>">
                <form method="POST" class="reply-form-submit" data-comment-id="<?= $commentId ?>" data-parent-reply-id="<?= $replyId ?>">
                    <input type="hidden" name="reply_comment_id" value="<?= $commentId ?>">
                    <input type="hidden" name="reply_parent_id" value="<?= $replyId ?>">
                    <textarea name="reply_text" placeholder="@<?= htmlspecialchars($r['author_name']) ?> kişisine cevap yaz..." required></textarea>
                    <button type="submit" class="btn btn-primary">Gönder</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if (!empty($children)): ?>
            <div class="social-replies nested-replies nested-replies-<?= $replyId ?>">
                <?php foreach ($children as $childIndex => $child): ?>
                    <?= cevapOgesiniOlustur($child, $repliesByParent, $currentUserId, $level + 1, $childIndex >= REPLIES_PREVIEW_LIMIT) ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="social-action show-more-replies"<?= count($children) > REPLIES_PREVIEW_LIMIT ? '' : ' hidden' ?>>
                Devamını görüntüle
            </button>
            <?php else: ?>
            <div class="social-replies nested-replies nested-replies-<?= $replyId ?>"></div>
            <button type="button" class="social-action show-more-replies" hidden>
                Devamını görüntüle
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function yorumOgesiniOlustur(array $c, array $replies, int $currentUserId, bool $isInitiallyHidden = false): string {
    ob_start();
    $repliesByParent = cevapAgaciniOlustur($replies);
    $rootReplies = $repliesByParent[0] ?? [];
    ?>
    <div class="social-comment" id="comment-<?= $c['id'] ?>"<?= $isInitiallyHidden ? ' hidden' : '' ?>>
        <?= yorumAvatariniOlustur($c['avatar'] ?? null, $c['author_name']) ?>
        <div class="social-comment-main">
            <div class="social-comment-bubble">
                <div class="social-comment-head">
                    <a href="profil.php?id=<?= $c['kullanici_id'] ?>" class="social-comment-author">@<?= htmlspecialchars($c['author_name']) ?></a>
                    <span class="social-comment-date"><?= date('d.m.Y H:i', strtotime($c['olusturulma_tarihi'])) ?></span>
                </div>
                <p class="social-comment-text"><?= nl2br(htmlspecialchars($c['icerik'])) ?></p>
            </div>

            <div class="social-comment-actions">
                <?php if (girisYapildiMi()): ?>
                <button type="button" class="social-action comment-like-btn <?= !empty($c['is_liked']) ? 'liked' : '' ?>" data-comment-id="<?= $c['id'] ?>">
                    <span class="like-icon"><?= !empty($c['is_liked']) ? '❤️' : '🧡' ?></span>
                    <span class="like-count"><?= (int)$c['like_count'] ?></span>
                </button>
                <button type="button" class="social-action reply-btn" data-comment-id="<?= $c['id'] ?>">
                    Cevapla <span class="reply-count-<?= $c['id'] ?>"><?= (int)($c['reply_count'] ?? count($replies)) ?></span>
                </button>
                <?php else: ?>
                <a href="login.php" class="social-action">🧡 <?= (int)$c['like_count'] ?> Beğeni</a>
                <?php endif; ?>

                <?php if ($currentUserId && $currentUserId === (int)$c['kullanici_id']): ?>
                <button type="button" onclick="yorumSil(<?= $c['id'] ?>, event)" class="social-action danger">Sil</button>
                <?php endif; ?>
            </div>

            <?php if (girisYapildiMi()): ?>
            <div class="social-reply-form reply-form-<?= $c['id'] ?>">
                <form method="POST" class="reply-form-submit" data-comment-id="<?= $c['id'] ?>">
                    <input type="hidden" name="reply_comment_id" value="<?= $c['id'] ?>">
                    <textarea name="reply_text" placeholder="@<?= htmlspecialchars($c['author_name']) ?> kişisine cevap yaz..." required></textarea>
                    <button type="submit" class="btn btn-primary">Gönder</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="social-replies replies-<?= $c['id'] ?>">
                <?php foreach ($rootReplies as $replyIndex => $r): ?>
                    <?= cevapOgesiniOlustur($r, $repliesByParent, $currentUserId, 0, $replyIndex >= REPLIES_PREVIEW_LIMIT) ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="social-action show-more-replies"<?= count($rootReplies) > REPLIES_PREVIEW_LIMIT ? '' : ' hidden' ?>>
                Devamını görüntüle
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Yorum silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment']) && girisYapildiMi()) {
    $commentId = (int)$_POST['delete_comment'];
    $stmt = $pdo->prepare("DELETE FROM yorumlar WHERE id = ? AND kullanici_id = ? AND tarif_id = ?");
    $stmt->execute([$commentId, $_SESSION['kullanici_id'], $id]);
    
    // AJAX isteği ise JSON döndür
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'comment_id' => $commentId], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    header('Location: tarif-detay.php?id=' . $id);
    exit;
}

// Cevap silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reply']) && girisYapildiMi()) {
    $replyId = (int)$_POST['delete_reply'];
    $ownerCheck = $pdo->prepare("SELECT id FROM yorum_cevaplari WHERE id = ? AND kullanici_id = ?");
    $ownerCheck->execute([$replyId, $_SESSION['kullanici_id']]);
    $deleted = false;

    if ($ownerCheck->fetch()) {
        $pdo->prepare("UPDATE yorum_cevaplari SET ust_cevap_id = NULL WHERE ust_cevap_id = ?")->execute([$replyId]);
        $stmt = $pdo->prepare("DELETE FROM yorum_cevaplari WHERE id = ? AND kullanici_id = ?");
        $stmt->execute([$replyId, $_SESSION['kullanici_id']]);
        $deleted = true;
    }
    
    // AJAX isteği ise JSON döndür
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $deleted, 'reply_id' => $replyId], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    header('Location: tarif-detay.php?id=' . $id);
    exit;
}

// Yorum ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && girisYapildiMi()) {
    $content = trim($_POST['comment']);
    if (!empty($content)) {
        $stmt = $pdo->prepare("INSERT INTO yorumlar (kullanici_id, tarif_id, icerik) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['kullanici_id'], $id, $content]);
        
        // AJAX isteği ise JSON döndür
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            $lastCommentId = $pdo->lastInsertId();
            
            // Yeni eklenen yorumu getir
            $stmt = $pdo->prepare("
                SELECT c.*, u.kullanici_adi as author_name, u.profil_resmi as avatar,
                (SELECT COUNT(*) FROM yorum_begenileri WHERE yorum_id = c.id) as like_count,
                (SELECT COUNT(*) FROM yorum_cevaplari WHERE yorum_id = c.id) as reply_count,
                (SELECT id FROM yorum_begenileri WHERE kullanici_id = ? AND yorum_id = c.id) as is_liked
                FROM yorumlar c
                JOIN kullanicilar u ON c.kullanici_id = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$_SESSION['kullanici_id'], $lastCommentId]);
            $newComment = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'comment' => $newComment,
                'html' => yorumOgesiniOlustur($newComment, [], (int)$_SESSION['kullanici_id']),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        header('Location: tarif-detay.php?id=' . $id);
        exit;
    }
}

// Beğeni toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like']) && girisYapildiMi()) {
    // Output buffering varsa temizle
    if (ob_get_length()) {
        ob_clean();
    }
    
    try {
        if ($isLiked) {
            $pdo->prepare("DELETE FROM begeniler WHERE kullanici_id = ? AND tarif_id = ?")->execute([$_SESSION['kullanici_id'], $id]);
            $newLiked = false;
        } else {
            $pdo->prepare("INSERT INTO begeniler (kullanici_id, tarif_id) VALUES (?, ?)")->execute([$_SESSION['kullanici_id'], $id]);
            $newLiked = true;
        }
        
        // AJAX isteği ise JSON döndür
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM begeniler WHERE tarif_id = ?");
            $countStmt->execute([$id]);
            $icon = $newLiked ? '❤️' : '🧡';
            $response = ['success' => true, 'liked' => $newLiked, 'icon' => $icon, 'count' => (int)$countStmt->fetchColumn()];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: tarif-detay.php?id=' . $id);
        exit;
    } catch (Exception $e) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        die('Beğeni işleminde hata: ' . $e->getMessage());
    }
}

// Tarif yıldız puanı verme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_recipe']) && girisYapildiMi()) {
    $rating = max(1, min(5, (int)$_POST['rate_recipe']));

    $stmt = $pdo->prepare("
        INSERT INTO tarif_puanlari (kullanici_id, tarif_id, puan)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE puan = VALUES(puan), guncellenme_tarihi = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$_SESSION['kullanici_id'], $id, $rating]);

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        $stats = $pdo->prepare("SELECT AVG(puan) as average_rating, COUNT(*) as rating_count FROM tarif_puanlari WHERE tarif_id = ?");
        $stats->execute([$id]);
        $ratingStats = $stats->fetch();

        echo json_encode([
            'success' => true,
            'userRating' => $rating,
            'averageRating' => round((float)($ratingStats['average_rating'] ?? 0), 1),
            'ratingCount' => (int)($ratingStats['rating_count'] ?? 0),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: tarif-detay.php?id=' . $id);
    exit;
}

// Yorum beğeni toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_comment']) && girisYapildiMi()) {
    $commentId = (int)$_POST['like_comment'];
    $check = $pdo->prepare("SELECT id FROM yorum_begenileri WHERE kullanici_id = ? AND yorum_id = ?");
    $check->execute([$_SESSION['kullanici_id'], $commentId]);
    
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM yorum_begenileri WHERE kullanici_id = ? AND yorum_id = ?")->execute([$_SESSION['kullanici_id'], $commentId]);
        $liked = false;
    } else {
        $pdo->prepare("INSERT INTO yorum_begenileri (kullanici_id, yorum_id) VALUES (?, ?)")->execute([$_SESSION['kullanici_id'], $commentId]);
        $liked = true;
    }
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM yorum_begenileri WHERE yorum_id = ?");
        $countStmt->execute([$commentId]);
        $icon = $liked ? '❤️' : '🧡';
        echo json_encode(['success' => true, 'liked' => $liked, 'icon' => $icon, 'count' => $countStmt->fetchColumn()]);
        exit;
    }
    header('Location: tarif-detay.php?id=' . $id . '#comment-' . $commentId);
    exit;
}

// Yorum cevabı ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_text']) && isset($_POST['reply_comment_id']) && girisYapildiMi()) {
    $commentId = (int)$_POST['reply_comment_id'];
    $parentReplyId = !empty($_POST['reply_parent_id']) ? (int)$_POST['reply_parent_id'] : null;
    $content = trim($_POST['reply_text']);
    
    if (!empty($content)) {
        if ($parentReplyId) {
            $parentCheck = $pdo->prepare("SELECT id FROM yorum_cevaplari WHERE id = ? AND yorum_id = ?");
            $parentCheck->execute([$parentReplyId, $commentId]);
            if (!$parentCheck->fetch()) {
                $parentReplyId = null;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO yorum_cevaplari (kullanici_id, yorum_id, ust_cevap_id, icerik) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['kullanici_id'], $commentId, $parentReplyId, $content]);
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            $lastReplyId = $pdo->lastInsertId();
            
            // Yeni eklenen cevabı getir
            $stmt = $pdo->prepare("
                SELECT r.*, u.kullanici_adi as author_name, u.profil_resmi as avatar,
                (SELECT COUNT(*) FROM cevap_begenileri WHERE cevap_id = r.id) as like_count,
                (SELECT id FROM cevap_begenileri WHERE kullanici_id = ? AND cevap_id = r.id) as is_liked
                FROM yorum_cevaplari r
                JOIN kullanicilar u ON r.kullanici_id = u.id
                WHERE r.id = ?
            ");
            $stmt->execute([$_SESSION['kullanici_id'], $lastReplyId]);
            $newReply = $stmt->fetch();
            
            // Reply count'u güncelle
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM yorum_cevaplari WHERE yorum_id = ?");
            $countStmt->execute([$commentId]);
            
            echo json_encode([
                'success' => true,
                'reply' => $newReply,
                'html' => cevapOgesiniOlustur($newReply, [], (int)$_SESSION['kullanici_id']),
                'count' => (int)$countStmt->fetchColumn(),
                'parentReplyId' => $parentReplyId,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    header('Location: tarif-detay.php?id=' . $id . '#comment-' . $commentId);
    exit;
}

// Cevap beğeni toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_reply']) && girisYapildiMi()) {
    $replyId = (int)$_POST['like_reply'];
    $check = $pdo->prepare("SELECT id FROM cevap_begenileri WHERE kullanici_id = ? AND cevap_id = ?");
    $check->execute([$_SESSION['kullanici_id'], $replyId]);
    
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM cevap_begenileri WHERE kullanici_id = ? AND cevap_id = ?")->execute([$_SESSION['kullanici_id'], $replyId]);
        $liked = false;
    } else {
        $pdo->prepare("INSERT INTO cevap_begenileri (kullanici_id, cevap_id) VALUES (?, ?)")->execute([$_SESSION['kullanici_id'], $replyId]);
        $liked = true;
    }
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cevap_begenileri WHERE cevap_id = ?");
        $countStmt->execute([$replyId]);
        $icon = $liked ? '❤️' : '🧡';
        echo json_encode(['success' => true, 'liked' => $liked, 'icon' => $icon, 'count' => $countStmt->fetchColumn()]);
        exit;
    }
    header('Location: tarif-detay.php?id=' . $id);
    exit;
}

$comments = $pdo->prepare("
    SELECT c.*, u.kullanici_adi as author_name, u.id as kullanici_id, u.profil_resmi as avatar,
    (SELECT COUNT(*) FROM yorum_begenileri WHERE yorum_id = c.id) as like_count,
    (SELECT COUNT(*) FROM yorum_cevaplari WHERE yorum_id = c.id) as reply_count,
    (SELECT id FROM yorum_begenileri WHERE kullanici_id = ? AND yorum_id = c.id) as is_liked
    FROM yorumlar c
    JOIN kullanicilar u ON c.kullanici_id = u.id
    WHERE c.tarif_id = ?
    ORDER BY like_count DESC, c.olusturulma_tarihi ASC
");
$comments->execute([girisYapildiMi() ? $_SESSION['kullanici_id'] : -1, $id]);
$comments = $comments->fetchAll();

$ingredients = array_filter(array_map('trim', explode("\n", $recipe['malzemeler'])));
$instructions = array_filter(array_map('trim', explode("\n", $recipe['hazirlanisi'])));
$visibleCommentsLimit = 3;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($recipe['baslik']) ?> - Yemek Paylaşım</title>
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
        <article class="recipe-detail">
            <div class="recipe-detail-header">
                <a href="javascript:geriGit()" class="btn btn-outline detail-back-btn">&#11013;&#65039; Geri Dön</a>
                <h1><?= htmlspecialchars($recipe['baslik']) ?></h1>
                <p class="author"><a href="profil.php?id=<?= $recipe['kullanici_id'] ?>" class="profile-name-link">@<?= htmlspecialchars($recipe['author_name']) ?></a> * <?= date('d.m.Y', strtotime($recipe['olusturulma_tarihi'])) ?></p>
                <?php
                $times = [];
                if ($recipe['hazirlama_suresi']) $times[] = $recipe['hazirlama_suresi'] . ' dk hazırlık';
                if ($recipe['pisirme_suresi']) $times[] = $recipe['pisirme_suresi'] . ' dk pişirme';
                if (!empty($times)): ?>
                    <p class="meta"><?= implode(' ', $times) ?></p>
                <?php endif; ?>
                <?php if ($recipe['kisi_sayisi']): ?>
                    <p class="meta"><?= $recipe['kisi_sayisi'] ?> kişilik</p>
                <?php endif; ?>

                <div class="rating-panel">
                    <div>
                        <strong>Tarif Puanı</strong>
                        <div class="rating-summary">
                            <span id="rating-average"><?= number_format((float)($recipe['average_rating'] ?? 0), 1) ?></span>
                            <span class="stars-display">&#9733;</span>
                            <span id="rating-count"><?= (int)($recipe['rating_count'] ?? 0) ?> oy</span>
                        </div>
                    </div>
                    <?php if (girisYapildiMi()): ?>
                    <form method="POST" id="rating-form" class="rating-stars" aria-label="Tarife yıldız puanı ver">
                        <?php for ($star = 1; $star <= 5; $star++): ?>
                        <button type="submit" name="rate_recipe" value="<?= $star ?>" class="<?= (int)($recipe['user_rating'] ?? 0) >= $star ? 'active' : '' ?>" title="<?= $star ?> yıldız">&#9733;</button>
                        <?php endfor; ?>
                    </form>
                    <?php else: ?>
                    <a href="login.php" class="rating-login">Puan vermek için giriş yap</a>
                    <?php endif; ?>
                </div>

                <div class="recipe-detail-actions">
                    <?php if (girisYapildiMi()): ?>
                    <form id="like-form" method="POST">
                        <button type="submit" name="like" value="1" class="btn <?= $isLiked ? 'btn-primary liked' : 'btn-outline' ?>" id="like-btn">
                            <?= $isLiked ? '❤️' : '🧡' ?> <span id="like-count"><?= $recipe['like_count'] ?></span> Beğeni
                        </button>
                    </form>
                    <?php else: ?>
                    <a href="login.php" class="btn btn-outline">&#129505; Beğen</a>
                    <?php endif; ?>

                    <?php if (girisYapildiMi() && $_SESSION['kullanici_id'] == $recipe['kullanici_id']): ?>
                    <a href="tarif-duzenle.php?id=<?= $recipe['id'] ?>" class="btn btn-outline">&#9999;&#65039; Düzenle</a>
                    <a href="tarif-sil.php?id=<?= $recipe['id'] ?>" class="btn btn-outline btn-danger-outline" onclick="return confirm('Bu tarifi silmek istediğinize emin misiniz?');">&#128465;&#65039; Sil</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="recipe-detail-content">
                <?php if (!empty($recipe['resim_url'])): ?>
                <img src="<?= htmlspecialchars($recipe['resim_url']) ?>" style="width: 100%; max-width: 500px; height: auto; border-radius: 10px; margin-bottom: 1.5rem; display: block;">
                <?php else: ?>
                <div style="width: 100%; max-width: 500px; height: 300px; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 4rem; margin-bottom: 1.5rem;">&#127869;&#65039;</div>
                <?php endif; ?>
                <h3>Malzemeler</h3>
                <ul>
                    <?php foreach ($ingredients as $ing): ?>
                        <li><?= htmlspecialchars($ing) ?></li>
                    <?php endforeach; ?>
                </ul>
                <h3>YAPILIŞ</h3>
                <ol>
                    <?php foreach ($instructions as $step): ?>
                        <li><?= htmlspecialchars($step) ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </article>

        <div class="comments-section social-comments-section">
            <div class="comments-header">
                <h3>Yorumlar <span id="comments-count"><?= count($comments) ?></span></h3>
                <p>Tarif hakkında soru sor, deneyimini paylaş veya yorumlara cevap ver.</p>
            </div>

            <?php if (girisYapildiMi()): ?>
            <form method="POST" id="new-comment-form" class="social-composer">
                <?= yorumAvatariniOlustur($_SESSION['profil_resmi'] ?? null, $_SESSION['kullanici_adi'], 'social-comment-avatar') ?>
                <div class="social-composer-body">
                    <textarea name="comment" placeholder="Bu tarif hakkında yorum yaz..." rows="2" required></textarea>
                    <div class="social-composer-actions">
                        <span>Yorumun herkes tarafından görülebilir.</span>
                        <button type="submit" class="btn btn-primary">Yorum Yap</button>
                    </div>
                </div>
            </form>
            <?php else: ?>
            <div class="social-login-prompt">Yorum yapmak için <a href="login.php">giriş yap</a>.</div>
            <?php endif; ?>

            <div id="comments-list" class="social-comments-list">
                <?php if (empty($comments)): ?>
                    <p class="text-muted empty-comments">Henüz yorum yok. İlk yorumu sen yap!</p>
                <?php else: ?>
                    <?php foreach ($comments as $commentIndex => $c): ?>
                        <?php
                        $repliesStmt = $pdo->prepare("
                            SELECT cr.*, u.kullanici_adi as author_name, u.profil_resmi as avatar,
                            (SELECT COUNT(*) FROM cevap_begenileri WHERE cevap_id = cr.id) as like_count,
                            (SELECT id FROM cevap_begenileri WHERE kullanici_id = ? AND cevap_id = cr.id) as is_liked
                            FROM yorum_cevaplari cr
                            JOIN kullanicilar u ON cr.kullanici_id = u.id
                            WHERE cr.yorum_id = ?
                            ORDER BY cr.olusturulma_tarihi ASC
                        ");
                        $repliesStmt->execute([girisYapildiMi() ? $_SESSION['kullanici_id'] : -1, $c['id']]);
                        $replies = $repliesStmt->fetchAll();
                        ?>
                        <?= yorumOgesiniOlustur($c, $replies, girisYapildiMi() ? (int)$_SESSION['kullanici_id'] : 0, $commentIndex >= $visibleCommentsLimit) ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" id="show-more-comments" class="btn btn-outline" style="margin-top: 1rem; width: 100%;"<?= count($comments) > $visibleCommentsLimit ? '' : ' hidden' ?>>
                Devamını görüntüle
            </button>
        </div>

    <script>
    // Akıllı geri gitme fonksiyonu
    function geriGit() {
        // Eğer tarif-duzenle veya tarif-ekle sayfasından geldiyse index'e git
        if (document.referrer.includes('tarif-duzenle.php') || document.referrer.includes('tarif-ekle.php')) {
            window.location.href = 'index.php';
        } else {
            // Aksi takdirde normal history.back() yap
            history.back();
        }
    }

    function yorumSayisiniGuncelle(delta) {
        const counter = document.getElementById('comments-count');
        if (!counter) return;
        counter.textContent = Math.max(0, parseInt(counter.textContent || '0', 10) + delta);
    }

    const COMMENTS_PREVIEW_LIMIT = <?= $visibleCommentsLimit ?>;
    const REPLIES_PREVIEW_LIMIT = <?= REPLIES_PREVIEW_LIMIT ?>;

    function yorumOnizlemesiniYenile() {
        const list = document.getElementById('comments-list');
        const showMoreBtn = document.getElementById('show-more-comments');
        if (!list || !showMoreBtn || showMoreBtn.dataset.expanded === '1') return;

        const comments = Array.from(list.querySelectorAll(':scope > .social-comment'));
        comments.forEach((comment, index) => {
            comment.hidden = index >= COMMENTS_PREVIEW_LIMIT;
        });
        showMoreBtn.hidden = comments.length <= COMMENTS_PREVIEW_LIMIT;
    }

    function cevapOnizlemesiniYenile(repliesWrap) {
        const showMoreBtn = repliesWrap?.nextElementSibling;
        if (!repliesWrap || !showMoreBtn?.classList.contains('show-more-replies') || showMoreBtn.dataset.expanded === '1') return;

        const replies = Array.from(repliesWrap.querySelectorAll(':scope > .social-reply'));
        replies.forEach((reply, index) => {
            reply.hidden = index >= REPLIES_PREVIEW_LIMIT;
        });
        showMoreBtn.hidden = replies.length <= REPLIES_PREVIEW_LIMIT;
    }

    function tumCevapOnizlemeleriniYenile(root = document) {
        root.querySelectorAll('.social-replies').forEach(cevapOnizlemesiniYenile);
    }

    document.getElementById('show-more-comments')?.addEventListener('click', function() {
        document.querySelectorAll('#comments-list > .social-comment[hidden]').forEach(comment => {
            comment.hidden = false;
        });
        this.dataset.expanded = '1';
        this.hidden = true;
    });

    function sosyalYorumOlaylariniBagla(root = document) {
        root.querySelectorAll('.comment-like-btn:not([data-bound])').forEach(btn => {
            btn.dataset.bound = '1';
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const commentId = this.dataset.commentId;
                const form = new FormData();
                form.append('like_comment', commentId);
                
                fetch('tarif-detay.php?id=<?= $id ?>', {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: form
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        this.classList.toggle('liked', !!d.liked);
                        this.querySelector('.like-icon').textContent = d.liked ? '❤️' : '🧡';
                        this.querySelector('.like-count').textContent = d.count;
                    }
                })
                .catch(err => console.error('Yorum beğeni hatası:', err));
            });
        });

        root.querySelectorAll('.reply-like-btn:not([data-bound])').forEach(btn => {
            btn.dataset.bound = '1';
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const replyId = this.dataset.replyId;
                const form = new FormData();
                form.append('like_reply', replyId);
                
                fetch('tarif-detay.php?id=<?= $id ?>', {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: form
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        this.classList.toggle('liked', !!d.liked);
                        this.querySelector('.like-icon').textContent = d.liked ? '❤️' : '🧡';
                        this.querySelector('.reply-like-count').textContent = d.count;
                    }
                })
                .catch(err => console.error('Cevap beğenisi hatası:', err));
            });
        });

        root.querySelectorAll('.reply-btn:not([data-bound])').forEach(btn => {
            btn.dataset.bound = '1';
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const commentId = this.dataset.commentId;
                const formDiv = document.querySelector('.reply-form-' + commentId);
                if (formDiv) {
                    formDiv.classList.toggle('active');
                    const textarea = formDiv.querySelector('textarea');
                    if (formDiv.classList.contains('active') && textarea) {
                        textarea.focus();
                    }
                }
            });
        });

        root.querySelectorAll('.nested-reply-btn:not([data-bound])').forEach(btn => {
            btn.dataset.bound = '1';
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const replyId = this.dataset.replyId;
                const formDiv = document.querySelector('.nested-reply-form-' + replyId);
                if (formDiv) {
                    formDiv.classList.toggle('active');
                    const textarea = formDiv.querySelector('textarea');
                    if (formDiv.classList.contains('active') && textarea) {
                        textarea.focus();
                    }
                }
            });
        });

        root.querySelectorAll('.show-more-replies:not([data-bound])').forEach(btn => {
            btn.dataset.bound = '1';
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const repliesWrap = this.previousElementSibling;
                if (!repliesWrap) return;

                repliesWrap.querySelectorAll(':scope > .social-reply[hidden]').forEach(reply => {
                    reply.hidden = false;
                });
                this.dataset.expanded = '1';
                this.hidden = true;
            });
        });

        root.querySelectorAll('.reply-form-submit:not([data-bound])').forEach(form => {
            form.dataset.bound = '1';
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const commentId = this.dataset.commentId;
                const parentReplyId = this.dataset.parentReplyId || '';
                const textarea = this.querySelector('textarea[name="reply_text"]');
                const submitBtn = this.querySelector('button[type="submit"]');
                
                if (!textarea.value.trim()) return;
                
                submitBtn.disabled = true;
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Gönderiliyor...';
                
                fetch('tarif-detay.php?id=<?= $id ?>', {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: new FormData(this)
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        this.reset();
                        const activeForm = parentReplyId
                            ? document.querySelector('.nested-reply-form-' + parentReplyId)
                            : document.querySelector('.reply-form-' + commentId);
                        activeForm?.classList.remove('active');
                        document.querySelector('.reply-count-' + commentId).textContent = d.count;
                        
                        const repliesWrap = parentReplyId
                            ? document.querySelector('.nested-replies-' + parentReplyId)
                            : document.querySelector('.replies-' + commentId);
                        const template = document.createElement('template');
                        template.innerHTML = d.html.trim();
                        repliesWrap.append(template.content.firstElementChild);
                        sosyalYorumOlaylariniBagla(repliesWrap);
                        cevapOnizlemesiniYenile(repliesWrap);
                    }
                })
                .catch(err => console.error('Cevap gönderme hatası:', err))
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
            });
        });
    }

    // Yorum silme AJAX
    function yorumSil(commentId, event) {
        event.preventDefault();
        if (!confirm('Yorumu silmek istediğinizden emin misiniz?')) return;
        
        const fd = new FormData();
        fd.append('delete_comment', commentId);
        
        fetch('tarif-detay.php?id=<?= $id ?>', {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: fd
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const commentElement = document.getElementById('comment-' + commentId);
                if (commentElement) {
                    commentElement.style.opacity = '0';
                    commentElement.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        commentElement.remove();
                        yorumOnizlemesiniYenile();
                    }, 300);
                    yorumSayisiniGuncelle(-1);
                }
            }
        })
        .catch(err => console.error('Silme hatası:', err));
    }

    // Cevap silme AJAX
    function cevapSil(replyId, event) {
        event.preventDefault();
        if (!confirm('Bu cevabı silmek istediğinizden emin misiniz?')) return;
        
        const fd = new FormData();
        fd.append('delete_reply', replyId);
        
        fetch('tarif-detay.php?id=<?= $id ?>', {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: fd
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const replyElement = document.querySelector('.reply-item[data-reply-id="' + replyId + '"]');
                if (replyElement) {
                    const comment = replyElement.closest('.social-comment');
                    const counter = comment?.querySelector('.reply-btn span');
                    if (counter) {
                        counter.textContent = Math.max(0, parseInt(counter.textContent || '0', 10) - 1);
                    }
                    replyElement.style.opacity = '0';
                    replyElement.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        const repliesWrap = replyElement.parentElement;
                        replyElement.remove();
                        cevapOnizlemesiniYenile(repliesWrap);
                    }, 300);
                }
            }
        })
        .catch(err => console.error('Cevap silme hatası:', err));
    }

    // Tarif beğeni AJAX
    const likeForm = document.getElementById('like-form');
    
    if (likeForm) {
        likeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            fetch('tarif-detay.php?id=<?= $id ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'like=1'
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const btn = document.getElementById('like-btn');
                    
                    // Button iç HTML'ini güncelle (ikonlu versiyonda)
                    btn.innerHTML = d.icon + ' <span id="like-count">' + d.count + '</span> Beğeni';
                    
                    // CSS classları güncelle
                    if (d.liked) {
                        btn.classList.remove('btn-outline');
                        btn.classList.add('btn-primary', 'liked');
                    } else {
                        btn.classList.remove('btn-primary', 'liked');
                        btn.classList.add('btn-outline');
                    }
                } else {
                    // API hatası
                }
            })
            .catch(err => console.error('Beğeni hatası:', err));
        });
    }

    const ratingForm = document.getElementById('rating-form');
    if (ratingForm) {
        ratingForm.addEventListener('click', function(e) {
            const btn = e.target.closest('button[name="rate_recipe"]');
            if (!btn) return;
            e.preventDefault();

            const rating = parseInt(btn.value, 10);
            const fd = new FormData();
            fd.append('rate_recipe', rating);

            fetch('tarif-detay.php?id=<?= $id ?>', {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: fd
            })
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;

                ratingForm.querySelectorAll('button').forEach(star => {
                    star.classList.toggle('active', parseInt(star.value, 10) <= d.userRating);
                });
                document.getElementById('rating-average').textContent = Number(d.averageRating).toFixed(1);
                document.getElementById('rating-count').textContent = d.ratingCount + ' oy';
            })
            .catch(err => console.error('Puan verme hatası:', err));
        });
    }

    sosyalYorumOlaylariniBagla();
    tumCevapOnizlemeleriniYenile();

    // Ana yorum formu AJAX
    document.getElementById('new-comment-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const commentText = this.querySelector('textarea[name="comment"]').value;
        
        if (!commentText.trim()) return;
        
        // Double-submit koruması
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Gönderiliyor...';
        
        const fd = new FormData(this);
        
        fetch('tarif-detay.php?id=<?= $id ?>', {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: fd
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                this.reset();
                document.querySelector('.empty-comments')?.remove();

                const template = document.createElement('template');
                template.innerHTML = d.html.trim();
                const newComment = template.content.firstElementChild;
                document.getElementById('comments-list').prepend(newComment);
                sosyalYorumOlaylariniBagla(newComment);
                yorumOnizlemesiniYenile();
                yorumSayisiniGuncelle(1);
            } else {
                alert('Yorum eklenirken hata: ' + (d.error || 'Bilinmeyen hata'));
            }
        })
        .catch(err => {
            console.error('Yorum hatası:', err);
            alert('Yorum eklenirken hata oluştu');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
    </script>
    </div>
</body>
</html>
