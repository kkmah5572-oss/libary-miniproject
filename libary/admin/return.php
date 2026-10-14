<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/../includes/init.php';
// EN: WHY: the roles table says only librarians update borrowings. EFFECT: admins and others get 403. 中文: 为什么：权限表规定只有图书管理员能更新借阅。效果：admin 和其他人会收到 403。
require_role(['librarian']);   // only librarians update borrowings (see the roles table)

// EN: run only when a "Mark returned" button was pressed. 中文: 只有按下 "Mark returned" 按钮时才运行。
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // EN: id of the loan being returned. 中文: 被归还的那条借阅记录的 id。
    $id = (int) ($_POST['id'] ?? 0);

    // EN: WHY: returning changes two tables (borrowings and books), so use a transaction: both succeed or neither. 中文: 为什么：还书要修改两张表（borrowings 和 books），所以用事务：要么都成功，要么都不改。
    $pdo->beginTransaction();
    // EN: find the loan only if it is still "borrowed"; FOR UPDATE locks it so two librarians can't return it twice (and add 2 copies). 中文: 只查找仍是 "borrowed" 的借阅；FOR UPDATE 锁住它，避免两个管理员重复归还（导致库存加 2）。
    $stmt = $pdo->prepare("SELECT br.book_id, b.title FROM borrowings br JOIN books b ON b.id = br.book_id
                           WHERE br.id = ? AND br.status = 'borrowed' FOR UPDATE");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    // EN: found = a real active loan. 中文: 找到 = 确实是一条有效的在借记录。
    if ($row) {
        // EN: EFFECT: mark the loan returned and record today's date. 中文: 效果：把借阅标记为已归还，并记录今天的日期。
        $pdo->prepare("UPDATE borrowings SET status = 'returned', return_date = CURDATE() WHERE id = ?")->execute([$id]);
        // EN: EFFECT: put the copy back on the shelf (+1). 中文: 效果：把这一册放回书架（+1）。
        $pdo->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE id = ?')->execute([$row['book_id']]);
        flash('success', '"' . $row['title'] . '" was returned and is back on the shelf.');
    } else {
        // EN: not found = already returned (e.g. double click). 中文: 没找到 = 已经归还过了（例如重复点击）。
        flash('error', 'That loan was already returned.');
    }
    // EN: EFFECT: save the changes. 中文: 效果：保存修改。
    $pdo->commit();
    // EN: go back to the list, keeping the search text if there was one. 中文: 回到列表，如果有搜索词就保留。
    redirect('admin/return.php' . (!empty($_POST['q']) ? '?q=' . urlencode($_POST['q']) : ''));
}

// EN: search text for finding a loan. 中文: 用来查找借阅的搜索词。
$q = trim($_GET['q'] ?? '');
$params = [];
// EN: extra SQL condition; empty unless a search was typed. 中文: 额外的 SQL 条件；没搜索时为空。
$extra = '';
if ($q !== '') {
    // EN: escape LIKE wildcards in the user's text, then wrap with % (contains). 中文: 转义用户输入里的 LIKE 通配符，再两端加 % 表示"包含"。
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
    // EN: search student name, email or book title. 中文: 在学生姓名、邮箱、书名里搜索。
    $extra = ' AND (u.name LIKE :q1 OR u.email LIKE :q2 OR b.title LIKE :q3)';
    $params = [':q1' => $like, ':q2' => $like, ':q3' => $like];
}
// EN: EFFECT: all active loans, soonest due first. "'borrowed' AS status" adds a fixed column so loan_badge() works. 中文: 效果：所有在借记录，最先到期的在前。"'borrowed' AS status" 添加一个固定列，让 loan_badge() 能正常使用。
$stmt = $pdo->prepare("SELECT br.id, br.borrow_date, br.due_date, u.name AS student, u.email, b.title,
                              (br.due_date < CURDATE()) AS is_overdue, 'borrowed' AS status
                       FROM borrowings br JOIN users u ON u.id = br.user_id JOIN books b ON b.id = br.book_id
                       WHERE br.status = 'borrowed' $extra ORDER BY br.due_date ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$page_title = 'Return books';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Return books</h1><span class="muted"><?= count($rows) ?> on loan</span></div>

<?php // EN: search form; the inline style makes it a 2-column layout (input + button). | 中文: 搜索表单；内联样式让它变成两列布局（输入框 + 按钮）。 ?>
<form class="filters filters-compact" method="get" role="search" style="grid-template-columns:1fr auto">
    <div><label for="q">Find a loan</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Student name, email or book title"></div>
    <button class="btn btn-primary" type="submit">Search</button>
</form>

<?php if (!$rows): ?>
    <?php // EN: message changes if a search was used. | 中文: 提示文字会根据是否使用了搜索而变化。 ?>
    <div class="empty">No books are on loan<?= $q !== '' ? ' for this search' : '' ?>.</div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Student</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Status</th><th class="col-actions">Action</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr class="<?= $r['is_overdue'] ? 'row-late' : '' ?>">
            <td><?= e($r['student']) ?><br><span class="muted"><?= e($r['email']) ?></span></td>
            <td><?= e($r['title']) ?></td>
            <td><?= e(fmt_date($r['borrow_date'])) ?></td>
            <td><?= e(fmt_date($r['due_date'])) ?></td>
            <td><?= loan_badge($r) ?></td>
            <td class="col-actions">
                <?php // EN: one small form per row; it sends the loan id (and the search text) back to this page. | 中文: 每行一个小表单；把借阅 id（和搜索词）提交回本页。 ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                    <button class="btn btn-small btn-primary" type="submit">Mark returned</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
