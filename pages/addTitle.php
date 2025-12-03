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
    $title = trim($_POST['title']);
    $type = $_POST['type'];
    $synopsis = trim($_POST['synopsis']);
    $author = trim($_POST['author']);
    $tags = trim($_POST['tags']);
    $external_link = trim($_POST['external_link']);
    $volumes = !empty($_POST['volumes']) ? (int)$_POST['volumes'] : null;
    $chapters = !empty($_POST['chapters']) ? (int)$_POST['chapters'] : null;
    $release_date = $_POST['release_date'] ?? null;
    $genre_ids = isset($_POST['genres']) ? implode(',', array_map('intval', $_POST['genres'])) : '';

    if (empty($title) || empty($type) || empty($synopsis) || empty($author)) {
        $error = 'Please fill all required fields.';
    } else {
        $cover_image = null;
        if (!empty($_FILES['cover_image']['name'])) {
            $allowed = ['image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024;
            if (in_array($_FILES['cover_image']['type'], $allowed) && $_FILES['cover_image']['size'] <= $max_size) {
                $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('img_') . '.' . $ext;
                $path = '../images/' . $filename;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $path)) {
                    $cover_image = 'images/' . $filename;
                }
            } else {
                $error = 'Invalid image (JPG/PNG only, max 2MB).';
            }
        }

        if (!$error) {
            try {
                $stmt = $db->prepare('
                    INSERT INTO titles 
                    (title, type, synopsis, author, tags, cover_image, external_link, 
                     volumes, chapters, release_date, genre_ids, added_by, is_approved)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                ');
                $stmt->execute([
                    $title, $type, $synopsis, $author, $tags, $cover_image,
                    $external_link, $volumes, $chapters, $release_date, $genre_ids,
                    $_SESSION['user']['user_id']
                ]);
                $success = 'Title submitted for approval!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Load genres
$genres = [];
try {
    $genres = $db->query('SELECT genre_id, genre_name FROM genres ORDER BY genre_name')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // No genres table yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Title - Ira-Yomi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; }
        .btn-primary { background:#1da1f2; border:none; }
        .btn-primary:hover { background:#1a91da; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2 class="mb-4">Submit New Title</h2>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3"><label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" required></div>

            <div class="mb-3"><label class="form-label">Type *</label>
                <select name="type" class="form-control" required>
                    <option value="LN">Light Novel (LN)</option>
                    <option value="WN">Web Novel (WN)</option>
                    <option value="VN">Visual Novel (VN)</option>
                </select></div>

            <div class="mb-3"><label class="form-label">Synopsis *</label>
                <textarea name="synopsis" class="form-control" rows="6" required></textarea></div>

            <div class="mb-3"><label class="form-label">Author *</label>
                <input type="text" name="author" class="form-control" required></div>

            <?php if (!empty($genres)): ?>
            <div class="mb-3">
                <label class="form-label">Genres (select all that apply)</label>
                <div class="row">
                    <?php foreach ($genres as $g): ?>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="genres[]" value="<?php echo $g['genre_id']; ?>" id="g<?php echo $g['genre_id']; ?>">
                                <label class="form-check-label" for="g<?php echo $g['genre_id']; ?>">
                                    <?php echo htmlspecialchars($g['genre_name']); ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-3"><label class="form-label">Tags (comma-separated, optional)</label>
                <input type="text" name="tags" class="form-control" placeholder="e.g. overpowered MC, reincarnation"></div>

            <div class="mb-3"><label class="form-label">Cover Image (JPG/PNG, max 2MB)</label>
                <input type="file" name="cover_image" class="form-control" accept="image/jpeg,image/png"></div>

            <div class="mb-3"><label class="form-label">External Link</label>
                <input type="url" name="external_link" class="form-control"></div>

            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Volumes (LN)</label>
                    <input type="number" name="volumes" class="form-control" min="0"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Chapters (WN)</label>
                    <input type="number" name="chapters" class="form-control" min="0"></div>
            </div>

            <div class="mb-3"><label class="form-label">Release Date</label>
                <input type="date" name="release_date" class="form-control"></div>

            <button type="submit" class="btn btn-primary btn-lg w-100">Submit for Approval</button>
        </form>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>