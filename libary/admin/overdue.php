<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/../includes/init.php';
// EN: staff only. 中文: 仅员工可访问。
require_role(['admin', 'librarian']);

// EN: WHY: overdue = still "borrowed" AND due date is before today. DATEDIFF(today, due) = how many days late. ORDER BY due_date ASC puts the most overdue first. EFFECT: $rows = every overdue loan with student and book info.
// 中文: 为什么：逾期 = 状态仍是 "borrowed" 且到期日早于今天。DATEDIFF(今天, 到期日) = 逾期天数。ORDER BY due_date ASC 让逾期最久的排最前。效果：$rows = 所有逾期借阅，带学生和书的信息。
$rows = $pdo->query("SELECT br.id, br.borrow_date, br.due_date, u.name AS student, u.email, b.id AS book_id, b.title,
                            DATEDIFF(CURDATE(), br.due_date) AS days_late
                     FROM borrowings br JOIN users u ON u.id = br.user_id JOIN books b ON b.id = br.book_id
                     WHERE br.status = 'borrowed' AND br.due_date < CURDATE()
                     ORDER BY br.due_date ASC")->fetchAll();

$page_title = 'Overdue books';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Overdue books</h1>
    <?php // EN: only librarians can process returns, so only they see this shortcut. | 中文: 只有图书管理员能处理还书，所以只有他们看到这个快捷入口。 ?>
    <?php if (current_role() === 'librarian'): ?><a class="btn btn-outline" href="<?= e(url('admin/return.php')) ?>">Process returns</a><?php endif; ?>
</div>

<?php // EN: nothing overdue: show a positive message. | 中文: 没有逾期：显示一条正面的提示。 ?>
<?php if (!$rows): ?>
    <div class="empty"><p>Nothing is overdue. Well done.</p></div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Student</th><th>Book</th><th>Borrowed</th><th>Was due</th><th>Days late</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <?php // EN: every row here is late, so it always gets the red "row-late" style. | 中文: 这里每一行都是逾期的，所以总是使用红色的 "row-late" 样式。 ?>
        <tr class="row-late">
            <td><?= e($r['student']) ?><br><span class="muted"><?= e($r['email']) ?></span></td>
            <td><a href="<?= e(url('book.php?id=' . (int) $r['book_id'])) ?>"><?= e($r['title']) ?></a></td>
            <td><?= e(fmt_date($r['borrow_date'])) ?></td>
            <td><?= e(fmt_date($r['due_date'])) ?></td>
            <?php // EN: "1 day" vs "2 days". | 中文: "1 day" 和 "2 days" 的单复数。 ?>
            <td><span class="chip chip-out"><?= (int) $r['days_late'] ?> day<?= (int) $r['days_late'] === 1 ? '' : 's' ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
