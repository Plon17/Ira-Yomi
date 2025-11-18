<?php
session_start();
require 'includes/config.php';

$stmt = $db->query('SELECT title_id, title, type, synopsis FROM titles WHERE is_approved = TRUE ORDER BY created_at DESC LIMIT 5');
$titles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ira-Yomi - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Welcome to Ira-Yomi</h1>
        <?php if (isset($_SESSION['user'])): ?>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user']['username']); ?>!</p>
        <?php else: ?>
            <p>Join our community to discuss and review Ira media!</p>
        <?php endif; ?>
        <h3>Recent Titles</h3>
        <?php if (empty($titles)): ?>
            <p>No titles available yet.</p>
        <?php else: ?>
            <div class="row">
                <?php foreach ($titles as $title): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($title['title']); ?> (<?php echo $title['type']; ?>)</h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($title['synopsis'], 0, 100)); ?>...</p>
                                <a href="pages/title.php?id=<?php echo $title['title_id']; ?>" class="btn btn-primary">View</a>
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