<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $cover_image = null;
        if (!empty($_FILES['cover_image']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $file_type = $_FILES['cover_image']['type'];
            $file_size = $_FILES['cover_image']['size'];
            $file_tmp = $_FILES['cover_image']['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            $file_name = uniqid('img_') . '.' . $file_ext;
            $upload_path = '../images/' . $file_name;

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
            try {
                $stmt = $db->prepare('
                    INSERT INTO titles (title, type, synopsis, author, genre, tags, cover_image, external_link, added_by, is_approved, volumes, chapters, release_date)
                    VALUES (:title, :type, :synopsis, :author, :genre, :tags, :cover_image, :external_link, :added_by, 0, :volumes, :chapters, :release_date)
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
                    'added_by' => $_SESSION['user']['user_id'],
                    'volumes' => $volumes,
                    'chapters' => $chapters,
                    'release_date' => $release_date
                ]);
                $success = 'Title submitted successfully! It will appear after admin approval.';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ira-Yomi - Add Title</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        h1 { font-size: 1.6rem; font-weight: bold; }
        .form-label strong { color: #333; }
        .btn-primary { background-color: #1da1f2; border-color: #1da1f2; }
        .btn-primary:hover { background-color: #1a91da; border-color: #1a91da; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-4">
        <h1 class="mb-3">Add New Title</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add Title</li>
            </ol>
        </nav>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <form method="POST" action="addTitle.php" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="title" class="form-label"><strong>Title</strong></label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label"><strong>Type</strong></label>
                        <select class="form-control" id="type" name="type" required>
                            <option value="">Select Type</option>
                            <option value="LN">Light Novel (LN)</option>
                            <option value="WN">Web Novel (WN)</option>
                            <option value="VN">Visual Novel (VN)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="synopsis" class="form-label"><strong>Synopsis</strong></label>
                        <textarea class="form-control" id="synopsis" name="synopsis" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="author" class="form-label"><strong>Author</strong></label>
                        <input type="text" class="form-control" id="author" name="author" required>
                    </div>
                    <div class="mb-3">
                        <label for="genre" class="form-label"><strong>Genre</strong></label>
                        <input type="text" class="form-control" id="genre" name="genre" required>
                    </div>
                    <div class="mb-3">
                        <label for="tags" class="form-label"><strong>Tags (comma-separated)</strong></label>
                        <input type="text" class="form-control" id="tags" name="tags">
                    </div>
                    <div class="mb-3">
                        <label for="cover_image" class="form-label"><strong>Cover Image (JPG/PNG, max 2MB)</strong></label>
                        <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/jpeg,image/png">
                    </div>
                    <div class="mb-3">
                        <label for="external_link" class="form-label"><strong>External Link</strong></label>
                        <input type="url" class="form-control" id="external_link" name="external_link">
                    </div>
                    <div class="mb-3">
                        <label for="volumes" class="form-label"><strong>Volumes (for LN)</strong></label>
                        <input type="number" class="form-control" id="volumes" name="volumes" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="chapters" class="form-label"><strong>Chapters (for WN)</strong></label>
                        <input type="number" class="form-control" id="chapters" name="chapters" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="release_date" class="form-label"><strong>Release Date</strong></label>
                        <input type="date" class="form-control" id="release_date" name="release_date">
                    </div>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary w-100">Submit Title</button>
                </form>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>