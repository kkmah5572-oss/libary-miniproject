<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/includes/init.php';

// EN: WHY: nobody to log out if not logged in. EFFECT: go to the login page. 中文: 为什么：没登录就没有人可登出。效果：跳到登录页。
if (!is_logged_in()) {
    redirect('login.php');
}

// EN: only a real form submit (POST) logs out. 中文: 只有真正提交表单（POST）才登出。
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // EN: WHY: stops other sites from logging the user out. 中文: 为什么：防止别的网站强制让用户登出。
    csrf_verify();

    // Wipe the session completely, then start a clean one just to show a message.
    // EN: EFFECT: empties all session data (user id, name, role). 中文: 效果：清空 session 里的所有数据（用户 id、姓名、角色）。
    $_SESSION = [];
    // EN: only if sessions use cookies. 中文: 只在 session 使用 cookie 时执行。
    if (ini_get('session.use_cookies')) {
        // EN: read the cookie settings so we delete the exact same cookie. 中文: 读取 cookie 设置，才能删除同一个 cookie。
        $p = session_get_cookie_params();
        // EN: EFFECT: set the cookie's expiry time in the past, so the browser deletes it. 中文: 效果：把 cookie 过期时间设为过去，浏览器就会删除它。
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    // EN: EFFECT: destroys the old session on the server. 中文: 效果：销毁服务器上的旧 session。
    session_destroy();
    // EN: WHY: we need a new session just to carry the "logged out" message. 中文: 为什么：需要一个新 session 来携带"已登出"提示。
    session_start();
    // EN: new id for the new session. 中文: 给新 session 换新 id。
    session_regenerate_id(true);
    flash('success', 'You have been logged out.');
    // EN: EFFECT: back to the login page. 中文: 效果：回到登录页。
    redirect('login.php');
}

// Someone opened logout.php directly: ask first.
// EN: WHY: opening the address (GET) must not log out; we show a confirm page instead. 中文: 为什么：直接打开网址（GET）不应登出；改为显示确认页面。
$page_title = 'Log out';
require __DIR__ . '/includes/header.php';
?>
<div class="form-card">
    <div class="inner">
        <h1>Log out?</h1>
        <form method="post">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-block" type="submit">Log out</button>
        </form>
        <?php // EN: cancel link back to the user's home page. | 中文: 取消链接，回到用户的首页。 ?>
        <p class="form-switch"><a href="<?= e(url(home_for_role(current_role()))) ?>">Stay logged in</a></p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
