<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user']['user_id'];
$active_tab = $_GET['tab'] ?? 'favorites';

// Handle profile update
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $display_name = trim($_POST['display_name']);
    $profile_pic = $_POST['current_pic'] ?? 'images/default-avatar.png';

    if (!empty($_FILES['profile_pic']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['profile_pic']['type'], $allowed) && $_FILES['profile_pic']['size'] < 2*1024*1024) {
            $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '.' . $ext;
            $path = '../images/avatars/' . $filename;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $path)) {
                $profile_pic = 'images/avatars/' . $filename;
            }
        }
    }

    $stmt = $db->prepare('UPDATE users SET display_name = ?, profile_pic = ? WHERE user_id = ?');
    $stmt->execute([$display_name ?: null, $profile_pic, $user_id]);
    
    $_SESSION['user']['display_name'] = $display_name;
    $_SESSION['user']['profile_pic'] = $profile_pic;
    $success = "Profile updated!";
}

// Load user info
$stmt = $db->prepare('SELECT username, email, display_name, profile_pic, created_at FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

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

// Submitted titles
$submitted = $db->prepare('SELECT title_id, title, type, cover_image, is_approved FROM titles WHERE added_by = ? ORDER BY created_at DESC');
$submitted->execute([$user_id]);
$submitted_titles = $submitted->fetchAll();
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
        .profile-img-big { width:120px; height:120px; border-radius:50%; object-fit:cover; border:5px solid white; box-shadow:0 8px 20px rgba(0,0,0,0.3); }
        .stat-card { background:white; border-radius:15px; box-shadow:0 6px 20px rgba(0,0,0,0.1); padding:25px; text-align:center; }
        .nav-tabs .nav-link.active { background:#1da1f2; color:white; border:none; }
        .nav-tabs .nav-link { color:#1da1f2; border-radius:10px; }
        .card-img-top { height:200px; object-fit:cover; border-radius:10px; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container my-5">
        <div class="profile-header text-center mb-5">
            <img src="../<?php echo $user['profile_pic'] ?? 'images/default-avatar.png'; ?>" class="profile-img-big mb-3" alt="Profile">
            <h1 class="display-4"><?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?></h1>
            <p class="lead">@<?php echo htmlspecialchars($user['username']); ?> • Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
            <button class="btn btn-light mt-2" id="editProfileBtn">Edit Profile</button>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Profile Edit -->
        <div class="card mb-5 shadow" id="profileEditForm" style="display:none;">
            <div class="card-body">
                <h4>Edit Profile</h4>
                <form method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Display Name</label>
                            <input type="text" name="display_name" class="form-control" value="<?php echo htmlspecialchars($user['display_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" name="profile_pic" class="form-control" accept="image/*">
                            <input type="hidden" name="current_pic" value="<?php echo $user['profile_pic'] ?? ''; ?>">
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary mt-3">Save Changes</button>
                </form>
            </div>
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
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab==='favorites'?'active':''; ?>" href="?tab=favorites" data-tab="favorites">Favorites</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab==='reading'?'active':''; ?>" href="?tab=reading" data-tab="reading">Reading</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab==='plantoread'?'active':''; ?>" href="?tab=plantoread" data-tab="plantoread">Plan to Read</a>
            </li>
        </ul>

        <!-- Dynamic List -->
        <div id="listContainer" class="row g-4">
            <!-- Filled by JavaScript -->
        </div>

        <!-- Submitted Titles -->
        <div class="mt-5">
            <h3>Your Submitted Titles</h3>
            <?php if (empty($submitted_titles)): ?>
                <p>You haven't submitted any titles yet.</p>
            <?php else: ?>
                <?php foreach ($submitted_titles as $t): ?>
                    <div class="card mb-3">
                        <div class="row g-0">
                            <div class="col-3">
                                <img src="../<?php echo $t['cover_image'] ?? 'images/default-cover.jpg'; ?>" class="img-fluid rounded-start" style="height:100%;object-fit:cover;">
                            </div>
                            <div class="col-9">
                                <div class="card-body">
                                    <h6><?php echo htmlspecialchars($t['title']); ?></h6>
                                    <small class="text-muted">Status: <?php echo $t['is_approved'] ? 'Approved' : 'Pending'; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

    document.querySelectorAll(".nav-tabs .nav-link").forEach(link => {
        link.addEventListener("click", function(e) {
            e.preventDefault(); // prevent full page reload
            document.querySelector(".nav-tabs .nav-link.active").classList.remove("active");
            this.classList.add("active");
            loadList(this.dataset.tab);
        });
    });

    // Load the active tab on page load
    loadList('<?php echo $active_tab; ?>');
    </script>

    <?php if (!empty($genre_data)): ?>
    <script>
    new Chart(document.getElementById('genreChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($genre_data, 'genre_name')); ?>,
            datasets: [{ data: <?php echo json_encode(array_column($genre_data, 'cnt')); ?>,
                backgroundColor: ['#1da1f2','#ff6384','#36a2eb','#ffce56','#4bc0c0','#9966ff','#c9cbcf','#ff9f40']
            }]
        },
        options: { responsive:true, plugins:{legend:{position:'right'}} }
    });
    </script>
    <?php endif; ?>

    <?php if (!empty($type_data)): ?>
    <script>
    new Chart(document.getElementById('typeChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($type_data, 'type')); ?>,
            datasets: [{ data: <?php echo json_encode(array_column($type_data, 'cnt')); ?>, backgroundColor:'#74b9ff' }]
        },
        options: { plugins:{legend:{display:false}}, responsive:true }
    });
    </script>
    <?php endif; ?>

    <script>
    document.getElementById('editProfileBtn').addEventListener('click', function() {
        const form = document.getElementById('profileEditForm');
        if (form.style.display === 'none') {
            form.style.display = 'block';
            this.textContent = 'Cancel';
        } else {
            form.style.display = 'none';
            this.textContent = 'Edit Profile';
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>