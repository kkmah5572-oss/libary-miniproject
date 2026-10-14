<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/../includes/init.php';
// EN: staff can view borrowings. 中文: 员工可以查看借阅记录。
require_role(['admin', 'librarian']);

// Admin can delete a borrowing record (if the book was still out, the copy goes back on the shelf).
// EN: a POST here means someone pressed Delete. 中文: 这里收到 POST 说明有人点了 Delete。
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // EN: WHY: viewing is for staff, but deleting is admin only (roles table). 中文: 为什么：查看对员工开放，但删除只限 admin（权限表）。
    require_role(['admin']);
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);

    // EN: transaction: deleting the record and fixing the stock must happen together. 中文: 事务：删除记录和修正库存必须一起完成。
    $pdo->beginTransaction();
    // EN: read the loan and lock it. 中文: 读取这条借阅并锁住。
    $stmt = $pdo->prepare('SELECT book_id, status FROM borrowings WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        // EN: WHY: if the book was still out, deleting the record means it is "back", so add the copy back; otherwise stock would be wrong forever. 中文: 为什么：书如果还没还，删除记录等于"收回"，要把这一册加回库存；否则库存会一直算错。
        if ($row['status'] === 'borrowed') {
            $pdo->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE id = ?')->execute([$row['book_id']]);
        }
        // EN: EFFECT: remove the loan record. 中文: 效果：删除这条借阅记录。
        $pdo->prepare('DELETE FROM borrowings WHERE id = ?')->execute([$id]);
        flash('success', 'Borrowing record deleted.');
    }
    // EN: save changes. 中文: 保存修改。
    $pdo->commit();
    redirect('admin/borrowings.php');
}

// EN: status filter from the URL; default "all". 中文: 来自网址的状态筛选；默认 "all"。
$status = $_GET['status'] ?? 'all';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where = [];
$params = [];
// EN: translate the chosen status into an SQL condition. 中文: 把选择的状态转成 SQL 条件。
if ($status === 'borrowed')      { $where[] = "br.status = 'borrowed'"; }
elseif ($status === 'returned')  { $where[] = "br.status = 'returned'"; }
elseif ($status === 'overdue')   { $where[] = "br.status = 'borrowed' AND br.due_date < CURDATE()"; }
// EN: WHY: unknown values are reset to 'all' so nothing weird gets through. 中文: 为什么：未知值重置为 'all'，避免奇怪的值混进来。
else                             { $status = 'all'; }
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
    $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2 OR b.title LIKE :q3)';
    $params = [':q1' => $like, ':q2' => $like, ':q3' => $like];
}
// EN: shared FROM + WHERE for both queries; "." joins strings. 中文: 两条查询共用的 FROM + WHERE；"." 用来拼接字符串。
$from = 'FROM borrowings br JOIN users u ON u.id = br.user_id JOIN books b ON b.id = br.book_id'
      . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

// EN: count matching records for paging. 中文: 统计符合条件的记录数，用于分页。
$stmt = $pdo->prepare("SELECT COUNT(*) $from");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);

// EN: fetch one page, newest first. 中文: 取当前页的数据，最新在前。
$stmt = $pdo->prepare("SELECT br.*, u.name AS student, u.email, b.title,
                              (br.status = 'borrowed' AND br.due_date < CURDATE()) AS is_overdue
                       $from ORDER BY br.id DESC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
// EN: LIMIT/OFFSET must be integers. 中文: LIMIT/OFFSET 必须是整数。
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$page_title = 'Borrowings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Borrowings</h1><span class="muted"><?= $total ?> record<?= $total === 1 ? '' : 's' ?></span></div>

<form class="filters filters-compact" method="get" role="search">
    <div><label for="q">Search</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Student name, email or book title"></div>
    <div>
        <label for="status">Status</label>
        <select id="status" name="status">
            <?php // EN: build the options from a key => label list, so adding a status is one line. | 中文: 用 键 => 文字 的列表生成选项，新增状态只需加一行。 ?>
            <?php foreach (['all' => 'All', 'borrowed' => 'On loan', 'overdue' => 'Overdue', 'returned' => 'Returned'] as $k => $label): ?>
                <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-primary" type="submit">Filter</button>
</form>

<?php if (!$rows): ?>
    <div class="empty">No borrowing records found.</div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <?php // EN: the Actions column only exists for admins. | 中文: 只有 admin 才有 Actions 列。 ?>
    <thead><tr><th>Student</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th><?php if (current_role() === 'admin'): ?><th class="col-actions">Actions</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr class="<?= $r['is_overdue'] ? 'row-late' : '' ?>">
            <td><?= e($r['student']) ?><br><span class="muted"><?= e($r['email']) ?></span></td>
            <td><a href="<?= e(url('book.php?id=' . (int) $r['book_id'])) ?>"><?= e($r['title']) ?></a></td>
            <td><?= e(fmt_date($r['borrow_date'])) ?></td>
            <td><?= e(fmt_date($r['due_date'])) ?></td>
            <?php // EN: fmt_date shows "-" if the book isn't returned yet (NULL). | 中文: 书还没还（NULL）时 fmt_date 显示 "-"。 ?>
            <td><?= e(fmt_date($r['return_date'])) ?></td>
            <td><?= loan_badge($r) ?></td>
            <?php if (current_role() === 'admin'): ?>
            <td class="col-actions">
                <?php // EN: confirm() asks before deleting. | 中文: confirm() 在删除前询问确认。 ?>
                <form method="post" onsubmit="return confirm('Delete this borrowing record?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-small btn-danger" type="submit">Delete</button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php // EN: page links that keep the filters (empty values are removed by array_filter). | 中文: 保留筛选条件的页码链接（array_filter 去掉空值）。 ?>
<?= pagination($page, $pages, array_filter(['q' => $q, 'status' => $status !== 'all' ? $status : null])) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
