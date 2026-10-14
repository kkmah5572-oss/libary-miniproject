<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/../includes/init.php';
// EN: staff only. 中文: 仅员工可访问。
require_role(['admin', 'librarian']);

// Delete a book (POST)
// EN: a POST to this page means the Delete button was pressed. 中文: 向本页发送 POST 表示点了 Delete 按钮。
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    try {
        // EN: EFFECT: delete the book. If loans refer to it, MySQL's foreign key refuses and throws an error. 中文: 效果：删除这本书。如果有借阅记录引用它，MySQL 外键会拒绝并抛出错误。
        $pdo->prepare('DELETE FROM books WHERE id = ?')->execute([$id]);
        flash('success', 'Book deleted.');
    } catch (PDOException $ex) {
        // EN: 23000 = foreign key / integrity problem. 中文: 23000 表示外键/完整性问题。
        if ($ex->getCode() === '23000') {
            flash('error', 'This book has borrowing records, so it can\'t be deleted. Set its copies to 0 instead.');
        } else {
            throw $ex;
        }
    }
    redirect('admin/books.php');
}

// EN: search text and paging. 中文: 搜索词和分页。
$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$params  = [];
$where   = '';
if ($q !== '') {
    // EN: escape LIKE wildcards, then wrap with % (contains). 中文: 转义 LIKE 通配符，再两端加 % 表示"包含"。
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
    // EN: search title, author or category. 中文: 在书名、作者、分类里搜索。
    $where = 'WHERE b.title LIKE :q1 OR a.name LIKE :q2 OR c.name LIKE :q3';
    $params = [':q1' => $like, ':q2' => $like, ':q3' => $like];
}
// EN: shared FROM/JOIN/WHERE for both queries. 中文: 两条查询共用的 FROM/JOIN/WHERE。
$from = "FROM books b JOIN authors a ON a.id = b.author_id JOIN categories c ON c.id = b.category_id $where";

// EN: count for paging. 中文: 统计总数用于分页。
$stmt = $pdo->prepare("SELECT COUNT(*) $from");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);

// EN: fetch only the columns the table needs, for this page. 中文: 只取表格需要的列，且只取当前页。
$stmt = $pdo->prepare("SELECT b.id, b.title, b.year_published, b.total_copies, b.available_copies, a.name AS author_name, c.name AS category_name
                       $from ORDER BY b.title LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$books = $stmt->fetchAll();

$page_title = 'Manage books';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Manage books</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/book-form.php')) ?>">Add a book</a>
</div>

<?php // EN: search form; style makes a 2-column layout. | 中文: 搜索表单；style 让它成为两列布局。 ?>
<form class="filters filters-compact" method="get" role="search" style="grid-template-columns:1fr auto">
    <div><label for="q">Search</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Title, author or category"></div>
    <button class="btn btn-primary" type="submit">Search</button>
</form>

<?php if (!$books): ?>
    <div class="empty">No books found.</div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Year</th><th>Available</th><th class="col-actions">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($books as $b): ?>
        <tr>
            <td><a href="<?= e(url('book.php?id=' . (int) $b['id'])) ?>"><strong><?= e($b['title']) ?></strong></a></td>
            <td><?= e($b['author_name']) ?></td>
            <td><?= e($b['category_name']) ?></td>
            <?php // EN: show "-" when the year is empty. | 中文: 年份为空时显示 "-"。 ?>
            <td><?= $b['year_published'] ? (int) $b['year_published'] : '-' ?></td>
            <?php // EN: "available / total", e.g. "4 / 5". | 中文: "可借 / 总数"，例如 "4 / 5"。 ?>
            <td><?= (int) $b['available_copies'] ?> / <?= (int) $b['total_copies'] ?></td>
            <td class="col-actions">
                <a class="btn btn-small" href="<?= e(url('admin/book-form.php?id=' . (int) $b['id'])) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this book?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                    <button class="btn btn-small btn-danger" type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= pagination($page, $pages, array_filter(['q' => $q])) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
