<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/../includes/init.php';
// EN: staff only. 中文: 仅员工可访问。
require_role(['admin', 'librarian']);

// EN: WHY: one form does both "Add" (id = 0) and "Edit" (id > 0). The id comes from the URL (opening) or the form (saving). 中文: 为什么：同一个表单既能"新增"（id = 0）也能"编辑"（id > 0）。id 来自网址（打开时）或表单（保存时）。
$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
// EN: default empty values for a new book, so the form can always read $book['...']. 中文: 新书的默认空值，这样表单总能读取 $book['...']。
$book = ['title' => '', 'author_id' => '', 'category_id' => '', 'year_published' => '', 'description' => '', 'total_copies' => 1, 'available_copies' => 1];

// EN: editing: load the existing book. 中文: 编辑模式：载入已有的书。
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM books WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    // EN: bad id → message and go back to the list. 中文: id 不存在 → 提示并回到列表。
    if (!$found) {
        flash('error', 'That book doesn\'t exist.');
        redirect('admin/books.php');
    }
    // EN: replace the defaults with the real data. 中文: 用真实数据替换默认值。
    $book = $found;
}

// EN: lists for the two dropdowns. 中文: 两个下拉框用的列表。
$authors    = $pdo->query('SELECT id, name FROM authors ORDER BY name')->fetchAll();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
// EN: one error message per field. 中文: 每个字段一条错误信息。
$errors = [];

// EN: form submitted. 中文: 表单已提交。
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // EN: read and clean each input. (int) turns ids and counts into numbers. 中文: 读取并清理每个输入。(int) 把 id 和数量转成数字。
    $title    = trim($_POST['title'] ?? '');
    $authorId = (int) ($_POST['author_id'] ?? 0);
    $catId    = (int) ($_POST['category_id'] ?? 0);
    $yearRaw  = trim($_POST['year_published'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $copies   = (int) ($_POST['total_copies'] ?? 0);

    // EN: title: required, max 200 (database column size). 中文: 书名：必填，最多 200（和数据库列大小一致）。
    if ($title === '' || mb_strlen($title) > 200) { $errors['title'] = 'Enter a title (up to 200 characters).'; }
    // EN: WHY: the chosen author must really exist in the list (the user could fake the form). array_column picks all ids. 中文: 为什么：所选作者必须真的在列表里（用户可能伪造表单）。array_column 取出所有 id。
    if (!in_array($authorId, array_column($authors, 'id'), true)) { $errors['author_id'] = 'Choose an author.'; }
    if (!in_array($catId, array_column($categories, 'id'), true)) { $errors['category_id'] = 'Choose a category.'; }
    // EN: year is optional; null means "unknown". 中文: 年份可选；null 表示"未知"。
    $year = null;
    if ($yearRaw !== '') {
        // EN: ctype_digit = only digits; year must be 1 .. next year. 中文: ctype_digit 表示只含数字；年份必须在 1 到明年之间。
        if (!ctype_digit($yearRaw) || (int) $yearRaw < 1 || (int) $yearRaw > (int) date('Y') + 1) {
            $errors['year_published'] = 'Enter a valid year.';
        } else {
            $year = (int) $yearRaw;
        }
    }
    // EN: sensible limits for copies. 中文: 册数的合理范围。
    if ($copies < 0 || $copies > 1000) { $errors['total_copies'] = 'Copies must be between 0 and 1000.'; }

    // When the total changes, the available number moves by the same amount.
    // EN: WHY: if the library owns 2 more copies, 2 more are on the shelf (loaned copies are unchanged). New book: available = total. 中文: 为什么：图书馆多了 2 册，书架上也多 2 册（已借出的不变）。新书：可借 = 总数。
    $available = $id ? (int) $book['available_copies'] + ($copies - (int) $book['total_copies']) : $copies;
    // EN: can't reduce the total below the number currently on loan. 中文: 总数不能减到比当前借出数还少。
    if (!isset($errors['total_copies']) && $available < 0) {
        $errors['total_copies'] = 'Too low: ' . ((int) $book['total_copies'] - (int) $book['available_copies']) . ' copies are on loan right now.';
    }

    // EN: keep what was typed so the form refills after an error. array_merge overwrites the old values. 中文: 保存已输入的内容，出错后表单能回填。array_merge 会覆盖旧值。
    $book = array_merge($book, ['title' => $title, 'author_id' => $authorId, 'category_id' => $catId,
                                'year_published' => $yearRaw, 'description' => $desc, 'total_copies' => $copies]);

    // EN: no errors: save. 中文: 没有错误：保存。
    if (!$errors) {
        if ($id) {
            // EN: EFFECT: update the existing book. "$desc ?: null" stores NULL instead of an empty string. 中文: 效果：更新已有的书。"$desc ?: null" 让空简介存成 NULL 而不是空字符串。
            $stmt = $pdo->prepare('UPDATE books SET title=?, author_id=?, category_id=?, year_published=?, description=?, total_copies=?, available_copies=? WHERE id=?');
            $stmt->execute([$title, $authorId, $catId, $year, $desc ?: null, $copies, $available, $id]);
            flash('success', 'Book updated.');
        } else {
            // EN: EFFECT: insert a new book; available copies start equal to the total. 中文: 效果：新增一本书；可借册数一开始等于总数。
            $stmt = $pdo->prepare('INSERT INTO books (title, author_id, category_id, year_published, description, total_copies, available_copies) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$title, $authorId, $catId, $year, $desc ?: null, $copies, $copies]);
            flash('success', 'Book added.');
        }
        redirect('admin/books.php');
    }
}

// EN: small helper: red error text under a field ("err" = short name). 中文: 小函数：在字段下面显示红色错误（err 是简称）。
function err(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
}

// EN: title depends on add/edit mode. 中文: 标题取决于是新增还是编辑。
$page_title = $id ? 'Edit book' : 'Add book';
require __DIR__ . '/../includes/header.php';
?>
<div class="form-card form-wide">
    <div class="inner">
        <h1><?= $id ? 'Edit book' : 'Add a book' ?></h1>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <?php // EN: sends the id back so the saving code knows add vs edit. | 中文: 把 id 带回去，保存时才知道是新增还是编辑。 ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="field">
                <label for="title">Title</label>
                <input id="title" name="title" value="<?= e($book['title']) ?>" maxlength="200" required>
                <?= err($errors, 'title') ?>
            </div>
            <?php // EN: two fields side by side. | 中文: 两个字段并排。 ?>
            <div class="form-row">
                <div class="field">
                    <label for="author_id">Author</label>
                    <select id="author_id" name="author_id" required>
                        <option value="">Choose an author</option>
                        <?php foreach ($authors as $a): ?>
                            <?php // EN: "selected" preselects the current author. | 中文: "selected" 预选当前作者。 ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) $book['author_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-hint">Not listed? <a href="<?= e(url('admin/authors.php')) ?>">Add an author</a> first.</p>
                    <?= err($errors, 'author_id') ?>
                </div>
                <div class="field">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Choose a category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) $book['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-hint">Not listed? <a href="<?= e(url('admin/categories.php')) ?>">Add a category</a> first.</p>
                    <?= err($errors, 'category_id') ?>
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="year_published">Year published</label>
                    <?php // EN: inputmode="numeric" shows a number keypad on phones. | 中文: inputmode="numeric" 让手机显示数字键盘。 ?>
                    <input id="year_published" name="year_published" inputmode="numeric" value="<?= e($book['year_published']) ?>" maxlength="4">
                    <?= err($errors, 'year_published') ?>
                </div>
                <div class="field">
                    <label for="total_copies">Total copies</label>
                    <?php // EN: min/max give browser-side limits (PHP checks again). | 中文: min/max 提供浏览器端限制（PHP 还会再检查一次）。 ?>
                    <input id="total_copies" type="number" name="total_copies" min="0" max="1000" value="<?= e($book['total_copies']) ?>" required>
                    <?php // EN: when editing, show how many are available now. | 中文: 编辑时显示当前可借册数。 ?>
                    <?php if ($id): ?><p class="field-hint"><?= (int) $book['available_copies'] ?> available now.</p><?php endif; ?>
                    <?= err($errors, 'total_copies') ?>
                </div>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?= e($book['description']) ?></textarea>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $id ? 'Save changes' : 'Add book' ?></button>
                <a class="btn btn-outline" href="<?= e(url('admin/books.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
