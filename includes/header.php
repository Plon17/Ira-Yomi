<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-brand { font-weight: bold; }
        .nav-link { color: #333; }
        .nav-link:hover { color: #1da1f2; }
        .dropdown-menu { background-color: #f8f9fa; }
        .dropdown-item:hover { background-color: #1da1f2; color: #fff; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">Ira-Yomi</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../pages/search.php">Search</a></li>
                    <li class="nav-item"><a class="nav-link" href="../pages/forum.php">Forum</a></li>
                    <li class="nav-item"><a class="nav-link" href="../pages/news.php">News</a></li>
                    <?php if (isset($_SESSION['user'])): ?>
                        <li class="nav-item"><a class="nav-link" href="../pages/addTitle.php">Add Title</a></li>
                        <li class="nav-item"><a class="nav-link" href="../pages/profile.php"><?php echo htmlspecialchars($_SESSION['user']['username']); ?></a></li>
                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Admin
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                    <li><a class="dropdown-item" href="../pages/admin/dashboard.php">Dashboard</a></li>
                                    <li><a class="dropdown-item" href="../pages/admin/manage-titles.php">Manage Titles</a></li>
                                    <li><a class="dropdown-item" href="../pages/admin/manage-users.php">Manage Users</a></li>
                                    <li><a class="dropdown-item" href="../pages/admin/manage-reviews.php">Manage Reviews</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="../pages/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="../pages/login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="../pages/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>