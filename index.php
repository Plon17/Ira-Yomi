<?php
session_start();
require 'includes/config.php';

// Fetch only approved titles, newest first
$stmt = $db->query('
    SELECT title_id, title, type, cover_image, synopsis 
    FROM titles 
    WHERE is_approved = 1 
    ORDER BY created_at DESC
');
$titles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ira-Yomi - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; }
        .card-img-top {
            height: 320px;
            object-fit: cover;
        }
        .card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: #1da1f2;
        }
        .card-text {
            font-size: 0.9rem;
            color: #555;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .btn-primary {
            background:#1da1f2;
            border:none;
        }
        .btn-primary:hover {
            background:#1a91da;
        }
        .type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container my-5">
        <h1 class="mb-4 text-center">Latest Titles</h1>

        <?php if (empty($titles)): ?>
            <div class="text-center py-5">
                <h3 class="text-muted">No titles available yet.</h3>
                <a href="pages/addTitle.php" class="btn btn-primary mt-3">Be the first to add one!</a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
                <?php foreach ($titles as $t): ?>
                    <div class="col">
                        <div class="card h-100 position-relative">
                            <?php if ($t['cover_image'] && file_exists($t['cover_image'])): ?>
                                <img src="<?php echo htmlspecialchars($t['cover_image']); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($t['title']); ?>">
                            <?php else: ?>
                                <img src="images/default-cover.jpg" class="card-img-top" alt="No cover">
                            <?php endif; ?>

                            <div class="type-badge"><?php echo htmlspecialchars($t['type']); ?></div>

                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($t['title']); ?></h5>
                                <p class="card-text flex-grow-1">
                                    <?php echo htmlspecialchars(substr($t['synopsis'], 0, 120)) . (strlen($t['synopsis']) > 120 ? '...' : ''); ?>
                                </p>
                                <a href="pages/title.php?id=<?php echo $t['title_id']; ?>" 
                                   class="btn btn-primary mt-auto">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>