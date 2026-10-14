<?php
// Top of every page. Set $page_title before including.
// EN: WHY: the menu/top part is identical on every page, so it is written once. 中文: 为什么：每个页面的顶部菜单都一样，所以只写一次。

// EN: EFFECT: use the page's own title, or "SmartLib" if the page did not set one. 中文: 效果：使用页面自己设置的标题，没设置就用 "SmartLib"。
$page_title = $page_title ?? 'SmartLib';
// EN: EFFECT: who is visiting (guest/student/librarian/admin). 中文: 效果：访问者是谁（guest/student/librarian/admin）。
$role       = current_role();
// EN: EFFECT: path of the running script, e.g. "/smartlib/admin/books.php". 中文: 效果：当前运行脚本的路径，例如 "/smartlib/admin/books.php"。
$script     = $_SERVER['SCRIPT_NAME'] ?? '';
// EN: EFFECT: only the file name, e.g. "books.php". Used to highlight the active menu item. 中文: 效果：只取文件名，例如 "books.php"。用来高亮当前菜单项。
$here       = basename($script);
// EN: EFFECT: true if the page is inside the admin folder. 中文: 效果：页面在 admin 文件夹里就是 true。
$in_admin   = strpos($script, '/admin/') !== false;

// EN: WHY: builds one menu link; "$admin" tells whether it points into the admin folder. 中文: 为什么：生成一个菜单链接；"$admin" 表示它是否指向 admin 文件夹。
function nav_item(string $file, string $label, bool $admin = false): string
{
    // EN: WHY: functions can't see outside variables unless declared "global". 中文: 为什么：函数默认看不到外部变量，必须声明 global。
    global $here, $in_admin;
    // EN: EFFECT: if this link is the current page, add the "is-active" class (for styling) and aria-current (for screen readers); otherwise nothing. 
    // 中文: 效果：如果这个链接就是当前页面，加上 "is-active" 类（用于样式）和 aria-current（给读屏软件）；否则为空。
    $active = ($here === $file && $in_admin === $admin) ? ' class="is-active" aria-current="page"' : '';
    // EN: EFFECT: returns the finished <a> link with escaped URL and label. 中文: 效果：返回完整的 <a> 链接，网址和文字都已转义。
    return '<a' . $active . ' href="' . e(url(($admin ? 'admin/' : '') . $file)) . '">' . e($label) . '</a>';
}
// EN: end PHP; the rest is HTML. 中文: 结束 PHP，后面是 HTML。
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php // EN: UTF-8 so Chinese/special characters show correctly. | 中文: 使用 UTF-8，中文和特殊字符才能正常显示。 ?>
    <meta charset="utf-8">
    <?php // EN: makes the page fit phone screens. | 中文: 让页面适配手机屏幕。 ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php // EN: browser tab title, e.g. "Books | SmartLib"; e() keeps it safe. | 中文: 浏览器标签标题，例如 "Books | SmartLib"；e() 保证安全。 ?>
    <title><?= e($page_title) ?> | SmartLib</title>
    <?php // EN: these two lines speed up loading Google Fonts by connecting early. | 中文: 这两行提前连接 Google Fonts 服务器，加快字体加载。 ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php // EN: loads the Newsreader and Public Sans fonts used by the design. | 中文: 加载设计用到的 Newsreader 和 Public Sans 字体。 ?>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,600;1,6..72,400&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <?php // EN: our own stylesheet; url() makes the path work from any folder. | 中文: 我们自己的样式表；url() 让路径在任何文件夹都有效。 ?>
    <link rel="stylesheet" href="<?= e(url('css/style.css')) ?>">
</head>
<body>
<?php // EN: accessibility: lets keyboard users jump past the menu. | 中文: 无障碍：让键盘用户跳过菜单直接到正文。 ?>
<a class="skip-link" href="#main">Skip to content</a>
<?php // EN: the site header bar. | 中文: 网站顶部栏。 ?>
<header class="site-header">
    <div class="wrap header-row">
        <?php // EN: logo/brand link to the home page. | 中文: 品牌/Logo，链接到首页。 ?>
        <a class="brand" href="<?= e(url('index.php')) ?>">SmartLib</a>
        <?php // EN: main navigation menu. | 中文: 主导航菜单。 ?>
        <nav class="main-nav" aria-label="Main">
            <?php // EN: Home and Books are shown to everyone. | 中文: Home 和 Books 对所有人显示。 ?>
            <?= nav_item('index.php', 'Home') ?>
            <?= nav_item('books.php', 'Books') ?>
            <?php // EN: students see "My borrowings". | 中文: 学生能看到 "My borrowings"。 ?>
            <?php if ($role === 'student'): ?>
                <?= nav_item('my-borrowings.php', 'My borrowings') ?>
            <?php // EN: staff see a link into the admin area (true = in the admin folder). | 中文: 员工能看到进入后台的链接（true 表示在 admin 文件夹里）。 ?>
            <?php elseif (is_staff()): ?>
                <?= nav_item('dashboard.php', 'Staff area', true) ?>
            <?php endif; ?>
        </nav>
        <?php // EN: right side: login state area. | 中文: 右侧：登录状态区域。 ?>
        <div class="header-actions">
            <?php // EN: logged in: show name and role. | 中文: 已登录：显示姓名和角色。 ?>
            <?php if (is_logged_in()): ?>
                <span class="user-chip"><?= e($_SESSION['name']) ?> <small class="role-<?= e($role) ?>"><?= e(ucfirst($role)) ?></small></span>
                <?php // EN: WHY: logout is a POST form (not a link) so other sites can't log you out by an image/link; csrf_field() adds the token. | 中文: 为什么：登出用 POST 表单而不是链接，别的网站就不能用图片/链接把你登出；csrf_field() 加上令牌。 ?>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost" type="submit">Log out</button>
                </form>
            <?php // EN: not logged in: show Log in and Create account. | 中文: 未登录：显示登录和注册按钮。 ?>
            <?php else: ?>
                <a class="link-plain" href="<?= e(url('login.php')) ?>">Log in</a>
                <a class="btn btn-brass" href="<?= e(url('register.php')) ?>">Create account</a>
            <?php endif; ?>
        </div>
    </div>
    <?php // EN: second menu bar only for staff while they are inside /admin/. | 中文: 只有员工在 /admin/ 里时才显示的第二层菜单。 ?>
    <?php if ($in_admin && is_staff()): ?>
        <div class="staff-bar">
            <nav class="wrap" aria-label="Staff">
                <?php // EN: shared staff links (all pass true = admin folder). | 中文: 员工共用的链接（都传 true，表示 admin 文件夹）。 ?>
                <?= nav_item('dashboard.php', 'Dashboard', true) ?>
                <?= nav_item('books.php', 'Manage books', true) ?>
                <?= nav_item('categories.php', 'Categories', true) ?>
                <?= nav_item('authors.php', 'Authors', true) ?>
                <?= nav_item('borrowings.php', 'Borrowings', true) ?>
                <?= nav_item('overdue.php', 'Overdue', true) ?>
                <?php // EN: only librarians process returns (see the roles table). | 中文: 只有图书管理员能处理还书（见权限表）。 ?>
                <?php if ($role === 'librarian'): ?><?= nav_item('return.php', 'Return books', true) ?><?php endif; ?>
                <?php // EN: only admins manage users. | 中文: 只有管理员能管理用户。 ?>
                <?php if ($role === 'admin'): ?><?= nav_item('users.php', 'Users', true) ?><?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</header>
<?php // EN: main content starts here; id="main" is the target of the skip link. | 中文: 正文从这里开始；id="main" 是跳转链接的目标。 ?>
<main id="main" class="wrap main-area">
<?php // EN: show each queued flash message once (success/error/info box). role="alert" makes screen readers announce errors. | 中文: 把排队的闪现消息各显示一次（成功/错误/提示框）。role="alert" 让读屏软件播报错误。 ?>
<?php foreach (flash_pull() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
