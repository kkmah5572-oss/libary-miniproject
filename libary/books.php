<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/includes/init.php';

// EN: search text from the URL (?q=...); trim removes outer spaces. 中文: 来自网址的搜索词（?q=...）；trim 去掉两端空格。
$q        = trim($_GET['q'] ?? '');
// EN: category id from the URL; (int) makes it a number (0 = none). 中文: 来自网址的分类 id；(int) 转成数字（0 表示没选）。
$category = (int) ($_GET['category'] ?? 0);
// EN: checkbox: if "available" exists in the URL, the box was ticked. 中文: 复选框：网址里有 "available" 说明勾选了。
$onlyFree = isset($_GET['available']);
// EN: WHY: page number can't be below 1. 中文: 为什么：页码不能小于 1。
$page     = max(1, (int) ($_GET['page'] ?? 1));
// EN: 12 books per page. 中文: 每页 12 本书。
$perPage  = 12;

// EN: WHY: build the WHERE conditions piece by piece, depending on which filters are used. 中文: 为什么：根据用到的筛选条件，一段一段拼出 WHERE。
$where  = [];
// EN: the values for the placeholders (kept separate from the SQL = safe). 中文: 占位符对应的值（和 SQL 分开 = 安全）。
$params = [];
if ($q !== '') {
    // EN: WHY: in LIKE, % and _ are wildcards. We put a backslash before them so the user's text is matched literally; then wrap with % for "contains". 
    // 中文: 为什么：LIKE 里 % 和 _ 是通配符。我们在它们前面加反斜杠，让用户输入按字面匹配；再在两端加 % 表示"包含"。
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
    // EN: search title OR author OR category name. 中文: 在书名、作者、分类名里搜索。
    $where[] = '(b.title LIKE :q1 OR a.name LIKE :q2 OR c.name LIKE :q3)';
    // EN: WHY: each named placeholder is used once, so the same value is assigned three times. 中文: 为什么：每个命名占位符只用一次，所以同一个值赋给三个占位符。
    $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
}
if ($category > 0) {
    // EN: only books of this category. 中文: 只显示这个分类的书。
    $where[] = 'b.category_id = :cat';
    $params[':cat'] = $category;
}
if ($onlyFree) {
    // EN: only books with at least one copy on the shelf. 中文: 只显示书架上至少有一册的书。
    $where[] = 'b.available_copies > 0';
}
// EN: join the conditions with AND; no conditions = no WHERE at all. 中文: 用 AND 连接各条件；没有条件就没有 WHERE。
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
// EN: WHY: the FROM/JOIN part is reused by the count query and the list query. 中文: 为什么：FROM/JOIN 部分被"计数查询"和"列表查询"共用。
$from = "FROM books b JOIN authors a ON a.id = b.author_id JOIN categories c ON c.id = b.category_id $whereSql";

// EN: step 1: count all matching books (for "N results" and page count). 中文: 第 1 步：统计符合条件的书总数（用于 "N results" 和总页数）。
$stmt = $pdo->prepare("SELECT COUNT(*) $from");
$stmt->execute($params);
// EN: fetchColumn() returns the single number. 中文: fetchColumn() 返回那个单一的数字。
$total = (int) $stmt->fetchColumn();
// EN: ceil rounds up (25 books / 12 = 3 pages); at least 1 page. 中文: ceil 向上取整（25 本 / 12 = 3 页）；至少 1 页。
$pages = max(1, (int) ceil($total / $perPage));
// EN: WHY: if someone types page=999, clamp it to the last page. 中文: 为什么：有人输入 page=999 时，限制到最后一页。
$page  = min($page, $pages);

// EN: step 2: fetch only this page's books. LIMIT = how many; OFFSET = how many to skip. 中文: 第 2 步：只取当前页的书。LIMIT = 取几本；OFFSET = 跳过几本。
$stmt = $pdo->prepare("SELECT b.*, a.name AS author_name, c.name AS category_name $from
                       ORDER BY b.title LIMIT :lim OFFSET :off");
// EN: bind each filter value. 中文: 绑定每个筛选值。
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
// EN: WHY: LIMIT/OFFSET must be integers, so PARAM_INT is required. 中文: 为什么：LIMIT/OFFSET 必须是整数，所以要用 PARAM_INT。
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
// EN: page 1 skips 0, page 2 skips 12, and so on. 中文: 第 1 页跳过 0 本，第 2 页跳过 12 本，以此类推。
$stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
// EN: EFFECT: $books = this page's rows. 中文: 效果：$books = 当前页的数据行。
$books = $stmt->fetchAll();

// EN: categories for the dropdown. 中文: 下拉框用的分类列表。
$cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$page_title = 'Books';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Books</h1>
    <?php // EN: "1 result" vs "2 results". | 中文: "1 result" 和 "2 results" 的单复数。 ?>
    <span class="muted"><?= $total ?> result<?= $total === 1 ? '' : 's' ?></span>
</div>

<?php // EN: GET form: the filters go into the URL, so results can be shared/bookmarked. | 中文: GET 表单：筛选条件进入网址，结果可以分享/收藏。 ?>
<form class="filters" method="get" role="search">
    <div>
        <label for="q">Search</label>
        <?php // EN: value keeps the search text after searching. | 中文: value 让搜索后仍保留搜索词。 ?>
        <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Title, author or category">
    </div>
    <div>
        <label for="category">Category</label>
        <select id="category" name="category">
            <option value="0">All categories</option>
            <?php foreach ($cats as $c): ?>
                <?php // EN: "selected" marks the currently chosen category. | 中文: "selected" 标记当前选中的分类。 ?>
                <option value="<?= (int) $c['id'] ?>" <?= $category === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php // EN: "checked" keeps the box ticked after searching. | 中文: "checked" 让搜索后复选框保持勾选。 ?>
    <label class="check"><input type="checkbox" name="available" value="1" <?= $onlyFree ? 'checked' : '' ?>> Available now</label>
    <button class="btn btn-primary" type="submit">Search</button>
</form>

<?php // EN: no books found: show an empty-state message. | 中文: 没找到书：显示"空状态"提示。 ?>
<?php if (!$books): ?>
    <div class="empty">
        <p>No books match your search.</p>
        <a href="<?= e(url('books.php')) ?>">Clear filters</a>
    </div>
<?php else: ?>
    <div class="book-grid">
        <?php // EN: one card per book. | 中文: 每本书一张卡片。 ?>
        <?php foreach ($books as $b) { echo book_card($b); } ?>
    </div>
    <?php // EN: page numbers; array_filter drops empty values so URLs stay clean (no "category=0"). | 中文: 页码；array_filter 去掉空值，让网址保持干净（没有 "category=0"）。 ?>
    <?= pagination($page, $pages, array_filter(['q' => $q, 'category' => $category ?: null, 'available' => $onlyFree ? 1 : null])) ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
