<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_id = isset($_POST['title_id']) ? (int)$_POST['title_id'] : 0;
    $action = $_POST['action'] ?? '';

    if ($title_id > 0) {
        try {
            if ($action === 'approve') {
                $stmt = $db->prepare('UPDATE titles SET is_approved = 1 WHERE title_id = :title_id');
                $stmt->execute(['title_id' => $title_id]);
                $success = 'Title approved successfully.';
            } elseif ($action === 'disapprove') {
                $stmt = $db->prepare('UPDATE titles SET is_approved = 0 WHERE title_id = :title_id');
                $stmt->execute(['title_id' => $title_id]);
                $success = 'Title disapproved successfully.';
            } elseif ($action === 'delete') {
                $stmt = $db->prepare('DELETE FROM titles WHERE title_id = :title_id');
                $stmt->execute(['title_id' => $title_id]);
                $success = 'Title deleted successfully.';
            } elseif ($action === 'edit') {
                // Get current title data first (so we can keep old values if not changed)
                $current = $db->prepare('SELECT * FROM titles WHERE title_id = ?');
                $current->execute([$title_id]);
                $old = $current->fetch();

                $title = trim($_POST['title'] ?? $old['title']);
                $type = $_POST['type'] ?? $old['type'];
                $synopsis = trim($_POST['synopsis'] ?? $old['synopsis']);
                $author = trim($_POST['author'] ?? $old['author']);
                $tags = trim($_POST['tags'] ?? $old['tags']);
                $external_link = trim($_POST['external_link'] ?? $old['external_link']);
                $volumes = $_POST['volumes'] !== '' ? (int)$_POST['volumes'] : $old['volumes'];
                $chapters = $_POST['chapters'] !== '' ? (int)$_POST['chapters'] : $old['chapters'];
                $release_date = $_POST['release_date'] ?: $old['release_date'];

                // Handle genres (multi-checkbox)
                $genre_ids = '';
                if (isset($_POST['genres']) && is_array($_POST['genres'])) {
                    $cleaned = array_map('intval', $_POST['genres']);
                    $genre_ids = implode(',', $cleaned);
                } else {
                    $genre_ids = $old['genre_ids'] ?? '';
                }

                // Validation
                if (empty($title) || empty($type) || empty($synopsis) || empty($author)) {
                    $error = 'Title, Type, Synopsis and Author are required.';
                } elseif (!in_array($type, ['LN', 'WN', 'VN'])) {
                    $error = 'Invalid type.';
                } else {
                    // Handle cover image
                    $cover_image = $old['cover_image'];
                    if (!empty($_FILES['cover_image']['name'])) {
                        $allowed = ['image/jpeg', 'image/png'];
                        if (in_array($_FILES['cover_image']['type'], $allowed) && $_FILES['cover_image']['size'] <= 2*1024*1024) {
                            $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
                            $filename = uniqid('img_') . '.' . $ext;
                            $path = '../../images/' . $filename;
                            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $path)) {
                                // Delete old image if exists
                                if ($old['cover_image'] && file_exists('../../' . $old['cover_image'])) {
                                    @unlink('../../' . $old['cover_image']);
                                }
                                $cover_image = 'images/' . $filename;
                            }
                        } else {
                            $error = 'Invalid image (JPG/PNG only, max 2MB).';
                        }
                    }

                    if (!$error) {
                        $stmt = $db->prepare('
                            UPDATE titles SET
                            title = ?, type = ?, synopsis = ?, author = ?, tags = ?,
                            cover_image = ?, external_link = ?, volumes = ?, chapters = ?,
                            release_date = ?, genre_ids = ?
                            WHERE title_id = ?
                        ');
                        $stmt->execute([
                            $title, $type, $synopsis, $author, $tags,
                            $cover_image, $external_link, $volumes, $chapters,
                            $release_date, $genre_ids, $title_id
                        ]);
                        $success = 'Title updated successfully!';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Load all titles + their current genres
$titles = $db->query('
    SELECT title_id, title, type, is_approved, synopsis, author, genre_ids, tags, 
           cover_image, external_link, volumes, chapters, release_date
    FROM titles 
    ORDER BY is_approved ASC, created_at DESC
')->fetchAll();

// Load all available genres
$all_genres = [];
try {
    $all_genres = $db->query('SELECT genre_id, genre_name FROM genres ORDER BY genre_name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ira-Yomi - Manage Titles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        h1 { font-size: 1.6rem; font-weight: bold; }
        .btn-primary { background-color: #1da1f2; border-color: #1da1f2; }
        .btn-primary:hover { background-color: #1a91da; border-color: #1a91da; }
        .btn-danger { background-color: #dc3545; border-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; border-color: #c82333; }
        .btn-warning { background-color: #ffc107; border-color: #ffc107; }
        .btn-warning:hover { background-color: #e0a800; border-color: #e0a800; }
        .edit-form { display: none; }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-4">
        <h1 class="mb-3">Manage Titles</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                <li class="breadcrumb-item active" aria-current="page">Manage Titles</li>
            </ol>
        </nav>
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-12">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($titles)): ?>
                            <tr><td colspan="4">No titles available.</td></tr>
                        <?php else: ?>
                            <?php foreach ($titles as $title): ?>
                                <tr>
                                    <td><a href="title.php?id=<?php echo $title['title_id']; ?>"><?php echo htmlspecialchars($title['title']); ?></a></td>
                                    <td><?php echo htmlspecialchars($title['type']); ?></td>
                                    <td><?php echo $title['is_approved'] ? 'Approved' : 'Pending'; ?></td>
                                    <td>
                                        <form method="POST" action="manage-titles.php" style="display:inline;">
                                            <input type="hidden" name="title_id" value="<?php echo $title['title_id']; ?>">
                                            <?php if (!$title['is_approved']): ?>
                                                <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm">Approve</button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="disapprove" class="btn btn-warning btn-sm">Disapprove</button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this title?');">Delete</button>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('edit-form-<?php echo $title['title_id']; ?>').style.display='block';">Edit</button>
                                        </form>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4">
                                        <div id="edit-form-<?php echo $title['title_id']; ?>" class="edit-form">
                                            <form method="POST" action="manage-titles.php" enctype="multipart/form-data">
                                                <input type="hidden" name="title_id" value="<?php echo $title['title_id']; ?>">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="existing_cover_image" value="<?php echo htmlspecialchars($title['cover_image']); ?>">

                                                <div class="mb-3">
                                                    <label class="form-label"><strong>Title *</strong></label>
                                                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($title['title']); ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label"><strong>Type *</strong></label>
                                                    <select class="form-control" name="type" required>
                                                        <option value="LN" <?php echo $title['type'] === 'LN' ? 'selected' : ''; ?>>Light Novel (LN)</option>
                                                        <option value="WN" <?php echo $title['type'] === 'WN' ? 'selected' : ''; ?>>Web Novel (WN)</option>
                                                        <option value="VN" <?php echo $title['type'] === 'VN' ? 'selected' : ''; ?>>Visual Novel (VN)</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label"><strong>Synopsis *</strong></label>
                                                    <textarea class="form-control" name="synopsis" rows="6" required><?php echo htmlspecialchars($title['synopsis']); ?></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label"><strong>Author *</strong></label>
                                                    <input type="text" class="form-control" name="author" value="<?php echo htmlspecialchars($title['author']); ?>" required>
                                                </div>

                                                <?php
                                                // Load genres for checkboxes
                                                $all_genres = [];
                                                try {
                                                    $all_genres = $db->query('SELECT genre_id, genre_name FROM genres ORDER BY genre_name')->fetchAll(PDO::FETCH_ASSOC);
                                                } catch (PDOException $e) {}
                                                $selected_genres = explode(',', $title['genre_ids'] ?? '');
                                                ?>

                                                <?php if (!empty($all_genres)): ?>
                                                <div class="mb-3">
                                                    <label class="form-label">Genres</label>
                                                    <div class="row">
                                                        <?php foreach ($all_genres as $g): ?>
                                                        <div class="col-md-4">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="genres[]" value="<?php echo $g['genre_id']; ?>" 
                                                                id="g<?php echo $g['genre_id'] . '-' . $title['title_id']; ?>"
                                                                <?php echo in_array($g['genre_id'], $selected_genres) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="g<?php echo $g['genre_id'] . '-' . $title['title_id']; ?>">
                                                                    <?php echo htmlspecialchars($g['genre_name']); ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>

                                                <div class="mb-3">
                                                    <label class="form-label"><strong>Tags (comma-separated)</strong></label>
                                                    <input type="text" class="form-control" name="tags" value="<?php echo htmlspecialchars($title['tags']); ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label"><strong>Cover Image (JPG/PNG, max 2MB)</strong></label>
                                                    <input type="file" class="form-control" name="cover_image" accept="image/jpeg,image/png">
                                                    <?php if ($title['cover_image']): ?>
                                                        <small>Current: <?php echo htmlspecialchars($title['cover_image']); ?></small>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label"><strong>External Link</strong></label>
                                                    <input type="url" class="form-control" name="external_link" value="<?php echo htmlspecialchars($title['external_link']); ?>">
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label"><strong>Volumes (for LN)</strong></label>
                                                        <input type="number" class="form-control" name="volumes" value="<?php echo htmlspecialchars($title['volumes']); ?>" min="0">
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label"><strong>Chapters (for WN)</strong></label>
                                                        <input type="number" class="form-control" name="chapters" value="<?php echo htmlspecialchars($title['chapters']); ?>" min="0">
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label"><strong>Release Date</strong></label>
                                                    <input type="date" class="form-control" name="release_date" value="<?php echo htmlspecialchars($title['release_date']); ?>">
                                                </div>

                                                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('edit-form-<?php echo $title['title_id']; ?>').style.display='none';">Cancel</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>