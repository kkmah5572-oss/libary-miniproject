<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/includes/init.php';

// EN: WHY: the book id comes from the URL (book.php?id=3); (int) makes it a safe number. 中文: 为什么：书的 id 来自网址（book.php?id=3）；(int) 把它变成安全的数字。
$id = (int) ($_GET['id'] ?? 0);
// EN: get one book plus its author and category names. "?" = safe placeholder for the id. 中文: 取一本书，并带上作者名和分类名。"?" 是 id 的安全占位符。
$stmt = $pdo->prepare("SELECT b.*, a.name AS author_name, c.name AS category_name
                       FROM books b JOIN authors a ON a.id = b.author_id JOIN categories c ON c.id = b.category_id
                       WHERE b.id = ?");
$stmt->execute([$id]);
// EN: EFFECT: $book = the row, or false if the id doesn't exist. 中文: 效果：$book 是查到的行；id 不存在则为 false。
$book = $stmt->fetch();

// EN: book not found. 中文: 没找到这本书。
if (!$book) {
    // EN: 404 = "Not Found". 中文: 404 表示"未找到"。
    http_response_code(404);
    $page_title = 'Book not found';
    require __DIR__ . '/includes/header.php';
    // EN: print a friendly message with a way back. 中文: 输出友好提示，并提供返回链接。
    echo '<div class="notice-page"><h1>Book not found</h1><p><a href="' . e(url('books.php')) . '">Back to all books</a></p></div>';
    require __DIR__ . '/includes/footer.php';
    // EN: stop so the rest of the page doesn't run on missing data. 中文: 终止，避免后面的代码在缺失数据上运行。
    exit;
}

// Student state: already holding this book? how many loans in total?
// EN: WHY: the Borrow button depends on the student's situation. 中文: 为什么：借书按钮取决于学生当前的情况。
$myLoan = null;
$activeCount = 0;
// EN: only students borrow. 中文: 只有学生可以借书。
if (current_role() === 'student') {
    // EN: does this student currently hold THIS book? 中文: 这个学生现在是否正借着这本书？
    $stmt = $pdo->prepare("SELECT due_date FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
    $stmt->execute([$_SESSION['user_id'], $id]);
    $myLoan = $stmt->fetch();

    // EN: how many books does this student hold in total? 中文: 这个学生一共借着几本书？
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status = 'borrowed'");
    $stmt->execute([$_SESSION['user_id']]);
    $activeCount = (int) $stmt->fetchColumn();
}

// EN: colour class from the category id (loops through 8 colours). 中文: 根据分类 id 得到颜色类名（在 8 种颜色中循环）。
$cat = 'cat-' . ((($book['category_id'] - 1) % 8) + 1);
// EN: copies on the shelf now. 中文: 现在书架上的册数。
$available = (int) $book['available_copies'];

// EN: the tab title is the book's title. 中文: 标签页标题就是书名。
$page_title = $book['title'];
require __DIR__ . '/includes/header.php';
?>
<p><a href="<?= e(url('books.php')) ?>">&larr; All books</a></p>
<div class="detail">
    <article class="detail-card <?= $cat ?>">
        <div class="inner">
            <h1><?= e($book['title']) ?></h1>
            <p class="muted">by <?= e($book['author_name']) ?></p>
            <?php // EN: e() makes it safe; nl2br turns line breaks into <br>; "?:" shows a fallback when the description is empty. | 中文: e() 保证安全；nl2br 把换行变成 <br>；"?:" 在简介为空时显示备用文字。 ?>
            <p><?= nl2br(e($book['description'] ?: 'No description yet.')) ?></p>
            <?php // EN: <dl> = a list of label/value pairs (dt = label, dd = value). | 中文: <dl> 是"名称/值"列表（dt 是名称，dd 是值）。 ?>
            <dl class="facts">
                <dt>Category</dt><dd><a href="<?= e(url('books.php?category=' . (int) $book['category_id'])) ?>"><?= e($book['category_name']) ?></a></dd>
                <?php // EN: urlencode makes the author's name safe inside a URL. | 中文: urlencode 让作者名能安全地放进网址。 ?>
                <dt>Author</dt><dd><a href="<?= e(url('books.php?q=' . urlencode($book['author_name']))) ?>"><?= e($book['author_name']) ?></a></dd>
                <dt>Year</dt><dd><?= $book['year_published'] ? (int) $book['year_published'] : '-' ?></dd>
                <dt>Total copies</dt><dd><?= (int) $book['total_copies'] ?></dd>
            </dl>
        </div>
    </article>

    <?php // EN: the side panel with availability and the borrow action. | 中文: 侧边栏，显示库存和借书操作。 ?>
    <aside class="side-panel">
        <div class="copies"><?= $available ?></div>
        <?php // EN: "copy" for 1, "copies" otherwise. | 中文: 数量为 1 用 "copy"，否则用 "copies"。 ?>
        <p class="muted"><?= $available === 1 ? 'copy' : 'copies' ?> available now</p>

        <?php // EN: case 1: guest — ask them to log in. | 中文: 情况 1：访客——请他们登录。 ?>
        <?php if (!is_logged_in()): ?>
            <p>Log in with a student account to borrow this book.</p>
            <?php // EN: next=... brings them back to this book after login. | 中文: next=... 让他们登录后回到这本书。 ?>
            <a class="btn btn-primary btn-block" href="<?= e(url('login.php?next=' . urlencode('book.php?id=' . $id))) ?>">Log in to borrow</a>
            <p class="form-switch">No account? <a href="<?= e(url('register.php')) ?>">Create one</a></p>

        <?php // EN: case 2: student — several sub-cases. | 中文: 情况 2：学生——又分几种子情况。 ?>
        <?php elseif (current_role() === 'student'): ?>
            <?php // EN: already holding this book. | 中文: 已经借了这本书。 ?>
            <?php if ($myLoan): ?>
                <div class="alert alert-info">You have this book. Return it by <?= e(fmt_date($myLoan['due_date'])) ?>.</div>
                <a class="btn btn-outline btn-block" href="<?= e(url('my-borrowings.php')) ?>">My borrowings</a>
            <?php // EN: no copies left. | 中文: 没有剩余册数。 ?>
            <?php elseif ($available < 1): ?>
                <div class="alert alert-error">All copies are out. Check back soon.</div>
            <?php // EN: reached the maximum number of loans. | 中文: 已达到最大借阅数量。 ?>
            <?php elseif ($activeCount >= MAX_ACTIVE_LOANS): ?>
                <div class="alert alert-error">You already hold <?= MAX_ACTIVE_LOANS ?> books. Return one to borrow another.</div>
            <?php // EN: everything OK: show the borrow button. | 中文: 一切正常：显示借书按钮。 ?>
            <?php else: ?>
                <?php // EN: POST to borrow.php, which does the real checks again on the server. | 中文: 以 POST 提交给 borrow.php，服务器会再做一次真正的检查。 ?>
                <form method="post" action="<?= e(url('borrow.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="book_id" value="<?= $id ?>">
                    <button class="btn btn-primary btn-block" type="submit">Borrow for <?= LOAN_DAYS ?> days</button>
                </form>
            <?php endif; ?>

        <?php // EN: case 3: staff — they manage books, not borrow. | 中文: 情况 3：员工——他们管理书籍，不借书。 ?>
        <?php else: ?>
            <p class="muted">Staff accounts manage books but don't borrow them.</p>
            <a class="btn btn-outline btn-block" href="<?= e(url('admin/book-form.php?id=' . $id)) ?>">Edit this book</a>
        <?php endif; ?>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
