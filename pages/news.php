<?php
ini_set('allow_url_fopen', 1);
session_start();
require '../includes/config.php';

$api_url = 'https://www.animenewsnetwork.com/encyclopedia/api.php?type=anime&nlist=30';
$news = [];
$error = '';

try {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
            'timeout' => 10
        ]
    ];

    $context = stream_context_create($opts);
    $xml_string = @file_get_contents($api_url, false, $context);

    if ($xml_string === false) {
        throw new Exception('Could not reach Anime News Network');
    }

    $xml = simplexml_load_string($xml_string);
    if ($xml === false) {
        throw new Exception('Invalid XML received');
    }

    foreach ($xml->item as $item) {
        $news[] = [
            'title'       => (string)$item->title,
            'link'        => (string)$item->link,
            'description' => strip_tags((string)$item->description),
            'pubDate'     => date('M j, Y', strtotime((string)$item->pubDate)),
            'image'       => $item->enclosure ? (string)$item->enclosure['url'] : null
        ];
    }

} catch (Exception $e) {
    $error = 'News feed unavailable: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News - Ira-Yomi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; }
        .news-card { background:white; border-radius:15px; overflow:hidden; box-shadow:0 6px 20px rgba(0,0,0,0.1); transition:0.3s; }
        .news-card:hover { transform:translateY(-8px); box-shadow:0 15px 30px rgba(0,0,0,0.2); }
        .news-img { height:200px; object-fit:cover; }
        .news-title { font-weight:bold; color:#1da1f2; }
        .source { font-size:0.85rem; color:#666; }
        .badge-ann { background:#dc3545; color:white; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container my-5">
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold">Latest Anime News</h1>
            <p class="lead">
                Powered by 
                <a href="https://www.animenewsnetwork.com" target="_blank" class="badge badge-ann">Anime News Network</a>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-warning text-center">
                <?= htmlspecialchars($error) ?> — Showing cached/offline mode.
            </div>
        <?php endif; ?>

        <?php if (empty($news)): ?>
            <div class="text-center py-5">
                <h3 class="text-muted">No news available right now</h3>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($news as $item): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="news-card h-100 d-flex flex-column">
                            <?php if ($item['image']): ?>
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" class="news-img" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php else: ?>
                                <div class="news-img bg-light d-flex align-items-center justify-content-center" style="height:200px;">
                                    <h5 class="text-muted">No Image</h5>
                                </div>
                            <?php endif; ?>

                            <div class="card-body d-flex flex-column">
                                <h5 class="news-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                <p class="card-text flex-grow-1"><?php echo htmlspecialchars(substr($item['description'], 0, 150)) . '...'; ?></p>
                                <div class="mt-auto">
                                    <small class="text-muted d-block mb-2"><?php echo $item['pubDate']; ?></small>
                                    <a href="<?php echo htmlspecialchars($item['link']); ?>" 
                                       target="_blank" 
                                       class="btn btn-primary w-100">
                                        Read Full Article →
                                    </a>
                                </div>
                            </div>
                            <div class="card-footer text-center source">
                                Source: Anime News Network
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
