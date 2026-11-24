<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $targetId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    // Admins cannot lock themselves out or remove their own admin role.
    if (!$targetId || $targetId === (int) $me['id']) {
        flash('error', 'You cannot change your own account here.');
        redirect('admin/users.php');
    }
    if ($action === 'toggle_active') {
        db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$targetId]);
        audit('user_status', 'Toggled active status of user #' . $targetId);
        flash('success', 'User status updated.');
    } elseif ($action === 'set_role' && in_array($_POST['role'] ?? '', ['user', 'admin'], true)) {
        db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$_POST['role'], $targetId]);
        audit('user_role', 'User #' . $targetId . ' role set to ' . $_POST['role']);
        flash('success', 'User role updated.');
    }
    redirect('admin/users.php' . (isset($_POST['return']) && is_string($_POST['return']) && str_starts_with($_POST['return'], '?') ? $_POST['return'] : ''));
}

$q = get_filter('q');
$role = get_filter('role', ['user', 'admin']);
$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like);
}
if ($role !== '') { $where[] = 'u.role = ?'; $params[] = $role; }

$stmt = db()->prepare('SELECT u.id, u.full_name, u.email, u.phone, u.role, u.is_active, u.created_at, u.last_login,
    (SELECT COUNT(*) FROM feedback f WHERE f.user_id = u.id) AS feedback_count
    FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.created_at DESC');
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage users';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head"><div><h1>Manage users</h1><p class="muted"><?= count($users) ?> account<?= count($users) === 1 ? '' : 's' ?></p></div></div>
<div class="card">
    <form method="get" class="filters filters-3" role="search">
        <div class="form-group"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name or email…"></div>
        <div class="form-group"><label for="role">Role</label>
            <select id="role" name="role" data-autosubmit><option value="">All</option>
                <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>User</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select></div>
        <div class="btn-row"><button class="btn" type="submit">Filter</button><a class="btn btn-outline" href="<?= e(url('admin/users.php')) ?>">Reset</a></div>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Feedback</th><th>Last login</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $me['id']; ?>
            <tr>
                <td><?= e($u['full_name']) ?><?= $self ? ' <span class="muted small">(you)</span>' : '' ?></td>
                <td><?= e($u['email']) ?></td>
                <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-red' : 'badge-grey' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                <td><?= $u['is_active'] ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-amber">Deactivated</span>' ?></td>
                <td><?= (int) $u['feedback_count'] ?></td>
                <td class="nowrap"><?= e(fmt_date($u['last_login'], true)) ?></td>
                <td>
                    <?php if (!$self): ?>
                    <div class="btn-row">
                        <form method="post" class="inline-form" data-confirm="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this account?">
                            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="return" value="<?= e(query_with([])) ?>">
                            <button class="btn btn-small btn-outline" type="submit"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                        <form method="post" class="inline-form" data-confirm="Change this user's role?">
                            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="set_role">
                            <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                            <input type="hidden" name="return" value="<?= e(query_with([])) ?>">
                            <button class="btn btn-small btn-outline" type="submit"><?= $u['role'] === 'admin' ? 'Make user' : 'Make admin' ?></button>
                        </form>
                    </div>
                    <?php else: ?><span class="muted small">—</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
