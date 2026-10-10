<?php
require_once __DIR__ . '/includes/boot.php';
session_start();
require_once 'db.php';
require_once 'includes/csrf.php';

// Admins only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard");
    exit;
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Delete user
    if (isset($_POST['delete_user_id'])) {
        $delete_id = (int)$_POST['delete_user_id'];
        if ($delete_id !== (int)$_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$delete_id]);
            $message = "User deleted successfully.";
        } else {
            $error = "You cannot delete your own admin account.";
        }
    }

    // Toggle role (promote/demote)
    if (isset($_POST['toggle_role_id'])) {
        $target_id = (int)$_POST['toggle_role_id'];
        if ($target_id !== (int)$_SESSION['user_id']) {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$target_id]);
            $target = $stmt->fetch();
            if ($target) {
                $new_role = $target['role'] === 'admin' ? 'user' : 'admin';
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $target_id]);
                $message = "Role updated to " . $new_role . ".";
            }
        } else {
            $error = "You cannot change your own role.";
        }
    }
}

// Stats
$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$new_week = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Fetch all users
$stmt = $pdo->query("SELECT id, username, email, role, xp, level, google_id, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>
<?php $page_title = "User Management | ADHD.lk Admin"; require_once "includes/header.php"; ?>

    <main class="main-content container" style="align-items: stretch;">
        <div class="admin-header">
            <div>
                <h2>User Management</h2>
                <p style="color: var(--text-secondary);">Manage registered users and their roles.</p>
            </div>
            <input type="search" id="user-filter" class="form-control filter-input" placeholder="Filter by name or email…">
        </div>

        <div class="stat-row">
            <div class="stat-card"><div class="stat-num"><?php echo $total_users; ?></div><div class="stat-label">Total users</div></div>
            <div class="stat-card"><div class="stat-num"><?php echo $new_week; ?></div><div class="stat-label">New this week</div></div>
            <div class="stat-card"><div class="stat-num"><?php echo $total_admins; ?></div><div class="stat-label">Admins</div></div>
        </div>

        <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="glass-card table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Level</th>
                        <th>Sign-in</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="user-rows">
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo (int)$user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge <?php echo $user['role'] === 'admin' ? 'admin' : 'user'; ?>"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span></td>
                        <td>Lv <?php echo (int)$user['level']; ?> · <?php echo (int)$user['xp']; ?> XP</td>
                        <td><?php echo $user['google_id'] ? 'Google' : 'Password'; ?></td>
                        <td style="white-space: nowrap;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td style="white-space: nowrap;">
                            <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Change this user\'s role?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="toggle_role_id" value="<?php echo (int)$user['id']; ?>">
                                    <button type="submit" class="btn-small"><?php echo $user['role'] === 'admin' ? 'Demote' : 'Make admin'; ?></button>
                                </form>
                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Permanently delete this user and ALL their data?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_user_id" value="<?php echo (int)$user['id']; ?>">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            <?php else: ?>
                                <span style="color: var(--text-secondary); font-size: 0.85rem;">(You)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const filter = document.getElementById('user-filter');
        const rows = document.querySelectorAll('#user-rows tr');
        filter.addEventListener('input', () => {
            const q = filter.value.toLowerCase();
            rows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    });
    </script>
<?php require_once 'includes/footer.php'; ?>
