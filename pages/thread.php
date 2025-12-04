<?php
session_start();
require '../includes/config.php';

$thread_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user']['user_id'] ?? 0;
$is_admin = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';

if ($thread_id <= 0) {
    header('Location: forum.php');
    exit;
}

// Load thread
$stmt = $db->prepare('
    SELECT ft.*, u.username, u.profile_pic 
    FROM forum_threads ft 
    JOIN users u ON ft.user_id = u.user_id 
    WHERE ft.thread_id = ?
');
$stmt->execute([$thread_id]);
$thread = $stmt->fetch();

if (!$thread) {
    header('Location: forum.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_id) {
        // New comment
        if (isset($_POST['comment'])) {
            $comment = trim($_POST['comment']);
            if ($comment) {
                $stmt = $db->prepare('INSERT INTO forum_comments (thread_id, user_id, comment) VALUES (?, ?, ?)');
                $stmt->execute([$thread_id, $user_id, $comment]);
            }
        }

        // Like / Dislike
        if (isset($_POST['comment_id']) && isset($_POST['vote'])) {
            $cid = (int)$_POST['comment_id'];
            $vote = $_POST['vote'];
            $field = $vote === 'like' ? 'likes' : 'dislikes';
            $db->prepare("UPDATE forum_comments SET $field = $field + 1 WHERE comment_id = ?")->execute([$cid]);
        }

        // REPORT — NOW WORKS
        if (isset($_POST['report_comment'])) {
            $cid = (int)$_POST['report_comment'];
            $check = $db->prepare('SELECT 1 FROM comment_reports WHERE comment_id = ? AND user_id = ?');
            $check->execute([$cid, $user_id]);
            if ($check->rowCount() == 0) {
                $db->prepare('INSERT INTO comment_reports (comment_id, user_id, source_type) VALUES (?, ?, "forum")')
                   ->execute([$cid, $user_id]);
            }
        }
    }

    // Admin delete
    if ($is_admin) {
        if (isset($_POST['delete_comment'])) {
            $cid = (int)$_POST['delete_comment'];
            $db->prepare('DELETE FROM forum_comments WHERE comment_id = ?')->execute([$cid]);
            $db->prepare('DELETE FROM comment_reports WHERE comment_id = ?')->execute([$cid]);
        }
        if (isset($_POST['delete_thread'])) {
            $db->prepare('DELETE FROM forum_comments WHERE thread_id = ?')->execute([$thread_id]);
            $db->prepare('DELETE FROM forum_threads WHERE thread_id = ?')->execute([$thread_id]);
            header('Location: forum.php');
            exit;
        }
    }
    header("Location: thread.php?id=$thread_id");
    exit;
}

// Load comments with report count
$comments = $db->prepare('
    SELECT fc.*, u.username, u.profile_pic,
           (SELECT COUNT(*) FROM comment_reports WHERE comment_id = fc.comment_id) as report_count
    FROM forum_comments fc 
    JOIN users u ON fc.user_id = u.user_id 
    WHERE fc.thread_id = ? 
    ORDER BY fc.likes DESC, fc.created_at ASC
');
$comments->execute([$thread_id]);
$comments_list = $comments->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($thread['title']); ?> - Forum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; }
        .thread-header { background:white; border-radius:15px; padding:30px; box-shadow:0 6px 20px rgba(0,0,0,0.1); }
        .comment { background:white; border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .profile-img { width:50px; height:50px; border-radius:50%; object-fit:cover; }
        .report-btn { color:#dc3545; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container my-5">
        <a href="forum.php" class="btn btn-outline-secondary mb-3">Back to Forum</a>

        <?php if ($is_admin): ?>
            <form method="POST" class="d-inline float-end">
                <input type="hidden" name="delete_thread" value="<?php echo $thread['thread_id']; ?>">
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete entire thread?')">Delete Thread</button>
            </form>
        <?php endif; ?>

        <div class="thread-header mb-5">
            <div class="d-flex align-items-center mb-4">
                <img src="../<?php echo $thread['profile_pic'] ?? 'images/default-avatar.png'; ?>" class="profile-img me-3">
                <div>
                    <h2><?php echo htmlspecialchars($thread['title']); ?></h2>
                    <small class="text-muted">by <?php echo htmlspecialchars($thread['username']); ?> • <?php echo date('M j, Y g:i A', strtotime($thread['created_at'])); ?></small>
                </div>
            </div>
            <p class="lead"><?php echo nl2br(htmlspecialchars($thread['content'])); ?></p>
        </div>

        <h4>Replies (<?php echo count($comments_list); ?>)</h4>

        <?php if ($user_id): ?>
        <form method="POST" class="mb-4 ajax-form" id="new-comment-form">
            <textarea name="comment" class="form-control" rows="3" placeholder="Write a reply..." required></textarea>
            <button type="submit" class="btn btn-primary mt-2">Post Reply</button>
        </form>
        <?php else: ?>
        <p><a href="login.php">Log in</a> to reply.</p>
        <?php endif; ?>

        <div id="comments-container">
            <?php foreach ($comments_list as $c): ?>
                <div class="comment d-flex" id="comment-<?php echo $c['comment_id']; ?>">
                    <img src="../<?php echo $c['profile_pic'] ?? 'images/default-avatar.png'; ?>" class="profile-img me-3">
                    <div class="flex-grow-1">
                        <strong><?php echo htmlspecialchars($c['username']); ?></strong>
                        <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($c['created_at'])); ?></small>
                        <!-- REPORT BADGE FOR EVERYONE -->
                        <?php if ($c['report_count'] > 0): ?>
                            <span class="badge bg-danger ms-2"><?php echo $c['report_count']; ?> report<?php echo $c['report_count'] > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($c['comment'])); ?></p>
                        <div class="comment-actions mt-2">
                            <!-- Like -->
                            <form method="POST" class="d-inline ajax-form">
                                <input type="hidden" name="comment_id" value="<?php echo $c['comment_id']; ?>">
                                <input type="hidden" name="vote" value="like">
                                <button type="submit" class="btn btn-link p-0 text-success">Like <?php echo $c['likes']; ?></button>
                            </form>

                            <!-- Dislike -->
                            <form method="POST" class="d-inline ms-3 ajax-form">
                                <input type="hidden" name="comment_id" value="<?php echo $c['comment_id']; ?>">
                                <input type="hidden" name="vote" value="dislike">
                                <button type="submit" class="btn btn-link p-0 text-danger">Dislike <?php echo $c['dislikes']; ?></button>
                            </form>

                            <!-- Report -->
                            <form method="POST" class="d-inline ms-3 ajax-form">
                                <input type="hidden" name="report_comment" value="<?php echo $c['comment_id']; ?>">
                                <button type="submit" class="btn btn-link p-0 report-btn">Report</button>
                            </form>

                            <!-- Admin Delete -->
                            <?php if ($is_admin): ?>
                            <form method="POST" class="d-inline ms-3 ajax-form">
                                <input type="hidden" name="delete_comment" value="<?php echo $c['comment_id']; ?>">
                                <button type="submit" class="btn btn-link p-0 text-danger">Delete</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        $(document).on('submit', '.ajax-form', function(e) {
            e.preventDefault();
            let form = this;
            let formData = new FormData(form);

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function() {
                    $('#comments-container').load(window.location.href + ' #comments-container > *');
                }
            });
        });
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>