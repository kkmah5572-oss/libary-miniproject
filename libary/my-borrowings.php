<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/includes/init.php';
// EN: only students have personal loans. 中文: 只有学生有个人借阅记录。
require_role(['student']);

// EN: WHY: one query gets every loan of this student with book/author info. 
// br.* = all loan columns; "is_overdue" is a calculated true/false; DATEDIFF = days between due date and today (negative = late). ORDER BY: current loans first, then newest first.
// 中文: 为什么：一条查询取出这个学生的所有借阅，并带上书和作者信息。
// br.* = 借阅表所有列；"is_overdue" 是算出来的真/假；DATEDIFF = 到期日与今天相差的天数（负数表示已逾期）。排序：先显示在借的，再按最新在前。
$stmt = $pdo->prepare("SELECT br.*, b.title, b.id AS book_id, a.name AS author_name,
                              (br.status = 'borrowed' AND br.due_date < CURDATE()) AS is_overdue,
                              DATEDIFF(br.due_date, CURDATE()) AS days_left
                       FROM borrowings br
                       JOIN books b ON b.id = br.book_id
                       JOIN authors a ON a.id = b.author_id
                       WHERE br.user_id = ?
                       ORDER BY br.status = 'borrowed' DESC, br.borrow_date DESC, br.id DESC");
// EN: user id from the session (trusted). 中文: 用户 id 来自 session（可信）。
$stmt->execute([$_SESSION['user_id']]);
$rows = $stmt->fetchAll();

// EN: WHY: split one list into two tables. array_filter keeps only rows where the arrow function returns true. 中文: 为什么：把一份列表分成两张表。array_filter 只保留箭头函数返回 true 的行。
$current = array_filter($rows, fn($r) => $r['status'] === 'borrowed');
$history = array_filter($rows, fn($r) => $r['status'] === 'returned');

$page_title = 'My borrowings';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>My borrowings</h1>
    <a class="btn btn-outline" href="<?= e(url('books.php')) ?>">Find more books</a>
</div>

<?php // EN: count() shows how many are on loan vs the maximum. | 中文: count() 显示在借数量和上限。 ?>
<h2>On loan now (<?= count($current) ?> of <?= MAX_ACTIVE_LOANS ?>)</h2>
<?php if (!$current): ?>
    <div class="empty"><p>You have no books on loan.</p><a href="<?= e(url('books.php')) ?>">Browse the catalogue</a></div>
<?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Book</th><th>Borrowed</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($current as $r): ?>
            <?php // EN: overdue rows get the "row-late" class (red background in CSS). | 中文: 逾期的行加上 "row-late" 类（CSS 里是红色背景）。 ?>
            <tr class="<?= $r['is_overdue'] ? 'row-late' : '' ?>">
                <td><a href="<?= e(url('book.php?id=' . (int) $r['book_id'])) ?>"><strong><?= e($r['title']) ?></strong></a><br><span class="muted"><?= e($r['author_name']) ?></span></td>
                <td><?= e(fmt_date($r['borrow_date'])) ?></td>
                <td><?= e(fmt_date($r['due_date'])) ?>
                    <?php // EN: overdue → "N day(s) late" (abs makes -3 into 3); today → "due today"; otherwise "N day(s) left". | 中文: 逾期 → "N day(s) late"（abs 把 -3 变成 3）；今天到期 → "due today"；否则 "N day(s) left"。 ?>
                    <br><span class="muted"><?= $r['is_overdue'] ? abs((int) $r['days_left']) . ' day(s) late' : ((int) $r['days_left'] === 0 ? 'due today' : (int) $r['days_left'] . ' day(s) left') ?></span></td>
                <?php // EN: coloured status label from components.php. | 中文: 来自 components.php 的彩色状态标签。 ?>
                <td><?= loan_badge($r) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="muted" style="margin-top:.75rem">Return books to a librarian at the desk. They will mark them as returned.</p>
<?php endif; ?>

<section class="section">
    <h2>Past borrowings</h2>
    <?php if (!$history): ?>
        <div class="empty">Nothing returned yet.</div>
    <?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Book</th><th>Borrowed</th><th>Returned</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($history as $r): ?>
                <tr>
                    <td><a href="<?= e(url('book.php?id=' . (int) $r['book_id'])) ?>"><?= e($r['title']) ?></a></td>
                    <td><?= e(fmt_date($r['borrow_date'])) ?></td>
                    <td><?= e(fmt_date($r['return_date'])) ?></td>
                    <td><?= loan_badge($r) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
