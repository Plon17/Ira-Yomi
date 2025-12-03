<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'] ?? '';

    if ($user_id === $_SESSION['user']['user_id']) {
        $error = "You cannot modify your own account!";
    } else {
        try {
            if ($action === 'ban') {
                $db->prepare('UPDATE users SET is_banned = 1 WHERE user_id = ?')->execute([$user_id]);
                $success = "User banned successfully.";
            } elseif ($action === 'unban') {
                $db->prepare('UPDATE users SET is_banned = 0 WHERE user_id = ?')->execute([$user_id]);
                $success = "User unbanned successfully.";
            } elseif ($action === 'make_admin') {
                $db->prepare('UPDATE users SET role = "admin" WHERE user_id = ?')->execute([$user_id]);
                $success = "User is now an admin.";
            } elseif ($action === 'remove_admin') {
                $db->prepare('UPDATE users SET role = "user" WHERE user_id = ?')->execute([$user_id]);
                $success = "Admin rights removed.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$users = $db->query('
    SELECT user_id, username, email, role, is_banned, created_at 
    FROM users 
    ORDER BY created_at DESC
')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Ira-Yomi Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .btn-primary { background:#1da1f2; border:none; }
        .btn-primary:hover { background:#1a91da; }
        .btn-danger { background:#dc3545; border:none; }
        .btn-success { background:#28a745; border:none; }
        .btn-warning { background:#ffc107; border:none; color:#212529; }
        .badge-admin { background:#1da1f2; }
        .badge-banned { background:#dc3545; }
        h1 { font-size:1.8rem; font-weight:bold; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container mt-5">
        <h1 class="mb-4">Manage Users</h1>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Registered</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr <?php echo $u['is_banned'] ? 'class="table-danger"' : ''; ?>>
                                    <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge badge-admin text-white">Admin</span>
                                        <?php endif; ?>
                                        <?php if ($u['is_banned']): ?>
                                            <span class="badge badge-banned">Banned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if ($u['is_banned']): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                    <button type="submit" name="action" value="unban" class="btn btn-success btn-sm">Unban</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                    <button type="submit" name="action" value="ban" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Ban this user?')">Ban</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($u['role'] !== 'admin'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                    <button type="submit" name="action" value="make_admin" class="btn btn-primary btn-sm">Make Admin</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                    <button type="submit" name="action" value="remove_admin" class="btn btn-warning btn-sm"
                                                            onclick="return confirm('Remove admin rights?')">Remove Admin</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>