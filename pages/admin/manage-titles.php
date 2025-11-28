<?php
session_start();
require '../../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../../index.php');
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
                $title = $_POST['title'] ?? '';
                $type = $_POST['type'] ?? '';
                $synopsis = $_POST['synopsis'] ?? '';
                $author = $_POST['author'] ?? '';
                $genre = $_POST['genre'] ?? '';
                $tags = $_POST['tags'] ?? '';
                $external_link = $_POST['external_link'] ?? '';
                $volumes = !empty($_POST['volumes']) ? (int)$_POST['volumes'] : null;
                $chapters = !empty($_POST['chapters']) ? (int)$_POST['chapters'] : null;
                $release_date = !empty($_POST['release_date']) ? $_POST['release_date'] : null;

                if (empty($title) || empty($type) || empty($synopsis) || empty($author) || empty($genre)) {
                    $error = 'Please fill in all required fields.';
                } elseif (!in_array($type, ['LN', 'WN', 'VN'])) {
                    $error = 'Invalid title type.';
                } elseif (!empty($external_link) && !filter_var($external_link, FILTER_VALIDATE_URL)) {
                    $error = 'Invalid URL format.';
                } else {
                    $cover_image = $_POST['existing_cover_image'] ?? null;
                    if (!empty($_FILES['cover_image']['name'])) {
                        $allowed_types = ['image/jpeg', 'image/png'];
                        $max_size = 2 * 1024 * 1024;
                        $file_type = $_FILES['cover_image']['type'];
                        $file_size = $_FILES['cover_image']['size'];
                        $file_tmp = $_FILES['cover_image']['tmp_name'];
                        $file_ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                        $file_name = uniqid('img_') . '.' . $file_ext;
                        $upload_path = '../../images/' . $file_name;

                        if (!in_array($file_type, $allowed_types)) {
                            $error = 'Only JPG and PNG images are allowed.';
                        } elseif ($file_size > $max_size) {
                            $error = 'Image file size must be less than 2MB.';
                        } elseif (!move_uploaded_file($file_tmp, $upload_path)) {
                            $error = 'Failed to upload image.';
                        } else {
                            $cover_image = 'images/' . $file_name;
                        }
                    }

                    if (!$error) {
                        $stmt = $db->prepare('
                            UPDATE titles
                            SET title = :title, type = :type, synopsis = :synopsis, author = :author,
                                genre = :genre, tags = :tags, cover_image = :cover_image,
                                external_link = :external_link, volumes = :volumes,
                                chapters = :chapters, release_date = :release_date
                            WHERE title_id = :title_id
                        ');
                        $stmt->execute([
                            'title' => $title,
                            'type' => $type,
                            'synopsis' => $synopsis,
                            'author' => $author,
                            'genre' => $genre,
                            'tags' => $tags,
                            'cover_image' => $cover_image,
                            'external_link' => $external_link,
                            'volumes' => $volumes,
                            'chapters' => $chapters,
                            'release_date' => $release_date,
                            'title_id' => $title_id
                        ]);
                        $success = 'Title updated successfully.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

try {
    $stmt = $db->query('SELECT title_id, title, type, is_approved, synopsis, author, genre, tags, cover_image, external_link, volumes, chapters, release_date FROM titles ORDER BY is_approved ASC, created_at DESC');
    $titles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
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
    <?php include '../../includes/header.php'; ?>
    <div class="container mt-4">
        <h1 class="mb-3">Manage Titles</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
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
                                    <td><a href="../title.php?id=<?php echo $title['title_id']; ?>"><?php echo htmlspecialchars($title['title']); ?></a></td>
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
                                                    <label for="title-<?php echo $title['title_id']; ?>" class="form-label"><strong>Title</strong></label>
                                                    <input type="text" class="form-control" id="title-<?php echo $title['title_id']; ?>" name="title" value="<?php echo htmlspecialchars($title['title']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="type-<?php echo $title['title_id']; ?>" class="form-label"><strong>Type</strong></label>
                                                    <select class="form-control" id="type-<?php echo $title['title_id']; ?>" name="type" required>
                                                        <option value="LN" <?php echo $title['type'] === 'LN' ? 'selected' : ''; ?>>Light Novel (LN)</option>
                                                        <option value="WN" <?php echo $title['type'] === 'WN' ? 'selected' : ''; ?>>Web Novel (WN)</option>
                                                        <option value="VN" <?php echo $title['type'] === 'VN' ? 'selected' : ''; ?>>Visual Novel (VN)</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="synopsis-<?php echo $title['title_id']; ?>" class="form-label"><strong>Synopsis</strong></label>
                                                    <textarea class="form-control" id="synopsis-<?php echo $title['title_id']; ?>" name="synopsis" rows="5" required><?php echo htmlspecialchars($title['synopsis']); ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="author-<?php echo $title['title_id']; ?>" class="form-label"><strong>Author</strong></label>
                                                    <input type="text" class="form-control" id="author-<?php echo $title['title_id']; ?>" name="author" value="<?php echo htmlspecialchars($title['author']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="genre-<?php echo $title['title_id']; ?>" class="form-label"><strong>Genre</strong></label>
                                                    <input type="text" class="form-control" id="genre-<?php echo $title['title_id']; ?>" name="genre" value="<?php echo htmlspecialchars($title['genre']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="tags-<?php echo $title['title_id']; ?>" class="form-label"><strong>Tags (comma-separated)</strong></label>
                                                    <input type="text" class="form-control" id="tags-<?php echo $title['title_id']; ?>" name="tags" value="<?php echo htmlspecialchars($title['tags']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="cover_image-<?php echo $title['title_id']; ?>" class="form-label"><strong>Cover Image (JPG/PNG, max 2MB)</strong></label>
                                                    <input type="file" class="form-control" id="cover_image-<?php echo $title['title_id']; ?>" name="cover_image" accept="image/jpeg,image/png">
                                                    <?php if ($title['cover_image']): ?>
                                                        <small>Current: <?php echo htmlspecialchars($title['cover_image']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="external_link-<?php echo $title['title_id']; ?>" class="form-label"><strong>External Link</strong></label>
                                                    <input type="url" class="form-control" id="external_link-<?php echo $title['title_id']; ?>" name="external_link" value="<?php echo htmlspecialchars($title['external_link']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="volumes-<?php echo $title['title_id']; ?>" class="form-label"><strong>Volumes (for LN)</strong></label>
                                                    <input type="number" class="form-control" id="volumes-<?php echo $title['title_id']; ?>" name="volumes" value="<?php echo htmlspecialchars($title['volumes']); ?>" min="0">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="chapters-<?php echo $title['title_id']; ?>" class="form-label"><strong>Chapters (for WN)</strong></label>
                                                    <input type="number" class="form-control" id="chapters-<?php echo $title['title_id']; ?>" name="chapters" value="<?php echo htmlspecialchars($title['chapters']); ?>" min="0">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="release_date-<?php echo $title['title_id']; ?>" class="form-label"><strong>Release Date</strong></label>
                                                    <input type="date" class="form-control" id="release_date-<?php echo $title['title_id']; ?>" name="release_date" value="<?php echo htmlspecialchars($title['release_date']); ?>">
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
    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>