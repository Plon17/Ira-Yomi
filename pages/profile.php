<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user']['user_id'];

// Load user info
$stmt = $db->prepare('SELECT username, email, created_at FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Load titles submitted by this user
$stmt = $db->prepare('
    SELECT title_id, title, type, cover_image, is_approved, created_at 
    FROM titles 
    WHERE added_by = ? 
    ORDER BY created_at DESC
');
$stmt->execute([$user_id]);
$submitted_titles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load what this user is reading
$stmt = $db->prepare('
    SELECT t.title_id, t.title, t.type, t.cover_image, ur.status, ur.added_at
    FROM user_reading ur
    JOIN titles t ON ur.title_id = t.title_id
    WHERE ur.user_id = ? AND t.is_approved = 1
    ORDER BY ur.added_at DESC
');
$stmt->execute([$user_id]);
$reading_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Ira-Yomi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; }
        .profile-header { background:linear-gradient(135deg, #1da1f2, #1a91da); color:white; padding:40px 0; border-radius:15px; }
        .card-img-top { height:200px; object-fit:cover; }
        .btn-primary { background:#1da1f2; border:none; }
        .btn-primary:hover { background:#1a91da; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container my-5">
        <div class="profile-header text-center mb-5">
            <h1>Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h1>
            <p class="lead">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <h3>Your Submitted Titles (<?php echo count($submitted_titles); ?>)</h3>
                <?php if (empty($submitted_titles)): ?>
                    <p>You haven't submitted any titles yet. <a href="addTitle.php">Add one!</a></p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($submitted_titles as $t): ?>
                            <div class="col-12">
                                <div class="card">
                                    <div class="row g-0">
                                        <div class="col-4">
                                            <?php if ($t['cover_image'] && file_exists("../" . $t['cover_image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($t['cover_image']); ?>" class="img-fluid rounded-start" style="height:100%; object-fit:cover;">
                                            <?php else: ?>
                                                <img src="../images/default-cover.jpg" class="img-fluid rounded-start">
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-8">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($t['title']); ?></h6>
                                                <small class="text-muted"><?php echo $t['type']; ?> • 
                                                    <?php echo $t['is_approved'] ? 'Approved' : 'Pending Review'; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-6">
                <h3>Your Reading List (<?php echo count($reading_list); ?>)</h3>
                <?php if (empty($reading_list)): ?>
                    <p>Nothing here yet. Start reading something!</p>
                <?php else: ?>
                    <div class="row row-cols-2 row-cols-md-3 g-3">
                        <?php foreach ($reading_list as $item): ?>
                            <div class="col">
                                <a href="title.php?id=<?php echo $item['title_id']; ?>" class="text-decoration-none">
                                    <div class="card h-100 text-center">
                                        <?php if ($item['cover_image'] && file_exists("../" . $item['cover_image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($item['cover_image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                        <?php else: ?>
                                            <img src="../images/default-cover.jpg" class="card-img-top">
                                        <?php endif; ?>
                                        <div class="card-body p-2">
                                            <small class="text-muted"><?php echo $item['status']; ?></small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>