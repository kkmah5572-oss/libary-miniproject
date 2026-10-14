<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/../includes/init.php';
// EN: only admins create/edit users. 中文: 只有 admin 能新增/编辑用户。
require_role(['admin']);

// EN: id = 0 means "add a user"; id > 0 means "edit this user". 中文: id = 0 表示"新增用户"；id > 0 表示"编辑该用户"。
$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
// EN: defaults for a new user. 中文: 新用户的默认值。
$user = ['name' => '', 'email' => '', 'role' => 'student'];

if ($id) {
    // EN: WHY: the password column is NOT selected, so the hash never reaches the form. 中文: 为什么：没有查询 password 列，所以密码哈希不会出现在表单里。
    $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('error', 'That user doesn\'t exist.');
        redirect('admin/users.php');
    }
    $user = $found;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name     = trim($_POST['name'] ?? '');
    // EN: lower-case email so duplicates are detected correctly. 中文: 邮箱转小写，这样重复检测才准确。
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $role     = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    // EN: validation, same rules as register.php. 中文: 校验，规则和 register.php 一样。
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) { $errors['name'] = 'Enter a name (2 to 100 characters).'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) { $errors['email'] = 'Enter a valid email address.'; }
    // EN: WHY: only the three real roles are accepted (the form can be faked). 中文: 为什么：只接受三个真实角色（表单可能被伪造）。
    if (!in_array($role, ['admin', 'librarian', 'student'], true)) { $errors['role'] = 'Choose a role.'; }
    // EN: new user: password required. 中文: 新用户：必须设置密码。
    if (!$id && strlen($password) < 8) { $errors['password'] = 'Use at least 8 characters.'; }
    // EN: editing: password optional, but if typed it must be long enough. 中文: 编辑：密码可不填；填了就必须够长。
    if ($id && $password !== '' && strlen($password) < 8) { $errors['password'] = 'Use at least 8 characters, or leave blank to keep the current one.'; }
    // EN: WHY: an admin who demotes themselves could lock the system. 中文: 为什么：管理员给自己降级会导致系统没人能管理。
    if ($id === (int) $_SESSION['user_id'] && $role !== $user['role'] && !isset($errors['role'])) {
        $errors['role'] = 'You can\'t change your own role.';
    }

    if (!$errors) {
        // EN: email must be unique; "id <> ?" ignores this same user when editing. 中文: 邮箱必须唯一；"id <> ?" 在编辑时排除这个用户自己。
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) { $errors['email'] = 'Another account already uses this email.'; }
    }

    // EN: refill the form with what was typed. 中文: 用已输入的内容回填表单。
    $user = array_merge($user, ['name' => $name, 'email' => $email, 'role' => $role]);

    if (!$errors) {
        if ($id) {
            // EN: editing with a new password: update the password hash too. 中文: 编辑且填了新密码：连密码哈希一起更新。
            if ($password !== '') {
                $pdo->prepare('UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?')
                    ->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                // EN: no new password: leave the old one unchanged. 中文: 没填新密码：保持旧密码不变。
                $pdo->prepare('UPDATE users SET name=?, email=?, role=? WHERE id=?')->execute([$name, $email, $role, $id]);
            }
            // EN: if you edited yourself, refresh the name shown in the header. 中文: 如果编辑的是自己，刷新页头显示的姓名。
            if ($id === (int) $_SESSION['user_id']) { $_SESSION['name'] = $name; }
            flash('success', 'User updated.');
        } else {
            // EN: new user: store the password only as a hash. 中文: 新用户：密码只以哈希形式保存。
            $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)')
                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            flash('success', 'User added.');
        }
        redirect('admin/users.php');
    }
}

// EN: helper for red error text under a field. 中文: 在字段下显示红色错误文字的小函数。
function err(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
}

$page_title = $id ? 'Edit user' : 'Add user';
require __DIR__ . '/../includes/header.php';
?>
<div class="form-card">
    <div class="inner">
        <h1><?= $id ? 'Edit user' : 'Add a user' ?></h1>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="field">
                <label for="name">Full name</label>
                <input id="name" name="name" value="<?= e($user['name']) ?>" required>
                <?= err($errors, 'name') ?>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?= e($user['email']) ?>" required>
                <?= err($errors, 'email') ?>
            </div>
            <div class="field">
                <label for="role">Role</label>
                <?php // EN: when editing yourself, add aria-describedby so screen readers link to the role note. | 中文: 编辑自己时加上 aria-describedby，让读屏软件关联到角色说明。 ?>
                <select id="role" name="role" <?= $id === (int) $_SESSION['user_id'] ? 'aria-describedby="role-note"' : '' ?>>
                    <?php foreach (['student', 'librarian', 'admin'] as $r): ?>
                        <option value="<?= $r ?>" <?= $user['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= err($errors, 'role') ?>
            </div>
            <div class="field">
                <?php // EN: label wording changes: "New password" when editing. | 中文: 标签文字随模式变化：编辑时是 "New password"。 ?>
                <label for="password"><?= $id ? 'New password' : 'Password' ?></label>
                <?php // EN: "required" only when adding a new user. | 中文: 只有新增用户时才 required。 ?>
                <input id="password" type="password" name="password" autocomplete="new-password" <?= $id ? '' : 'required' ?>>
                <p class="field-hint"><?= $id ? 'Leave blank to keep the current password.' : 'At least 8 characters.' ?></p>
                <?= err($errors, 'password') ?>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $id ? 'Save changes' : 'Add user' ?></button>
                <a class="btn btn-outline" href="<?= e(url('admin/users.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
