<?php
session_start();
require '../includes/config.php';

$user_id = $_SESSION['user']['user_id'] ?? 0;

// Handle new thread
if ($user_id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_thread'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    if ($title && $content) {
        $stmt = $db->prepare('INSERT INTO forum_threads (user_id, title, content, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$user_id, $title, $content]);
    }
    header('Location: forum.php');
    exit;
}

// Search
$search = trim($_GET['s'] ?? '');
$sql = 'SELECT ft.thread_id, ft.title, ft.created_at, u.username,
               (SELECT COUNT(*) FROM forum_comments WHERE thread_id = ft.thread_id) as reply_count
        FROM forum_threads ft
        JOIN users u ON ft.user_id = u.user_id';
$params = [];

if ($search) {
    $sql .= ' WHERE ft.title LIKE ?';
    $params[] = "%$search%";
}

$sql .= ' ORDER BY ft.created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$threads = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum - Ira-Yomi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; }
        .thread-card { background:white; border-radius:15px; box-shadow:0 6px 20px rgba(0,0,0,0.1); padding:20px; margin-bottom:20px; }
        .reply-count { background:#1da1f2; color:white; padding:4px 10px; border-radius:20px; font-size:0.9rem; }
        .btn-primary { background:#1da1f2; border:none; }
        .btn-primary:hover { background:#1a91da; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container my-5">
        <h1 class="mb-4 text-center">Community Forum</h1>

        <!-- Search -->
        <form method="GET" class="mb-4">
            <div class="input-group">
                <input type="text" name="s" class="form-control" placeholder="Search threads..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>

        <!-- Create Thread -->
        <?php if ($user_id): ?>
        <div class="card mb-4 shadow">
            <div class="card-body">
                <h4>Start a Discussion</h4>
                <form method="POST">
                    <div class="mb-3">
                        <input type="text" name="title" class="form-control" placeholder="Thread title..." required>
                    </div>
                    <div class="mb-3">
                        <textarea name="content" class="form-control" rows="4" placeholder="What's on your mind?" required></textarea>
                    </div>
                    <button type="submit" name="create_thread" class="btn btn-success">Post Thread</button>
                </form>
            </div>
        </div>
        <?php else: ?>
            <p class="text-center"><a href="login.php">Log in</a> to create threads and comment.</p>
        <?php endif; ?>

        <!-- Thread List -->
        <div>
            <?php foreach ($threads as $t): ?>
                <div class="thread-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5><a href="thread.php?id=<?php echo $t['thread_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($t['title']); ?></a>
                            </h5>
                            <small class="text-muted">by <?php echo htmlspecialchars($t['username']); ?> • <?php echo date('M j, Y g:i A', strtotime($t['created_at'])); ?></small>
                        </div>
                        <span class="reply-count"><?php echo $t['reply_count']; ?> replies</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>