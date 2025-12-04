<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = (int)$_POST['report_id'];
    $action = $_POST['action'] ?? '';

    if ($report_id > 0) {
        try {
            $report = $db->prepare('SELECT cr.*, c.user_id as comment_user_id FROM comment_reports cr JOIN comments c ON cr.comment_id = c.comment_id WHERE cr.id = ?');
            $report->execute([$report_id]);
            $r = $report->fetch();

            if ($r) {
                if ($action === 'delete_comment') {
                    $db->prepare('DELETE FROM comments WHERE comment_id = ?')->execute([$r['comment_id']]);
                    $db->prepare('DELETE FROM comment_reports WHERE comment_id = ?')->execute([$r['comment_id']]);
                    $success = 'Comment deleted.';
                } elseif ($action === 'ban_user') {
                    $db->prepare('UPDATE users SET is_banned = 1 WHERE user_id = ?')->execute([$r['comment_user_id']]);
                    $db->prepare('DELETE FROM comments WHERE comment_id = ?')->execute([$r['comment_id']]);
                    $db->prepare('DELETE FROM comment_reports WHERE comment_id = ?')->execute([$r['comment_id']]);
                    $success = 'User banned and comment deleted.';
                } elseif ($action === 'ignore') {
                    $db->prepare('DELETE FROM comment_reports WHERE id = ?')->execute([$report_id]);
                    $success = 'Report ignored.';
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Load reports
$reports = $db->query("
    SELECT 
        cr.id,
        cr.comment_id,
        cr.user_id AS reporter_id,

        u1.username AS reporter,
        u2.username AS commenter,

        -- Title data
        t.title_id,
        t.title,

        -- Forum data
        ft.thread_id,
        ft.title AS thread_title,

        CASE cr.source_type
            WHEN 'novel' THEN 'novel'
            WHEN 'forum' THEN 'forum'
        END AS source_type,

        COALESCE(c.comment, fc.comment) AS comment,
        COALESCE(c.created_at, fc.created_at) AS comment_date

    FROM comment_reports cr
    LEFT JOIN comments c ON cr.comment_id = c.comment_id AND cr.source_type='novel'
    LEFT JOIN titles t ON c.title_id = t.title_id
    LEFT JOIN forum_comments fc ON cr.comment_id = fc.comment_id AND cr.source_type='forum'
    LEFT JOIN forum_threads ft ON fc.thread_id = ft.thread_id

    JOIN users u1 ON cr.user_id = u1.user_id
    LEFT JOIN users u2 ON 
        CASE cr.source_type 
            WHEN 'novel' THEN c.user_id
            WHEN 'forum' THEN fc.user_id
        END = u2.user_id

    ORDER BY cr.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reports - Ira-Yomi Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; }
        .card { border-radius:15px; box-shadow:0 6px 20px rgba(0,0,0,0.1); }
        .btn-primary { background:#1da1f2; border:none; }
        .btn-primary:hover { background:#1a91da; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container my-5">
        <h1 class="mb-4">Manage Reports (<?php echo count($reports); ?>)</h1>

        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <?php if (empty($reports)): ?>
            <div class="text-center py-5">
                <h3 class="text-muted">No reports yet. All clean!</h3>
            </div>
        <?php else: ?>
            <?php foreach ($reports as $r): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <p><strong>Reported by:</strong> <?= htmlspecialchars($r['reporter']) ?></p>

                                <p>
                                    <strong>Comment by:</strong> <?= htmlspecialchars($r['commenter']) ?> on 
                                    <?php if ($r['source_type'] === 'novel'): ?>
                                        <a href="title.php?id=<?= $r['title_id'] ?>">
                                            <?= htmlspecialchars($r['title']) ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="thread.php?id=<?= $r['thread_id'] ?>">
                                            <?= htmlspecialchars($r['thread_title']) ?>
                                        </a>
                                    <?php endif; ?>
                                </p>

                                <p class="border p-3 bg-light rounded">
                                    "<?= htmlspecialchars($r['comment']) ?>"
                                </p>

                                <small class="text-muted">
                                    Reported on <?= date('M j, Y g:i A', strtotime($r['comment_date'])) ?>
                                </small>
                            </div>

                            <div class="col-md-4 text-end">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="action" value="delete_comment"
                                            class="btn btn-danger btn-sm">Delete Comment</button>
                                </form>

                                <form method="POST" class="d-inline ms-2">
                                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="action" value="ban_user"
                                            class="btn btn-dark btn-sm"
                                            onclick="return confirm('Ban user and delete comment?')">
                                        Ban User
                                    </button>
                                </form>

                                <form method="POST" class="d-inline ms-2">
                                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="action" value="ignore"
                                            class="btn btn-secondary btn-sm">Ignore</button>
                                </form>

                                <div class="mt-2">
                                    <?php if ($r['source_type'] === 'novel'): ?>
                                        <a href="title.php?id=<?= $r['title_id'] ?>&comment_id=<?= $r['comment_id'] ?>#comment-<?= $r['comment_id'] ?>">
                                            Go to Comment
                                        </a>
                                    <?php else: ?>
                                        <a href="thread.php?id=<?= $r['thread_id'] ?>&comment_id=<?= $r['comment_id'] ?>#comment-<?= $r['comment_id'] ?>">
                                            Go to Comment
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>