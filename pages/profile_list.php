<?php
session_start();
require '../includes/config.php';

$user_id = $_SESSION['user']['user_id'] ?? null;
if (!$user_id) exit;

$tab = $_GET['tab'] ?? 'favorites';

if ($tab === 'favorites') {
    $stmt = $db->prepare('SELECT t.title_id, t.title, t.type, t.cover_image 
                          FROM user_favorites uf 
                          JOIN titles t ON uf.title_id = t.title_id 
                          WHERE uf.user_id = ? AND t.is_approved = 1 
                          ORDER BY uf.added_at DESC');
} else {
    $stmt = $db->prepare('SELECT t.title_id, t.title, t.type, t.cover_image, ur.status 
                          FROM user_reading ur 
                          JOIN titles t ON ur.title_id = t.title_id 
                          WHERE ur.user_id = ? AND t.is_approved = 1 
                          ORDER BY ur.added_at DESC');
}

$stmt->execute([$user_id]);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($tab === 'reading') {
    $list = array_filter($list, fn($i) => $i['status'] === 'Reading');
}
if ($tab === 'plantoread') {
    $list = array_filter($list, fn($i) => $i['status'] === 'Plan to Read');
}

if (empty($list)) {
    echo '<p class="text-center text-muted">Nothing here yet!</p>';
    exit;
}

foreach ($list as $item):
?>
<div class="col-md-3">
    <a href="title.php?id=<?= $item['title_id']; ?>" class="text-decoration-none">
        <div class="card h-100">
            <img src="../<?= $item['cover_image'] ?? 'images/default-cover.jpg'; ?>" class="card-img-top">
            <div class="card-body text-center">
                <small class="text-muted">
                    <?= $tab === 'favorites' ? 'Favorite' : $item['status']; ?>
                </small>
            </div>
        </div>
    </a>
</div>
<?php endforeach; ?>
