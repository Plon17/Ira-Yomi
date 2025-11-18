<?php
session_start();
require '../includes/config.php';

// Get title_id from URL
$title_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($title_id <= 0) {
    $error = 'Invalid title ID.';
} else {
    try {
        // Admins can view unapproved titles, others only approved
        $query = 'SELECT title, type, synopsis, author, genre, tags, cover_image, external_link, volumes, chapters, release_date FROM titles WHERE title_id = :title_id';
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            $query .= ' AND is_approved = 1';
        }
        $stmt = $db->prepare($query);
        $stmt->execute(['title_id' => $title_id]);
        $title = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$title) {
            $error = 'Title not found or not approved.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ira-Yomi - <?php echo $title ? htmlspecialchars($title['title']) : 'Title Not Found'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .cover-image img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .meta-list { list-style: none; padding:0; margin:0; }
        .meta-list li { margin-bottom: .4rem; }
        .meta-list strong { color:#333; }
        .btn-primary {
            background-color:#1da1f2; border-color:#1da1f2;
        }
        .btn-primary:hover {
            background-color:#1a91da; border-color:#1a91da;
        }
        h1 { font-size:1.6rem; font-weight:bold; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="../pages/search.php">Novel</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($title['title']); ?></li>
                </ol>
            </nav>
            <h1 class="mb-3">
                <?php echo htmlspecialchars($title['title']); ?>
                <small class="text-muted">(<?php echo htmlspecialchars($title['type']); ?>)</small>
            </h1>
            <div class="row">
                <div class="col-md-4">
                    <?php if ($title['cover_image'] && file_exists("../" . $title['cover_image'])): ?>
                        <div class="cover-image mb-3">
                            <img src="../<?php echo htmlspecialchars($title['cover_image']); ?>" class="img-fluid" alt="<?php echo htmlspecialchars($title['title']); ?>">
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-3">No cover image available.</p>
                    <?php endif; ?>
                    <ul class="meta-list">
                        <?php if ($title['type'] === 'LN'): ?>
                            <li><strong>Volumes:</strong> <?php echo (int)$title['volumes']; ?></li>
                        <?php elseif ($title['type'] === 'WN'): ?>
                            <li><strong>Chapters:</strong> <?php echo (int)$title['chapters']; ?></li>
                        <?php endif; ?>
                        <li><strong>Author:</strong> <?php echo htmlspecialchars($title['author']); ?></li>
                        <li><strong>Release Date:</strong> <?php echo htmlspecialchars($title['release_date']); ?></li>
                        <li><strong>Genre:</strong> <?php echo htmlspecialchars($title['genre']); ?></li>
                        <li><strong>Tags:</strong> <?php echo htmlspecialchars($title['tags']); ?></li>
                    </ul>
                </div>
                <div class="col-md-8">
                    <div class="synopsis mb-4">
                        <strong>Synopsis:</strong><br>
                        <?php echo nl2br(htmlspecialchars($title['synopsis'])); ?>
                    </div>
                    <?php if ($title['external_link']): ?>
                        <p>
                            <a href="<?php echo htmlspecialchars($title['external_link']); ?>" class="btn btn-primary btn-sm" target="_blank">Read More</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>