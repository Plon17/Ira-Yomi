<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Stats
$total_users = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$total_titles = $db->query('SELECT COUNT(*) FROM titles')->fetchColumn();
$pending_titles = $db->query('SELECT COUNT(*) FROM titles WHERE is_approved = 0')->fetchColumn();
$total_comments = $db->query('SELECT COUNT(*) FROM comments')->fetchColumn();
$active_today = $db->query('SELECT COUNT(DISTINCT user_id) FROM user_reading WHERE DATE(added_at) = CURDATE()')->fetchColumn();

// Recent activity
$recent_titles = $db->query('SELECT t.title, u.username, t.created_at FROM titles t JOIN users u ON t.added_by = u.user_id ORDER BY t.created_at DESC LIMIT 5')->fetchAll();
$recent_comments = $db->query('SELECT c.comment, u.username, t.title, c.created_at FROM comments c JOIN users u ON c.user_id = u.user_id JOIN titles t ON c.title_id = t.title_id ORDER BY c.created_at DESC LIMIT 5')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ira-Yomi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height:100vh; }
        .dashboard-card { background:white; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.2); padding:30px; transition:0.3s; }
        .dashboard-card:hover { transform:translateY(-10px); }
        .stat-number { font-size:3rem; font-weight:bold; color:#1da1f2; }
        h1 { color:white; text-shadow:0 4px 10px rgba(0,0,0,0.3); }
        .activity-item { background:#f8f9fa; padding:15px; border-radius:12px; margin-bottom:10px; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container my-5">
        <h1 class="text-center mb-5">Admin Dashboard</h1>

        <!-- Stats Grid -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <p class="text-muted mb-0">Total Users</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="stat-number"><?php echo $total_titles; ?></div>
                    <p class="text-muted mb-0">Total Titles</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="stat-number text-warning"><?php echo $pending_titles; ?></div>
                    <p class="text-muted mb-0">Pending Approval</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="stat-number text-success"><?php echo $active_today; ?></div>
                    <p class="text-muted mb-0">Active Today</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card">
                    <h3>Recent Titles</h3>
                    <?php foreach ($recent_titles as $t): ?>
                        <div class="activity-item">
                            <strong><?php echo htmlspecialchars($t['title']); ?></strong><br>
                            <small class="text-muted">by <?php echo htmlspecialchars($t['username']); ?> • <?php echo date('M j, Y g:i A', strtotime($t['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="dashboard-card">
                    <h3>Recent Comments</h3>
                    <?php foreach ($recent_comments as $c): ?>
                        <div class="activity-item">
                            <strong><?php echo htmlspecialchars($c['username']); ?></strong> on <em><?php echo htmlspecialchars($c['title']); ?></em><br>
                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($c['created_at'])); ?></small>
                            <p class="mb-0 mt-2"><?php echo htmlspecialchars(substr($c['comment'], 0, 100)) . (strlen($c['comment']) > 100 ? '...' : ''); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="text-center">
            <a href="manage-titles.php" class="btn btn-primary btn-lg">Manage Titles</a>
            <a href="manage-users.php" class="btn btn-warning btn-lg ms-3">Manage Users</a>
        </div>
    </div>
</body>
</html>