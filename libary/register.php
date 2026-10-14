<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/includes/init.php';

// EN: WHY: logged-in users don't need to register again. EFFECT: send them to their home page. 中文: 为什么：已登录的用户不需要再注册。效果：把他们送到自己的首页。
if (is_logged_in()) {
    redirect(home_for_role(current_role()));
}

// EN: WHY: collect one error message per field, so each can be shown under its input. 中文: 为什么：每个字段收集一条错误，显示在对应输入框下面。
$errors = [];
// EN: WHY: keep what the user typed so the form is refilled after an error. 中文: 为什么：保存用户已输入的内容，出错后表单能自动回填。
$name = '';
$email = '';

// EN: run only when the form was submitted. 中文: 只有提交表单时才运行。
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // EN: check the CSRF token. 中文: 检查 CSRF 令牌。
    csrf_verify();
    // EN: read inputs; "?? ''" avoids errors if a field is missing; trim removes outer spaces. 中文: 读取输入；"?? ''" 防止字段缺失时报错；trim 去掉两端空格。
    $name     = trim($_POST['name'] ?? '');
    // EN: emails are lower-cased so "A@x.com" and "a@x.com" are the same account. 中文: 邮箱转小写，"A@x.com" 和 "a@x.com" 视为同一个账号。
    $email    = strtolower(trim($_POST['email'] ?? ''));
    // EN: passwords are never trimmed. 中文: 密码不做 trim。
    $password = $_POST['password'] ?? '';
    // EN: the "confirm password" field. 中文: "确认密码"字段。
    $confirm  = $_POST['confirm'] ?? '';

    // EN: WHY: mb_strlen counts characters properly (Chinese names too); matches the DB column VARCHAR(100). 中文: 为什么：mb_strlen 能正确计算字符数（包括中文名）；和数据库 VARCHAR(100) 对应。
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = 'Enter your name (2 to 100 characters).';
    }
    // EN: WHY: filter_var checks the email format for us. 中文: 为什么：filter_var 帮我们检查邮箱格式。
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors['email'] = 'Enter a valid email address.';
    }
    // EN: WHY: minimum password length for basic security. 中文: 为什么：设置最短密码长度，保证基本安全。
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    }
    // EN: WHY: catch typing mistakes. 中文: 为什么：发现输入错误。
    if ($password !== $confirm) {
        $errors['confirm'] = 'The passwords don\'t match.';
    }

    // EN: only query the database if the basic checks passed. 中文: 基本检查通过后才查询数据库。
    if (!$errors) {
        // EN: look for an existing account with this email. 中文: 查找是否已有这个邮箱的账号。
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        // EN: fetch() returns a row if found. 中文: 找到就返回一行。
        if ($stmt->fetch()) {
            $errors['email'] = 'An account with this email already exists. Try logging in.';
        }
    }

    // EN: still no errors: create the account. 中文: 仍然没有错误：创建账号。
    if (!$errors) {
        // EN: try/catch because two people could register the same email at the same moment. 中文: 用 try/catch，因为两个人可能同一时刻注册同一个邮箱。
        try {
            // Public sign-up always creates a student. Staff accounts are made by an admin.
            // EN: WHY: role is fixed in SQL as 'student', never read from the form, so nobody can make themselves admin. 中文: 为什么：角色在 SQL 里固定为 'student'，不从表单读取，所以没人能给自己注册 admin。
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
            // EN: WHY: password_hash makes a one-way hash; the real password is never stored. 中文: 为什么：password_hash 生成单向哈希，不保存真实密码。
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', 'Account created. Log in to start borrowing.');
            // EN: EFFECT: go to the login page. 中文: 效果：跳到登录页。
            redirect('login.php');
        } catch (PDOException $ex) {
            // EN: 23000 = duplicate (the UNIQUE email rule fired). 中文: 23000 表示重复（邮箱的 UNIQUE 规则触发）。
            if ($ex->getCode() === '23000') {
                $errors['email'] = 'An account with this email already exists. Try logging in.';
            } else {
                // EN: unknown error: don't hide it. 中文: 未知错误：不要隐藏。
                throw $ex;
            }
        }
    }
}

// EN: WHY: small helper that prints the red error text under a field. 中文: 为什么：一个小函数，在字段下面输出红色错误文字。
function field_error(array $errors, string $key): string
{
    // EN: EFFECT: if this field has an error, return a <p> with it (id lets other things point to it); otherwise ''. 中文: 效果：这个字段有错误就返回带错误的 <p>（id 方便其他元素引用）；否则返回空字符串。
    return isset($errors[$key]) ? '<p class="field-error" id="err-' . $key . '">' . e($errors[$key]) . '</p>' : '';
}

// EN: browser tab title. 中文: 浏览器标签标题。
$page_title = 'Create account';
// EN: print the top of the page. 中文: 输出页面顶部。
require __DIR__ . '/includes/header.php';
?>
<div class="form-card">
    <div class="inner">
        <h1>Create your account</h1>
        <?php // EN: novalidate turns off the browser's own checks so our PHP messages are shown consistently. | 中文: novalidate 关闭浏览器自带校验，统一显示我们 PHP 的提示。 ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="name">Full name</label>
                <?php // EN: value refills the typed name; class "input-invalid" adds a red border when there's an error. | 中文: value 回填已输入的姓名；有错误时加上 "input-invalid" 类显示红框。 ?>
                <input id="name" name="name" value="<?= e($name) ?>" autocomplete="name" required class="<?= isset($errors['name']) ? 'input-invalid' : '' ?>">
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required class="<?= isset($errors['email']) ? 'input-invalid' : '' ?>">
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <?php // EN: passwords are never refilled (safer). new-password tells password managers to suggest one. | 中文: 密码不回填（更安全）。new-password 让密码管理器建议新密码。 ?>
                <input id="password" type="password" name="password" autocomplete="new-password" required class="<?= isset($errors['password']) ? 'input-invalid' : '' ?>">
                <p class="field-hint">At least 8 characters.</p>
                <?= field_error($errors, 'password') ?>
            </div>
            <div class="field">
                <label for="confirm">Confirm password</label>
                <input id="confirm" type="password" name="confirm" autocomplete="new-password" required class="<?= isset($errors['confirm']) ? 'input-invalid' : '' ?>">
                <?= field_error($errors, 'confirm') ?>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Create account</button>
        </form>
        <p class="form-switch">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
