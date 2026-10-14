<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/../includes/init.php';
// EN: only admin and librarian may see the dashboard. 中文: 只有 admin 和 librarian 能看仪表盘。
require_role(['admin', 'librarian']);

// EN: WHY: one query returns all five summary numbers (each sub-SELECT is one number). COALESCE(SUM(...),0) gives 0 instead of NULL when there are no books. EFFECT: $stats = one row with titles, copies, borrowed, students, overdue. 
// 中文: 为什么：一条查询返回五个统计数字（每个子查询是一个数字）。COALESCE(SUM(...),0) 在没有书时返回 0 而不是 NULL。效果：$stats 是一行数据，包含 titles、copies、borrowed、students、overdue。
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM books)                                              AS titles,
    (SELECT COALESCE(SUM(total_copies),0) FROM books)                         AS copies,
    (SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed')               AS borrowed,
    (SELECT COUNT(*) FROM users WHERE role = 'student')                       AS students,
    (SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed' AND due_date < CURDATE()) AS overdue
")->fetch();

// EN: EFFECT: the 6 latest loans with student name and book title (JOIN pulls names from other tables). 中文: 效果：最新的 6 条借阅，带学生姓名和书名（JOIN 从其他表取名字）。
$recent = $pdo->query("SELECT br.id, br.borrow_date, br.due_date, br.status, u.name AS student, b.title,
                              (br.status = 'borrowed' AND br.due_date < CURDATE()) AS is_overdue
                       FROM borrowings br JOIN users u ON u.id = br.user_id JOIN books b ON b.id = br.book_id
                       ORDER BY br.borrow_date DESC, br.id DESC LIMIT 6")->fetchAll();

// EN: EFFECT: the 5 most borrowed books: COUNT(br.id) counts loans per book, GROUP BY groups by book, ORDER BY loans DESC puts the biggest first. 中文: 效果：借阅最多的 5 本书：COUNT(br.id) 统计每本书的借阅次数，GROUP BY 按书分组，ORDER BY loans DESC 让最多的排最前。
$popular = $pdo->query("SELECT b.id, b.title, COUNT(br.id) AS loans
                        FROM books b JOIN borrowings br ON br.book_id = b.id
                        GROUP BY b.id, b.title ORDER BY loans DESC, b.title LIMIT 5")->fetchAll();

$page_title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Dashboard</h1></div>

<?php // EN: four statistic boxes; each is a link to the related page. | 中文: 四个统计方块；每个都链接到相关页面。 ?>
<div class="stats">
    <a class="stat" href="<?= e(url('admin/books.php')) ?>"><b><?= (int) $stats['titles'] ?></b><span>Book titles (<?= (int) $stats['copies'] ?> copies)</span></a>
    <a class="stat stat-gold" href="<?= e(url('admin/borrowings.php?status=borrowed')) ?>"><b><?= (int) $stats['borrowed'] ?></b><span>Books on loan</span></a>
    <?php // EN: only admins may open the Users page, so librarians see a plain box without a link. | 中文: 只有 admin 能打开用户页，所以图书管理员看到的是没有链接的普通方块。 ?>
    <?php if (current_role() === 'admin'): ?>
        <a class="stat stat-blue" href="<?= e(url('admin/users.php?role=student')) ?>"><b><?= (int) $stats['students'] ?></b><span>Students</span></a>
    <?php else: ?>
        <div class="stat stat-blue"><b><?= (int) $stats['students'] ?></b><span>Students</span></div>
    <?php endif; ?>
    <a class="stat stat-warn" href="<?= e(url('admin/overdue.php')) ?>"><b><?= (int) $stats['overdue'] ?></b><span>Overdue books</span></a>
</div>

<?php // EN: quick action buttons, shown according to the role. | 中文: 快捷操作按钮，按角色显示。 ?>
<div class="quick">
    <a class="btn btn-primary" href="<?= e(url('admin/book-form.php')) ?>">Add a book</a>
    <?php // EN: only librarians update borrowings (return books). | 中文: 只有图书管理员能更新借阅（处理还书）。 ?>
    <?php if (current_role() === 'librarian'): ?>
        <a class="btn btn-outline" href="<?= e(url('admin/return.php')) ?>">Process a return</a>
    <?php endif; ?>
    <a class="btn btn-outline" href="<?= e(url('admin/overdue.php')) ?>">View overdue books</a>
    <?php // EN: only admins manage users. | 中文: 只有 admin 能管理用户。 ?>
    <?php if (current_role() === 'admin'): ?>
        <a class="btn btn-outline" href="<?= e(url('admin/user-form.php')) ?>">Add a user</a>
    <?php endif; ?>
</div>

<?php // EN: two columns: latest borrowings and most borrowed. | 中文: 两列：最新借阅和最常借阅。 ?>
<div class="two-col">
    <section>
        <h2>Latest borrowings</h2>
        <?php if (!$recent): ?><div class="empty">No borrowings yet.</div><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Student</th><th>Book</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <?php // EN: white-space:nowrap keeps the date on one line. | 中文: white-space:nowrap 让日期保持在一行。 ?>
                <tr><td><?= e($r['student']) ?></td><td><?= e($r['title']) ?></td><td style="white-space:nowrap"><?= e(fmt_date($r['due_date'])) ?></td><td><?= loan_badge($r) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
    <section>
        <h2>Most borrowed</h2>
        <?php if (!$popular): ?><div class="empty">No data yet.</div><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Book</th><th>Loans</th></tr></thead>
            <tbody>
            <?php foreach ($popular as $p): ?>
                <tr><td><a href="<?= e(url('book.php?id=' . (int) $p['id'])) ?>"><?= e($p['title']) ?></a></td><td><?= (int) $p['loans'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
