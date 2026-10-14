<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/../includes/init.php';
// EN: WHY: only admins manage users (roles table). 中文: 为什么：只有 admin 能管理用户（权限表）。
require_role(['admin']);

// EN: a POST means the Delete button was pressed. 中文: 收到 POST 表示点了 Delete 按钮。
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);

    // EN: WHY: an admin deleting themselves would lock everyone out. 中文: 为什么：管理员删除自己会导致没人能管理系统。
    if ($id === (int) $_SESSION['user_id']) {
        flash('error', 'You can\'t delete your own account.');
    } else {
        try {
            // EN: EFFECT: delete the user; MySQL refuses if borrowing records refer to them. 中文: 效果：删除用户；如果有借阅记录引用，MySQL 会拒绝。
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', 'User deleted.');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                flash('error', 'This user has borrowing records, so the account can\'t be deleted.');
            } else {
                throw $ex;
            }
        }
    }
    redirect('admin/users.php');
}

// EN: filters from the URL. 中文: 来自网址的筛选条件。
$role = $_GET['role'] ?? '';
$q    = trim($_GET['q'] ?? '');
$where = [];
$params = [];
// EN: WHY: only accept the three real roles (anything else is ignored). 中文: 为什么：只接受三个真实角色（其他值一律忽略）。
if (in_array($role, ['admin', 'librarian', 'student'], true)) {
    $where[] = 'u.role = :role';
    $params[':role'] = $role;
} else {
    $role = '';
}
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
    $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2)';
    $params[':q1'] = $params[':q2'] = $like;
}
// EN: WHY: the sub-SELECT counts each user's current loans in the same query. ORDER BY role then name. 中文: 为什么：子查询在同一条查询里统计每个用户的在借数量。先按角色再按姓名排序。
$stmt = $pdo->prepare('SELECT u.id, u.name, u.email, u.role, u.created_at,
                              (SELECT COUNT(*) FROM borrowings b WHERE b.user_id = u.id AND b.status = \'borrowed\') AS active_loans
                       FROM users u ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY u.role, u.name');
$stmt->execute($params);
$users = $stmt->fetchAll();

$page_title = 'Users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Users</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/user-form.php')) ?>">Add a user</a>
</div>

<form class="filters filters-compact" method="get" role="search">
    <div><label for="q">Search</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Name or email"></div>
    <div>
        <label for="role">Role</label>
        <select id="role" name="role">
            <option value="">All roles</option>
            <?php foreach (['admin', 'librarian', 'student'] as $r): ?>
                <?php // EN: ucfirst makes "admin" display as "Admin". | 中文: ucfirst 让 "admin" 显示成 "Admin"。 ?>
                <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-primary" type="submit">Filter</button>
</form>

<?php if (!$users): ?>
    <div class="empty">No users found.</div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Books on loan</th><th>Joined</th><th class="col-actions">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <?php // EN: add a "You" label on your own row. | 中文: 在自己的那一行加上 "You" 标签。 ?>
            <td><strong><?= e($u['name']) ?></strong><?= (int) $u['id'] === (int) $_SESSION['user_id'] ? ' <span class="chip chip-muted">You</span>' : '' ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e(ucfirst($u['role'])) ?></td>
            <td><?= (int) $u['active_loans'] ?></td>
            <td><?= e(fmt_date($u['created_at'])) ?></td>
            <td class="col-actions">
                <a class="btn btn-small" href="<?= e(url('admin/user-form.php?id=' . (int) $u['id'])) ?>">Edit</a>
                <?php // EN: hide Delete on your own row (also blocked in PHP above). | 中文: 自己那一行不显示 Delete（上面的 PHP 也会拦截）。 ?>
                <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                <form method="post" onsubmit="return confirm('Delete this user?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-small btn-danger" type="submit">Delete</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
