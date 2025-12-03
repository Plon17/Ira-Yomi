<?php
session_start();
require '../includes/config.php';

$title_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

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
    <title>Ira-Yomi - <?php echo $title ? htmlspecialchars($title['title']) : 'Not Found'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; }
        .cover-image img { max-width:100%; height:auto; border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,0.2); }
        .meta-list li { margin-bottom:0.8rem; font-size:1.1rem; }
        .reading-count { background:#1da1f2; color:white; padding:8px 16px; border-radius:50px; font-weight:bold; }
        .btn-primary { background:#1da1f2; border:none; }
        .btn-primary:hover { background:#1a91da; }
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

                    <div class="reading-count d-inline-block">
                        <?php echo (int)($title['reading_count'] ?? 0); ?> user<?php echo $title['reading_count'] == 1 ? '' : 's'; ?> reading
                    </div>
                </div>
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3"><?php echo htmlspecialchars($title['title']); ?> <small class="text-muted">(<?php echo $title['type']; ?>)</small></h1>
                    
                    <ul class="meta-list list-unstyled">
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
                        <?php if (!empty($title['genre_ids'])): ?>
                            <li><strong>Genres:</strong> 
                                <?php
                                $genre_ids = explode(',', $title['genre_ids']);
                                $genre_names = [];
                                foreach ($genre_ids as $gid) {
                                    $gid = trim($gid);
                                    if ($gid) {
                                        $gstmt = $db->prepare('SELECT genre_name FROM genres WHERE genre_id = ?');
                                        $gstmt->execute([$gid]);
                                        $g = $gstmt->fetchColumn();
                                        if ($g) $genre_names[] = htmlspecialchars($g);
                                    }
                                }
                                echo $genre_names ? implode(', ', $genre_names) : 'None';
                                ?>
                            </li>
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
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>