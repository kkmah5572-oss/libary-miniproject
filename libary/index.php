<?php
// EN: WHY: loads session, database, helpers. EFFECT: $pdo, e(), url() etc. become available on this page. 中文: 为什么：加载 session、数据库和辅助函数。效果：本页可以使用 $pdo、e()、url() 等。
require __DIR__ . '/includes/init.php';

// EN: WHY: the home page shows "Browse by category" tiles with book counts. 中文: 为什么：首页要显示带书数的"按分类浏览"方块。
// EN: LEFT JOIN keeps categories with 0 books; COUNT counts books per category; GROUP BY groups; ORDER BY id keeps a stable order. EFFECT: $categories = list of categories + book_count. 
// 中文: LEFT JOIN 让 0 本书的分类也显示；COUNT 统计每个分类的书数；GROUP BY 分组；ORDER BY id 保持顺序稳定。效果：$categories = 分类列表加 book_count。
$categories = $pdo->query("SELECT c.id, c.name, COUNT(b.id) AS book_count
                           FROM categories c LEFT JOIN books b ON b.category_id = c.id
                           GROUP BY c.id, c.name ORDER BY c.id")->fetchAll();

// EN: WHY: the same SELECT/JOIN is needed twice, so it's stored in a variable. JOIN adds the author and category names to each book. 
// 中文: 为什么：同一段 SELECT/JOIN 要用两次，所以存进变量。JOIN 给每本书加上作者名和分类名。
$bookSql = "SELECT b.*, a.name AS author_name, c.name AS category_name
            FROM books b JOIN authors a ON a.id = b.author_id JOIN categories c ON c.id = b.category_id";
// EN: EFFECT: the 3 newest books (highest id first) for the decorative card stack. 中文: 效果：最新的 3 本书（id 最大的在前），用于装饰性的卡片堆。
$stack  = $pdo->query("$bookSql ORDER BY b.id DESC LIMIT 3")->fetchAll();
// EN: EFFECT: the 6 newest books for the "Recently added" section. 中文: 效果：最新的 6 本书，用于 "Recently added" 区块。
$recent = $pdo->query("$bookSql ORDER BY b.id DESC LIMIT 6")->fetchAll();

// Student summary box
// EN: null means "no summary" (guests and staff don't get the box). 中文: null 表示"没有摘要"（访客和员工不显示这个框）。
$loanInfo = null;
// EN: only students have loans. 中文: 只有学生才有借阅。
if (current_role() === 'student') {
    // EN: WHY: one query gives all three numbers. COUNT(*) = active loans; SUM(due_date < CURDATE()) = how many are late (true counts as 1); MIN(due_date) = earliest due date. "?" is a safe placeholder for the user id.
    // 中文: 为什么：一条查询得到三个数字。COUNT(*) = 在借数量；SUM(due_date < CURDATE()) = 逾期数量（真值算 1）；MIN(due_date) = 最早到期日。"?" 是用户 id 的安全占位符。
    $stmt = $pdo->prepare("SELECT COUNT(*) AS active,
                                  SUM(due_date < CURDATE()) AS late,
                                  MIN(due_date) AS next_due
                           FROM borrowings WHERE user_id = ? AND status = 'borrowed'");
    // EN: EFFECT: run the query for this student. 中文: 效果：为这个学生执行查询。
    $stmt->execute([$_SESSION['user_id']]);
    // EN: EFFECT: fetch() returns the single result row. 中文: 效果：fetch() 返回唯一的结果行。
    $loanInfo = $stmt->fetch();
}

// EN: browser tab title. 中文: 浏览器标签标题。
$page_title = 'Home';
// EN: EFFECT: print the top of the page. 中文: 效果：输出页面顶部。
require __DIR__ . '/includes/header.php';
?>
<?php // EN: hero = the big intro area at the top. | 中文: hero 是顶部的大型介绍区。 ?>
<section class="hero">
    <div>
        <h1>Find a book, borrow it, bring it back on time.</h1>
        <p class="hero-lead">Search the whole catalogue by title, author or category. Students can borrow online and see what is due in one place.</p>
        <?php // EN: WHY: method="get" puts the search text in the URL (books.php?q=...), so results can be bookmarked. | 中文: 为什么：method="get" 把搜索词放进网址（books.php?q=...），结果页可以收藏。 ?>
        <form class="search-big" action="<?= e(url('books.php')) ?>" method="get" role="search">
            <?php // EN: name="q" is the key PHP reads as $_GET['q']. | 中文: name="q" 就是 PHP 里 $_GET['q'] 读取的键。 ?>
            <input type="search" name="q" placeholder="Title, author or category" aria-label="Search books">
            <button class="btn btn-primary" type="submit">Search</button>
        </form>
        <div class="hero-links">
            <?php // EN: guests: invite them to register or log in. | 中文: 访客：邀请他们注册或登录。 ?>
            <?php if (!is_logged_in()): ?>
                <a class="btn btn-brass" href="<?= e(url('register.php')) ?>">Create a free account</a>
                <a href="<?= e(url('login.php')) ?>">I already have an account</a>
            <?php // EN: staff: link to the dashboard. | 中文: 员工：链接到仪表盘。 ?>
            <?php elseif (is_staff()): ?>
                <a class="btn btn-brass" href="<?= e(url('admin/dashboard.php')) ?>">Open staff dashboard</a>
            <?php // EN: students: link to their loans. | 中文: 学生：链接到自己的借阅。 ?>
            <?php else: ?>
                <a class="btn btn-brass" href="<?= e(url('my-borrowings.php')) ?>">My borrowings</a>
            <?php endif; ?>
            <a href="<?= e(url('books.php')) ?>">Browse all books</a>
        </div>
    </div>
    <?php // EN: aria-hidden hides this decoration from screen readers. | 中文: aria-hidden 让读屏软件忽略这个装饰。 ?>
    <div class="card-stack" aria-hidden="true">
        <?php // EN: EFFECT: print one card per book using book_card() from components.php. | 中文: 效果：用 components.php 里的 book_card() 为每本书输出一张卡片。 ?>
        <?php foreach ($stack as $b) { echo book_card($b); } ?>
    </div>
</section>

<?php // EN: show the loan box only if $loanInfo has data (i.e. a student). | 中文: 只有 $loanInfo 有数据（即学生）时才显示借阅框。 ?>
<?php if ($loanInfo): ?>
    <section class="panel-loans">
        <h2>Hello, <?= e($_SESSION['name']) ?></h2>
        <?php // EN: no active loans. | 中文: 没有在借的书。 ?>
        <?php if ((int) $loanInfo['active'] === 0): ?>
            <p>You have no books on loan. You can borrow up to <?= MAX_ACTIVE_LOANS ?>.</p>
        <?php else: ?>
            <?php // EN: show the count and the next due date; fmt_date makes it readable. | 中文: 显示在借数量和最近到期日；fmt_date 让日期更易读。 ?>
            <p>You have <strong><?= (int) $loanInfo['active'] ?></strong> book(s) on loan. The next one is due on <strong><?= e(fmt_date($loanInfo['next_due'])) ?></strong>.
            <?php // EN: red label only if something is overdue. | 中文: 只有存在逾期时才显示红色标签。 ?>
            <?php if ((int) $loanInfo['late'] > 0): ?><span class="chip chip-out"><?= (int) $loanInfo['late'] ?> overdue</span><?php endif; ?></p>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= e(url('my-borrowings.php')) ?>">See my borrowings</a>
    </section>
<?php endif; ?>

<section class="section">
    <div class="section-head"><h2>Browse by category</h2></div>
    <div class="cat-tiles">
        <?php // EN: one coloured tile per category; "% 8" loops through the 8 CSS colours. | 中文: 每个分类一个彩色方块；"% 8" 在 8 种 CSS 颜色中循环。 ?>
        <?php foreach ($categories as $c): ?>
            <?php // EN: the link filters books.php by this category id. | 中文: 链接让 books.php 按这个分类 id 筛选。 ?>
            <a class="cat-tile cat-<?= (($c['id'] - 1) % 8) + 1 ?>" href="<?= e(url('books.php?category=' . (int) $c['id'])) ?>">
                <strong><?= e($c['name']) ?></strong>
                <?php // EN: "title" or "titles" depending on whether the count is 1. | 中文: 数量为 1 时用 "title"，否则用 "titles"。 ?>
                <span><?= (int) $c['book_count'] ?> title<?= (int) $c['book_count'] === 1 ? '' : 's' ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2>Recently added</h2>
        <a href="<?= e(url('books.php')) ?>">See all books</a>
    </div>
    <div class="book-grid">
        <?php // EN: print the 6 newest books as cards. | 中文: 把最新的 6 本书输出成卡片。 ?>
        <?php foreach ($recent as $b) { echo book_card($b); } ?>
    </div>
</section>
<?php // EN: EFFECT: print the bottom of the page. | 中文: 效果：输出页面底部。 ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
