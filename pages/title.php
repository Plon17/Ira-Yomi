<?php
session_start();
require '../includes/config.php';

$title_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user']['user_id'] ?? 0;

if ($title_id <= 0) {
    $error = 'Invalid title ID.';
} else {
    try {
        $is_admin = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
        $sql = 'SELECT t.*, 
                       (SELECT COUNT(*) FROM user_reading WHERE title_id = t.title_id AND status = "Reading") as reading_count
                FROM titles t 
                WHERE t.title_id = :title_id';
        if (!$is_admin) $sql .= ' AND t.is_approved = 1';
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['title_id' => $title_id]);
        $title = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$title) {
            $error = 'Title not found or not approved.';
        }

        // Current reading status
        $current_status = null;
        if ($user_id) {
            $stmt = $db->prepare('SELECT status FROM user_reading WHERE user_id = ? AND title_id = ?');
            $stmt->execute([$user_id, $title_id]);
            $row = $stmt->fetch();
            $current_status = $row ? $row['status'] : null;
        }

        // Handle reading list change
        if ($user_id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reading_status'])) {
            $status = $_POST['reading_status'];
            if ($status === 'remove') {
                $db->prepare('DELETE FROM user_reading WHERE user_id = ? AND title_id = ?')->execute([$user_id, $title_id]);
            } else {
                $db->prepare('INSERT INTO user_reading (user_id, title_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?')
                   ->execute([$user_id, $title_id, $status, $status]);
            }
            header("Location: title.php?id=$title_id");
            exit;
        }

        // Handle comment
        if ($user_id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
            $comment = trim($_POST['comment']);
            if ($comment) {
                $stmt = $db->prepare('INSERT INTO comments (title_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$title_id, $user_id, $comment]);
            }
            header("Location: title.php?id=$title_id");
            exit;
        }

        // Load comments
        $comments = $db->prepare('
            SELECT c.comment, c.created_at, u.username 
            FROM comments c 
            JOIN users u ON c.user_id = u.user_id 
            WHERE c.title_id = ? 
            ORDER BY c.created_at DESC
        ');
        $comments->execute([$title_id]);
        $comments_list = $comments->fetchAll();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ira-Yomi - <?php echo $title ? htmlspecialchars($title['title']) : 'Not Found'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; }
        .cover-image img { max-width:100%; height:auto; border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,0.2); }
        .reading-count { background:#1da1f2; color:white; padding:8px 16px; border-radius:50px; font-weight:bold; }
        .btn-primary, .btn-success, .btn-outline-danger { font-weight:bold; }
        .comment-box { background:white; border-radius:12px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .comment { border-bottom:1px solid #eee; padding:15px 0; }
        .comment:last-child { border-bottom:none; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container my-5">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="row">
                <div class="col-lg-4 text-center">
                    <?php if ($title['cover_image'] && file_exists("../" . $title['cover_image'])): ?>
                        <img src="../<?php echo htmlspecialchars($title['cover_image']); ?>" class="cover-image img-fluid mb-4" alt="<?php echo htmlspecialchars($title['title']); ?>">
                    <?php else: ?>
                        <img src="../images/default-cover.jpg" class="cover-image img-fluid mb-4" alt="No cover">
                    <?php endif; ?>

                    <div class="reading-count d-inline-block mb-3">
                        <?php echo (int)($title['reading_count'] ?? 0); ?> reading
                    </div>

                    <?php if ($user_id): ?>
                        <div class="mb-4">
                            <form method="POST" class="d-inline">
                                <select name="reading_status" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                    <option value="">-- Reading Status --</option>
                                    <option value="Reading" <?php echo $current_status==='Reading'?'selected':''; ?>>Reading</option>
                                    <option value="Plan to Read" <?php echo $current_status==='Plan to Read'?'selected':''; ?>>Plan to Read</option>
                                    <option value="Completed" <?php echo $current_status==='Completed'?'selected':''; ?>>Completed</option>
                                    <option value="Dropped" <?php echo $current_status==='Dropped'?'selected':''; ?>>Dropped</option>
                                    <?php if ($current_status): ?>
                                        <option value="remove">Remove from List</option>
                                    <?php endif; ?>
                                </select>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3"><?php echo htmlspecialchars($title['title']); ?> <small class="text-muted">(<?php echo $title['type']; ?>)</small></h1>
                    
                    <ul class="list-unstyled mb-4">
                        <?php if ($title['type'] === 'LN' && $title['volumes']): ?>
                            <li><strong>Volumes:</strong> <?php echo (int)$title['volumes']; ?></li>
                        <?php endif; ?>
                        <?php if ($title['type'] === 'WN' && $title['chapters']): ?>
                            <li><strong>Chapters:</strong> <?php echo (int)$title['chapters']; ?></li>
                        <?php endif; ?>
                        <li><strong>Author:</strong> <?php echo htmlspecialchars($title['author'] ?? 'Unknown'); ?></li>
                        <?php if ($title['release_date'] && $title['release_date'] !== '0000-00-00'): ?>
                            <li><strong>Release Date:</strong> <?php echo date('F j, Y', strtotime($title['release_date'])); ?></li>
                        <?php endif; ?>

                        <!-- FIXED GENRES SECTION -->
                        <?php if (!empty($title['genre_ids'])): ?>
                            <li><strong>Genres:</strong> 
                                <?php
                                $genre_ids = array_filter(array_map('trim', explode(',', $title['genre_ids'])));
                                if (!empty($genre_ids)) {
                                    $placeholders = str_repeat('?,', count($genre_ids) - 1) . '?';
                                    $stmt = $db->prepare("SELECT genre_name FROM genres WHERE genre_id IN ($placeholders) ORDER BY genre_name");
                                    $stmt->execute($genre_ids);
                                    $genre_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                    echo htmlspecialchars(implode(', ', $genre_names));
                                } else {
                                    echo 'None';
                                }
                                ?>
                            </li>
                        <?php else: ?>
                            <li><strong>Genres:</strong> None</li>
                        <?php endif; ?>

                        <?php if ($title['tags']): ?>
                            <li><strong>Tags:</strong> <?php echo htmlspecialchars($title['tags']); ?></li>
                        <?php endif; ?>
                    </ul>

                    <div class="mt-4">
                        <h3>Synopsis</h3>
                        <p class="lead"><?php echo nl2br(htmlspecialchars($title['synopsis'] ?? 'No synopsis available.')); ?></p>
                    </div>

                    <?php if ($title['external_link']): ?>
                        <a href="<?php echo htmlspecialchars($title['external_link']); ?>" class="btn btn-primary btn-lg mt-3" target="_blank">
                            Read This Title
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="comment-box mt-5">
                <h3>Comments (<?php echo count($comments_list); ?>)</h3>
                <?php if ($user_id): ?>
                    <form method="POST" class="mb-4">
                        <textarea name="comment" class="form-control" rows="3" placeholder="Write a comment..." required></textarea>
                        <button type="submit" class="btn btn-primary mt-2">Post Comment</button>
                    </form>
                <?php else: ?>
                    <p><a href="login.php">Log in</a> to comment.</p>
                <?php endif; ?>

                <?php foreach ($comments_list as $c): ?>
                    <div class="comment">
                        <strong><?php echo htmlspecialchars($c['username']); ?></strong>
                        <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($c['created_at'])); ?></small>
                        <p class="mt-2"><?php echo nl2br(htmlspecialchars($c['comment'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>