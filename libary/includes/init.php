<?php
// Loaded at the top of every page: session, database, helper functions.
// EN: WHY: every page needs the same setup, so it lives in one file. EFFECT: pages just "require" this file and get sessions, DB and helpers.
// 中文: 为什么：每个页面都需要同样的准备工作，所以放在一个文件里。效果：页面只要 require 这个文件，就有了 session、数据库和辅助函数。

// EN: WHY: dates (due dates, "overdue") depend on the time zone. EFFECT: PHP date functions use Malaysia time.
// 中文: 为什么：到期日、"逾期"都和时区有关。效果：PHP 的日期函数使用马来西亚时间。
date_default_timezone_set('Asia/Kuala_Lumpur');

// EN: WHY: calling session_start() twice causes a warning, so check first. EFFECT: only starts a session if none is active yet.
// 中文: 为什么：重复调用 session_start() 会警告，所以先检查。效果：只有还没开启 session 时才开启。
if (session_status() === PHP_SESSION_NONE) {
    // EN: EFFECT: creates/loads the visitor's session so $_SESSION can remember who is logged in.
    // 中文: 效果：创建/读取访客的 session，$_SESSION 才能记住谁登录了。
    session_start();
}

// EN: WHY: __DIR__ is this file's folder, so the path works wherever the project is. require_once = load db.php exactly once; stop if missing. EFFECT: $pdo becomes available.
// 中文: 为什么：__DIR__ 是当前文件所在文件夹，项目放哪里路径都正确。require_once = 只加载一次 db.php，缺失就报错停止。效果：$pdo 可以使用了。
require_once __DIR__ . '/db.php';

// URL prefix of the project folder, e.g. "/smartlib" (works in any sub-folder).
// EN: WHY: links must work whether the site is at /smartlib or somewhere else. The next lines work out that prefix.
// 中文: 为什么：不管网站放在 /smartlib 还是别的文件夹，链接都要能用。下面几行计算这个前缀。

// EN: WHY: DOCUMENT_ROOT is the web server's root folder (htdocs). realpath() cleans it into a real absolute path; "?:" falls back to '' if it fails. EFFECT: $docRoot = e.g. C:\xampp\htdocs.
// 中文: 为什么：DOCUMENT_ROOT 是网站根目录（htdocs）。realpath() 把它变成真实的绝对路径；"?:" 表示失败时用空字符串。效果：$docRoot 例如 C:\xampp\htdocs。
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
// EN: WHY: need the project's real folder. __DIR__.'/..' = one level up from includes. EFFECT: $appRoot = e.g. C:\xampp\htdocs\smartlib.
// 中文: 为什么：需要项目的真实文件夹。__DIR__.'/..' 表示 includes 的上一级。效果：$appRoot 例如 C:\xampp\htdocs\smartlib。
$appRoot = realpath(__DIR__ . '/..') ?: '';
// EN: default prefix is empty (project is at the site root). 中文: 默认前缀为空（项目就在网站根目录）。
$base = '';
// EN: WHY: only compute the prefix if both paths exist and the project is really inside the web root (stripos(...) === 0 means "starts with", ignoring case). 
// 中文: 为什么：只有两个路径都存在、且项目确实位于网站根目录里面时才计算前缀（stripos(...) === 0 表示"以…开头"，不区分大小写）。
if ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) {
    // EN: WHY: cut off the web-root part, leaving "\smartlib"; then turn Windows backslashes into URL slashes. EFFECT: $base = "/smartlib".
    // 中文: 为什么：去掉网站根目录部分，剩下 "\smartlib"；再把 Windows 的反斜杠换成 URL 用的斜杠。效果：$base = "/smartlib"。
    $base = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
}
// EN: WHY: a constant can't be changed later by accident; rtrim removes a trailing "/". EFFECT: BASE_URL is available everywhere, e.g. "/smartlib".
// 中文: 为什么：常量不会被不小心改掉；rtrim 去掉末尾的 "/"。效果：BASE_URL 在所有地方都能用，例如 "/smartlib"。
define('BASE_URL', rtrim($base, '/'));

// EN: WHY: business rules in one place; change the number here and the whole site follows. EFFECT: loans last 14 days.
// 中文: 为什么：业务规则集中放一处，改这个数字整个网站都跟着变。效果：借期为 14 天。
const LOAN_DAYS        = 14;  // how long a student can keep a book
// EN: EFFECT: a student can hold at most 3 books at once. 中文: 效果：每个学生最多同时借 3 本。
const MAX_ACTIVE_LOANS = 3;   // books a student can hold at the same time

// ---------- small helpers ----------
// EN: Section: small helper functions. 中文: 小节：小型辅助函数。

// EN: WHY: "e" = escape. Any text from users must be escaped before showing in HTML, otherwise someone can inject <script> (XSS). EFFECT: returns safe text.
// 中文: 为什么：e 是 escape（转义）的缩写。用户输入的文字在显示到 HTML 前必须转义，否则别人能注入 <script>（XSS 攻击）。效果：返回安全的文字。
function e($value): string
{
    // EN: (string) turns any value into text; htmlspecialchars turns < > & " ' into harmless codes; UTF-8 handles Chinese correctly.
    // 中文: (string) 把任何值转成文字；htmlspecialchars 把 < > & " ' 变成无害的代码；UTF-8 保证中文正常。
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// EN: WHY: build links that always include the project prefix. 中文: 为什么：生成的链接总是带上项目前缀。
function url(string $path = ''): string
{
    // EN: EFFECT: url('books.php') returns "/smartlib/books.php"; ltrim removes a leading "/" so we don't get "//".
    // 中文: 效果：url('books.php') 返回 "/smartlib/books.php"；ltrim 去掉开头的 "/"，避免出现 "//"。
    return BASE_URL . '/' . ltrim($path, '/');
}

// EN: WHY: after saving a form we send the visitor to another page. 中文: 为什么：表单处理完后要把访客送到另一个页面。
function redirect(string $path)
{
    // EN: EFFECT: tells the browser "go to this address now" (HTTP Location header).
    // 中文: 效果：告诉浏览器"马上去这个地址"（HTTP 的 Location 头）。
    header('Location: ' . url($path));
    // EN: WHY: without exit, PHP would keep running the rest of the page. EFFECT: stops the script right here.
    // 中文: 为什么：不 exit 的话 PHP 会继续执行后面的代码。效果：脚本在这里立刻结束。
    exit;
}

// EN: WHY: show dates in a friendly way. "?string" means the date may also be null. 中文: 为什么：把日期显示得好看。"?string" 表示日期也可能是 null。
function fmt_date(?string $date): string
{
    // EN: EFFECT: "2026-10-14" becomes "14 Oct 2026"; if there is no date, show "-".
    // 中文: 效果："2026-10-14" 变成 "14 Oct 2026"；没有日期就显示 "-"。
    return $date ? date('j M Y', strtotime($date)) : '-';
}

// EN: WHY: "flash" messages survive one redirect (e.g. "Book added"). 中文: 为什么："闪现消息"能在一次跳转后显示出来（例如"书已添加"）。
function flash(string $type, string $message): void
{
    // EN: EFFECT: [] appends a new message (type = success/error/info) to the session list.
    // 中文: 效果：[] 把新消息（类型 success/error/info）追加到 session 的列表里。
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

// EN: WHY: read the messages once, then forget them so they don't show again. 中文: 为什么：消息只读一次，然后清掉，避免重复显示。
function flash_pull(): array
{
    // EN: EFFECT: take the stored messages, or an empty array if none. 中文: 效果：取出已存的消息，没有就用空数组。
    $messages = $_SESSION['flash'] ?? [];
    // EN: EFFECT: delete them from the session. 中文: 效果：把它们从 session 中删除。
    unset($_SESSION['flash']);
    // EN: EFFECT: give the messages to the caller (header.php). 中文: 效果：把消息交给调用者（header.php）。
    return $messages;
}

// ---------- login / roles ----------
// EN: Section: login and roles. 中文: 小节：登录与角色。

// EN: WHY: one clear yes/no question used all over the site. 中文: 为什么：整个网站都用到的"是否已登录"判断。
function is_logged_in(): bool
{
    // EN: EFFECT: true if the session holds a user id (set at login). 中文: 效果：session 里有用户 id（登录时设置）就返回 true。
    return !empty($_SESSION['user_id']);
}

// EN: WHY: know who is visiting. 中文: 为什么：要知道访问者是谁。
function current_role(): string
{
    // EN: EFFECT: returns admin/librarian/student, or "guest" if nobody is logged in. 中文: 效果：返回 admin/librarian/student，没登录就是 "guest"。
    return $_SESSION['role'] ?? 'guest';
}

// EN: WHY: admin and librarian share the same staff pages. 中文: 为什么：管理员和图书管理员共用员工页面。
function is_staff(): bool
{
    // EN: EFFECT: true if the role is in the list. "true" (strict) stops odd type matches. 中文: 效果：角色在列表里就返回 true；最后的 true 表示严格比较，避免类型误判。
    return in_array(current_role(), ['admin', 'librarian'], true);
}

// Where each role lands after logging in.
// EN: WHY: different roles need different home pages. 中文: 为什么：不同角色需要不同的首页。
function home_for_role(string $role): string
{
    // EN: EFFECT: staff go to the dashboard; everyone else goes to the book list. 中文: 效果：员工去仪表盘；其他人去图书列表。
    return in_array($role, ['admin', 'librarian'], true) ? 'admin/dashboard.php' : 'books.php';
}

// Current page relative to the project folder, e.g. "book.php?id=3".
// EN: WHY: after login we want to come back to the page the visitor wanted. 中文: 为什么：登录后要回到访客原本想看的页面。
function current_path(): string
{
    // EN: REQUEST_URI is the address in the browser, e.g. "/smartlib/book.php?id=3". 中文: REQUEST_URI 是浏览器地址，例如 "/smartlib/book.php?id=3"。
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    // EN: EFFECT: cut off the project prefix and the leading "/", giving "book.php?id=3". 中文: 效果：去掉项目前缀和开头的 "/"，得到 "book.php?id=3"。
    return ltrim(substr($uri, strlen(BASE_URL)), '/');
}

// Only allow redirects to pages inside this project.
// EN: WHY: the "next" value comes from the URL, so attackers could set it to an evil website (open redirect). 中文: 为什么："next" 来自网址，攻击者可能把它改成恶意网站（开放重定向）。
function safe_next(?string $next): string
{
    // EN: make sure it is text. 中文: 确保它是文字。
    $next = (string) $next;
    // EN: WHY: the regex only allows "page.php" or "admin/page.php" with a simple query string. EFFECT: anything else (like http://evil.com) is rejected.
    // 中文: 为什么：正则只允许 "page.php" 或 "admin/page.php"，后面可带简单的查询参数。效果：其他内容（如 http://evil.com）都会被拒绝。
    if ($next !== '' && preg_match('#^(admin/)?[a-z0-9_\-]+\.php(\?[a-z0-9_=&\-]*)?$#i', $next)) {
        // EN: valid, so return it. 中文: 合格，原样返回。
        return $next;
    }
    // EN: EFFECT: invalid values become empty, so the caller uses the default page. 中文: 效果：不合格就返回空字符串，调用者会改用默认页面。
    return '';
}

// EN: WHY: protect pages that need a login. 中文: 为什么：保护需要登录才能看的页面。
function require_login(): void
{
    // EN: if nobody is logged in... 中文: 如果没有人登录……
    if (!is_logged_in()) {
        // EN: EFFECT: queue a message to show on the login page. 中文: 效果：排一条消息，在登录页显示。
        flash('error', 'Log in to continue.');
        // EN: EFFECT: send them to login, remembering the page they wanted (urlencode makes it URL-safe). 中文: 效果：送去登录页，并记住他们想看的页面（urlencode 让它适合放进网址）。
        redirect('login.php?next=' . urlencode(current_path()));
    }
}

// EN: WHY: protect pages by role (e.g. only admin). 中文: 为什么：按角色保护页面（例如只有 admin）。
function require_role(array $roles): void
{
    // EN: first they must be logged in. 中文: 首先必须登录。
    require_login();
    // EN: if their role is not in the allowed list... 中文: 如果他们的角色不在允许列表里……
    if (!in_array(current_role(), $roles, true)) {
        // EN: EFFECT: show the 403 "no access" page and stop. 中文: 效果：显示 403 "无权限"页面并终止。
        forbidden();
    }
}

// EN: WHY: a friendly "no access" page. 中文: 为什么：给出友好的"无权限"页面。
function forbidden(): void
{
    // EN: 403 = "Forbidden" status code. 中文: 403 表示"禁止访问"状态码。
    http_response_code(403);
    // EN: WHY: header.php is included inside this function, so it needs access to the global $pdo. 中文: 为什么：header.php 是在这个函数里被引入的，所以要用到全局的 $pdo。
    global $pdo;
    // EN: the title shown in the browser tab. 中文: 浏览器标签页上显示的标题。
    $page_title = 'No access';
    // EN: EFFECT: print the top of the page (menu etc.). 中文: 效果：输出页面顶部（菜单等）。
    require __DIR__ . '/header.php';
    // EN: EFFECT: print the message; e() escapes the role and URL for safety. The "." joins text pieces. 中文: 效果：输出提示；e() 对角色和网址做转义保证安全；"." 用来拼接字符串。
    echo '<div class="notice-page"><h1>You don\'t have access to this page</h1>'
       . '<p>Your account (' . e(current_role()) . ') can\'t open it. Go back to the <a href="' . e(url(home_for_role(current_role()))) . '">start page</a>.</p></div>';
    // EN: EFFECT: print the bottom of the page. 中文: 效果：输出页面底部。
    require __DIR__ . '/footer.php';
    // EN: stop so no protected content is shown. 中文: 终止，避免显示受保护的内容。
    exit;
}

// ---------- CSRF protection for every POST form ----------
// EN: Section: CSRF = a fake form on another site tricking a logged-in user into submitting. A secret token blocks it. 中文: 小节：CSRF 是别的网站伪造表单、骗已登录用户提交。用一个秘密令牌就能挡住。

// EN: WHY: one secret token per visitor. 中文: 为什么：每个访客一个秘密令牌。
function csrf_token(): string
{
    // EN: if there is no token yet... 中文: 如果还没有令牌……
    if (empty($_SESSION['csrf'])) {
        // EN: EFFECT: random_bytes makes 32 unguessable bytes; bin2hex turns them into 64 readable characters; saved in the session. 中文: 效果：random_bytes 生成 32 个猜不到的字节；bin2hex 转成 64 个可读字符；存进 session。
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    // EN: return the token. 中文: 返回令牌。
    return $_SESSION['csrf'];
}

// EN: WHY: every form must carry the token. 中文: 为什么：每个表单都要带上令牌。
function csrf_field(): string
{
    // EN: EFFECT: outputs a hidden <input>, so the token is sent with the form but not shown. 中文: 效果：输出一个隐藏的 <input>，令牌随表单提交但用户看不到。
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

// EN: WHY: check the token when a form is submitted. 中文: 为什么：表单提交时要检查令牌。
function csrf_verify(): void
{
    // EN: the token that came with the form. 中文: 表单带来的令牌。
    $sent     = $_POST['csrf'] ?? '';
    // EN: the token we stored in the session. 中文: 我们存在 session 里的令牌。
    $expected = $_SESSION['csrf'] ?? '';
    // EN: WHY: hash_equals compares in constant time (safer than ===). EFFECT: fails if token is missing, not text, or different. 
    // 中文: 为什么：hash_equals 用固定时间比较（比 === 更安全）。效果：令牌缺失、不是文字或不一致都会失败。
    if ($expected === '' || !is_string($sent) || !hash_equals($expected, $sent)) {
        // EN: 400 = "Bad Request". 中文: 400 表示"错误的请求"。
        http_response_code(400);
        // EN: EFFECT: show a hint and stop; nothing gets saved. 中文: 效果：显示提示并终止；不会保存任何数据。
        exit('This form expired. Go back, refresh the page and try again.');
    }
}

// EN: WHY: loaded last because components.php uses e() and url() defined above. EFFECT: book_card(), loan_badge(), pagination() become available.
// 中文: 为什么：放在最后，因为 components.php 要用到上面定义的 e() 和 url()。效果：book_card()、loan_badge()、pagination() 可以使用了。
require_once __DIR__ . '/components.php';
