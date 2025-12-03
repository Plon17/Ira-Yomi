<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user']['user_id'];

/* ---------------------------------------
   USER INFO
---------------------------------------- */
$stmt = $db->prepare('SELECT username, email, created_at FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* ---------------------------------------
   STATS
---------------------------------------- */
$stats = ['reading'=>0, 'plantoread'=>0, 'completed'=>0, 'favorites'=>0];

$reading_stmt = $db->prepare('
    SELECT ur.status, COUNT(*) as cnt 
    FROM user_reading ur 
    JOIN titles t ON ur.title_id = t.title_id 
    WHERE ur.user_id = ? AND t.is_approved = 1 
    GROUP BY ur.status
');
$reading_stmt->execute([$user_id]);
foreach ($reading_stmt->fetchAll() as $row) {
    $key = strtolower(str_replace(' ', '', $row['status']));
    if (isset($stats[$key])) $stats[$key] = (int)$row['cnt'];
}

$fav_count = $db->prepare('
    SELECT COUNT(*) 
    FROM user_favorites uf 
    JOIN titles t ON uf.title_id = t.title_id 
    WHERE uf.user_id = ? AND t.is_approved = 1
');
$fav_count->execute([$user_id]);
$stats['favorites'] = (int)$fav_count->fetchColumn();

/* ---------------------------------------
   GENRE PIE CHART DATA
---------------------------------------- */
$genre_stmt = $db->prepare('
    SELECT g.genre_name, COUNT(*) as cnt 
    FROM user_reading ur 
    JOIN titles t ON ur.title_id = t.title_id 
    JOIN genres g ON FIND_IN_SET(g.genre_id, t.genre_ids)
    WHERE ur.user_id = ? AND t.is_approved = 1
    GROUP BY g.genre_id 
    ORDER BY cnt DESC 
    LIMIT 8
');
$genre_stmt->execute([$user_id]);
$genre_data = $genre_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------------------
   TYPE BAR CHART DATA
---------------------------------------- */
$type_stmt = $db->prepare('
    SELECT t.type, COUNT(*) as cnt
    FROM user_reading ur
    JOIN titles t ON ur.title_id = t.title_id
    WHERE ur.user_id = ? AND t.is_approved = 1
    GROUP BY t.type
    ORDER BY cnt DESC
');
$type_stmt->execute([$user_id]);
$type_data = $type_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------------------
   SUBMITTED TITLES
---------------------------------------- */
$submitted = $db->prepare('
    SELECT title_id, title, type, cover_image, is_approved 
    FROM titles 
    WHERE added_by = ? 
    ORDER BY created_at DESC
');
$submitted->execute([$user_id]);
$submitted_titles = $submitted->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile - Ira-Yomi</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    body { background:#f8f9fa; }
    .profile-header { background:linear-gradient(135deg, #1da1f2, #1a91da); color:white; padding:60px 0; border-radius:20px; }
    .stat-card { background:white; border-radius:15px; box-shadow:0 6px 20px rgba(0,0,0,0.1); padding:25px; text-align:center; }
    .nav-tabs .nav-link.active { background:#1da1f2; color:white; border:none; }
    .nav-tabs .nav-link { color:#1da1f2; border-radius:10px; cursor:pointer; }
    .card-img-top { height:200px; object-fit:cover; border-radius:10px; }
</style>

</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container my-5">

    <div class="profile-header text-center mb-5">
        <h1 class="display-4"><?= htmlspecialchars($user['username']); ?></h1>
        <p class="lead">Member since <?= date('F Y', strtotime($user['created_at'])); ?></p>
    </div>

    <!-- Stats -->
    <div class="row mb-5 g-4">
        <div class="col-md-3"><div class="stat-card"><h3><?= $stats['reading']; ?></h3><small>Reading</small></div></div>
        <div class="col-md-3"><div class="stat-card"><h3><?= $stats['plantoread']; ?></h3><small>Plan to Read</small></div></div>
        <div class="col-md-3"><div class="stat-card"><h3><?= $stats['completed']; ?></h3><small>Completed</small></div></div>
        <div class="col-md-3"><div class="stat-card"><h3><?= $stats['favorites']; ?></h3><small>Favorites</small></div></div>
    </div>

    <!-- Charts -->
    <div class="row mb-5 align-items-center">
        <!-- Genre Chart -->
        <?php if (!empty($genre_data)): ?>
        <div class="col-md-6 mx-auto">
            <div class="card shadow">
                <div class="card-body">
                    <h5>Your Top Genres</h5>
                    <div style="height:250px;">
                        <canvas id="genreChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Type Chart -->
        <?php if (!empty($type_data)): ?>
        <div class="col-md-6 mx-auto">
            <div class="card shadow">
                <div class="card-body">
                    <h5>Reading by Type</h5>
                    <div style="height:250px;">
                        <canvas id="typeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs justify-content-center mb-4">
        <li class="nav-item"><a class="nav-link active" data-tab="favorites">Favorites</a></li>
        <li class="nav-item"><a class="nav-link" data-tab="reading">Reading</a></li>
        <li class="nav-item"><a class="nav-link" data-tab="plantoread">Plan to Read</a></li>
    </ul>

    <!-- List Container (AJAX-loaded) -->
    <div id="listContainer" class="row g-4"></div>

    <!-- Submitted Titles -->
    <div class="mt-5">
        <h3>Your Submitted Titles</h3>
        <?php foreach ($submitted_titles as $t): ?>
            <div class="card mb-3">
                <div class="row g-0">
                    <div class="col-3">
                        <img src="../<?= $t['cover_image'] ?? 'images/default-cover.jpg'; ?>" class="img-fluid rounded-start" style="height:100%;object-fit:cover;">
                    </div>
                    <div class="col-9">
                        <div class="card-body">
                            <h6><?= htmlspecialchars($t['title']); ?></h6>
                            <small class="text-muted">Status: <?= $t['is_approved'] ? 'Approved' : 'Pending'; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php include '../includes/footer.php'; ?>

<!-- AJAX Loader -->
<script>
function loadList(tab) {
    fetch("profile_list.php?tab=" + tab)
        .then(r => r.text())
        .then(html => {
            document.getElementById("listContainer").innerHTML = html;
        });
}

document.querySelectorAll(".nav-link").forEach(link => {
    link.addEventListener("click", function() {
        document.querySelector(".nav-link.active").classList.remove("active");
        this.classList.add("active");
        loadList(this.dataset.tab);
    });
});

loadList("favorites");
</script>

<!-- Charts JS -->
<script>
<?php if (!empty($genre_data)): ?>
new Chart(document.getElementById('genreChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($genre_data, 'genre_name')); ?>,
        datasets: [{
            data: <?= json_encode(array_column($genre_data, 'cnt')); ?>,
            backgroundColor: ['#1da1f2','#ff6384','#36a2eb','#ffce56','#4bc0c0','#9966ff','#c9cbcf','#ff9f40']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right', // MOVED HERE
                labels: { boxWidth: 20 }
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($type_data)): ?>
new Chart(document.getElementById('typeChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($type_data, 'type')); ?>,
        datasets: [{
            data: <?= json_encode(array_column($type_data, 'cnt')); ?>,
            backgroundColor: '#74b9ff'
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        responsive: true
    }
});
<?php endif; ?>
</script>

</body>
</html>
