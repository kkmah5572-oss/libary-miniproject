<?php
// EN: load session, database and helper functions. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/includes/init.php';

// EN: WHY: "next" = the page the visitor wanted before being asked to log in. It can come from the URL (GET) or the form (POST). safe_next() rejects dangerous values. EFFECT: $next is '' or a safe page name.
// 中文: 为什么："next" 是访客登录前想访问的页面，可能来自网址（GET）或表单（POST）。safe_next() 会拒绝危险的值。效果：$next 是空字符串或安全的页面名。
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');

// EN: WHY: a logged-in person doesn't need the login page. 中文: 为什么：已登录的人不需要看登录页。
if (is_logged_in()) {
    // EN: EFFECT: go to "next" if set, otherwise to the role's home page. "?:" means "use the right side if the left is empty". 中文: 效果：有 next 就去 next，否则去该角色的首页。"?:" 表示左边为空就用右边。
    redirect($next ?: home_for_role(current_role()));
}

// EN: holds the error message (empty = no error). 中文: 保存错误信息（空 = 没有错误）。
$error = '';
// EN: WHY: remember the typed email so it is refilled after a failed login. 中文: 为什么：记住输入的邮箱，登录失败后能自动回填。
$email = '';

// EN: run only when the login form was submitted. 中文: 只有提交登录表单时才运行。
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // EN: check the CSRF token. 中文: 检查 CSRF 令牌。
    csrf_verify();
    // EN: WHY: emails are not case-sensitive, so lower-case and trim spaces. EFFECT: "  Sam@X.com " becomes "sam@x.com". 中文: 为什么：邮箱不区分大小写，所以转小写并去空格。效果："  Sam@X.com " 变成 "sam@x.com"。
    $email    = strtolower(trim($_POST['email'] ?? ''));
    // EN: the password is NOT trimmed — spaces may be part of it. 中文: 密码不做 trim，因为空格可能是密码的一部分。
    $password = $_POST['password'] ?? '';

    // EN: WHY: prepared statement with "?" prevents SQL injection. EFFECT: looks up the user by email. 中文: 为什么：带 "?" 的预处理语句可防止 SQL 注入。效果：按邮箱查找用户。
    $stmt = $pdo->prepare('SELECT id, name, password, role FROM users WHERE email = ?');
    // EN: run it with the email. 中文: 带上邮箱执行。
    $stmt->execute([$email]);
    // EN: EFFECT: $user = the row, or false if no such email. 中文: 效果：$user 是查到的行；邮箱不存在则为 false。
    $user = $stmt->fetch();

    // Always run password_verify so a wrong email and a wrong password take the same time.
    // EN: WHY: if the email doesn't exist we still check against a fake hash, so attackers can't tell "no such email" from "wrong password" by timing. 
    // 中文: 为什么：邮箱不存在时也拿一个假的哈希去比对，这样攻击者无法通过响应时间区分"邮箱不存在"和"密码错误"。
    $hash = $user['password'] ?? '$2y$10$Bevyb6M2X6AuZGDzQuj0EOEM3dLcD9Jh0CjIgM1AatFajE5J2PpyK';
    // EN: WHY: passwords are stored as hashes, never plain text; password_verify compares them. EFFECT: true only if the user exists AND the password matches.
    // 中文: 为什么：密码以哈希保存，不存明文；password_verify 负责比对。效果：只有用户存在且密码正确才为 true。
    if ($user && password_verify($password, $hash)) {
        // EN: WHY: a new session id after login stops "session fixation" attacks. true = delete the old session. 中文: 为什么：登录后换新的 session id，防止"会话固定"攻击。true 表示删除旧 session。
        session_regenerate_id(true);
        // EN: EFFECT: remember the user's id in the session = "logged in". (int) keeps it a number. 中文: 效果：把用户 id 存进 session，表示"已登录"。(int) 保证是数字。
        $_SESSION['user_id'] = (int) $user['id'];
        // EN: remember the name for the header. 中文: 记住姓名，给页头显示。
        $_SESSION['name']    = $user['name'];
        // EN: remember the role for permission checks. 中文: 记住角色，用于权限检查。
        $_SESSION['role']    = $user['role'];
        // EN: queue a welcome message. 中文: 排一条欢迎消息。
        flash('success', 'Welcome back, ' . $user['name'] . '.');
        // EN: EFFECT: go to the wanted page or the role's home, and stop. 中文: 效果：去想访问的页面或角色首页，并终止。
        redirect($next ?: home_for_role($user['role']));
    }
    // EN: WHY: one message for both wrong email and wrong password, so nothing is leaked. 中文: 为什么：邮箱错和密码错用同一条提示，不泄露任何信息。
    $error = 'The email or password is incorrect.';
}

// EN: browser tab title. 中文: 浏览器标签标题。
$page_title = 'Log in';
// EN: print the top of the page. 中文: 输出页面顶部。
require __DIR__ . '/includes/header.php';
?>
<?php // EN: the white card holding the form. | 中文: 装表单的白色卡片。 ?>
<div class="form-card">
    <div class="inner">
        <h1>Log in</h1>
        <?php // EN: show the error box only if there is an error. | 中文: 只有出错时才显示错误框。 ?>
        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php // EN: method="post" so the password is not put in the URL. | 中文: 用 method="post"，密码就不会出现在网址里。 ?>
        <form method="post">
            <?php // EN: CSRF token. | 中文: CSRF 令牌。 ?>
            <?= csrf_field() ?>
            <?php // EN: hidden field keeps "next" through the form submit. | 中文: 隐藏字段让 "next" 随表单一起提交。 ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <div class="field">
                <label for="email">Email</label>
                <?php // EN: type="email" gives phone keyboards/validation; autocomplete helps password managers; autofocus puts the cursor here. | 中文: type="email" 让手机键盘和校验更合适；autocomplete 帮助密码管理器；autofocus 让光标自动停在这里。 ?>
                <input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <?php // EN: type="password" hides the typed characters. | 中文: type="password" 会隐藏输入的字符。 ?>
                <input id="password" type="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Log in</button>
        </form>
        <?php // EN: link for new visitors. | 中文: 给新访客的注册链接。 ?>
        <p class="form-switch">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
    </div>
</div>
<?php // EN: print the bottom of the page. | 中文: 输出页面底部。 ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
