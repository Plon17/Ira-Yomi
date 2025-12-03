<?php
session_start();
require '../includes/config.php';

// Get search term and selected genres
$query = trim($_GET['q'] ?? '');
$selected_genres = $_GET['genres'] ?? [];

// Build the SQL query
$sql = 'SELECT title_id, title, type, cover_image, synopsis, author 
        FROM titles 
        WHERE is_approved = 1';

$params = [];

if ($query !== '') {
    $sql .= ' AND (title LIKE :query OR author LIKE :query OR tags LIKE :query)';
    $params['query'] = "%$query%";
}

if (!empty($selected_genres)) {
    $placeholders = str_repeat('?,', count($selected_genres) - 1) . '?';
    $sql .= " AND genre_ids REGEXP ?";
    $regex = '(^|,)' . implode('($|,)', $selected_genres) . '($|,)';
    $params[] = $regex;
}

$sql .= ' ORDER BY created_at DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load all genres for the filter
$genres = $db->query('SELECT genre_id, genre_name FROM genres ORDER BY genre_name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - Ira-Yomi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; }
        .card-img-top { height:320px; object-fit:cover; }
        .card { transition:0.2s; border:none; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .card:hover { transform:translateY(-8px); box-shadow:0 12px 24px rgba(0,0,0,0.15); }
        .card-title { font-weight:bold; color:#1da1f2; }
        .btn-primary { background:#1da1f2; border:none; }
        .btn-primary:hover { background:#1a91da; }
        .type-badge { position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.7); color:white; padding:4px 10px; border-radius:20px; font-size:0.8rem; }
        .genre-filter { background:white; padding:20px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container my-5">
        <h1 class="mb-4 text-center">Search Titles</h1>

        <div class="row">
            <!-- Filter Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="genre-filter">
                    <form method="GET">
                        <div class="mb-3">
                            <input type="text" name="q" class="form-control" placeholder="Title, author, tags..." 
                                   value="<?php echo htmlspecialchars($query); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Filter by Genre</label>
                            <?php foreach ($genres as $g): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="genres[]" value="<?php echo $g['genre_id']; ?>"
                                           id="genre<?php echo $g['genre_id']; ?>" <?php echo in_array($g['genre_id'], $selected_genres) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="genre<?php echo $g['genre_id']; ?>">
                                        <?php echo htmlspecialchars($g['genre_name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </form>
                </div>
            </div>

            <!-- Results -->
            <div class="col-lg-9">
                <?php if (empty($results)): ?>
                    <div class="text-center py-5">
                        <h3 class="text-muted">No titles found</h3>
                        <a href="search.php" class="btn btn-primary mt-3">Clear filters</a>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                        <?php foreach ($results as $t): ?>
                            <div class="col">
                                <div class="card h-100 position-relative">
                                    <?php if ($t['cover_image'] && file_exists("../" . $t['cover_image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($t['cover_image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($t['title']); ?>">
                                    <?php else: ?>
                                        <img src="../images/default-cover.jpg" class="card-img-top" alt="No cover">
                                    <?php endif; ?>
                                    <div class="type-badge"><?php echo htmlspecialchars($t['type']); ?></div>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?php echo htmlspecialchars($t['title']); ?></h5>
                                        <p class="card-text flex-grow-1">
                                            <?php echo htmlspecialchars(substr($t['synopsis'], 0, 120)) . (strlen($t['synopsis']) > 120 ? '...' : ''); ?>
                                        </p>
                                        <a href="title.php?id=<?php echo $t['title_id']; ?>" class="btn btn-primary mt-auto">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>