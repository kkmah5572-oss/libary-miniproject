<?php
// Shared screen for managing categories and authors (same layout, different table).
// EN: WHY: categories and authors pages are identical except for the table, so one function serves both. 中文: 为什么：分类页和作者页除了表不同，其他完全一样，所以用一个函数服务两者。

// EN: $table = DB table name; $singular/$plural = words shown to the user. 中文: $table 是数据库表名；$singular/$plural 是显示给用户的单数/复数词。
function render_lookup_page(string $table, string $singular, string $plural): void
{
    // EN: need the database connection inside this function. 中文: 函数里要用数据库连接。
    global $pdo;
    // EN: WHY: table names can't be sent as safe "?" values, so we only accept two known names. 中文: 为什么：表名不能用 "?" 占位符安全传递，所以只接受两个已知名字。
    $allowed = ['categories', 'authors'];   // table name is never taken from user input
    // EN: EFFECT: any other table name stops the page (prevents SQL injection through the table name). 中文: 效果：其他表名一律终止页面（防止通过表名进行 SQL 注入）。
    if (!in_array($table, $allowed, true)) {
        exit('Invalid table.');
    }
    // EN: EFFECT: only admin and librarian may continue. 中文: 效果：只有 admin 和 librarian 可以继续。
    require_role(['admin', 'librarian']);

    // EN: WHY: forms are sent with POST; opening the page is GET. EFFECT: this block runs only when a form was submitted. 中文: 为什么：表单用 POST 提交，打开页面是 GET。效果：这个代码块只在提交表单时运行。
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // EN: check the secret token first. 中文: 先检查秘密令牌。
        csrf_verify();
        // EN: which button was pressed: add / rename / delete. 中文: 按下了哪个按钮：add / rename / delete。
        $action = $_POST['action'] ?? '';
        // EN: trim removes spaces at both ends of the name. 中文: trim 去掉名字两端的空格。
        $name   = trim($_POST['name'] ?? '');
        // EN: (int) makes the id a number, so it can't carry SQL tricks. 中文: (int) 把 id 变成数字，避免夹带 SQL 恶意内容。
        $id     = (int) ($_POST['id'] ?? 0);

        // EN: WHY: database errors (like duplicates) are thrown as exceptions; try lets us handle them. 中文: 为什么：数据库错误（如重复）会作为异常抛出，用 try 才能处理。
        try {
            // EN: add and rename share the same name checks. 中文: add 和 rename 共用同样的名字检查。
            if ($action === 'add' || $action === 'rename') {
                // EN: validation: not empty and at most 100 characters (the column size). 中文: 校验：不能为空，最多 100 个字符（和数据库列大小一致）。
                if ($name === '' || mb_strlen($name) > 100) {
                    // EN: EFFECT: show a red error message. ucfirst makes the first letter capital. 中文: 效果：显示红色错误提示。ucfirst 让首字母大写。
                    flash('error', ucfirst($singular) . ' name must be 1 to 100 characters.');
                // EN: valid name and action is add. 中文: 名字有效，且动作是 add。
                } elseif ($action === 'add') {
                    // EN: WHY: "?" is a placeholder so the name can never run as SQL. EFFECT: inserts a new row. ($table is safe: whitelisted above.) 中文: 为什么："?" 是占位符，名字不会被当成 SQL 执行。效果：插入一行新记录。（$table 已在上面白名单检查过，所以安全。）
                    $pdo->prepare("INSERT INTO $table (name) VALUES (?)")->execute([$name]);
                    // EN: success message. 中文: 成功提示。
                    flash('success', ucfirst($singular) . ' "' . $name . '" added.');
                // EN: otherwise it is a rename. 中文: 否则就是重命名。
                } else {
                    // EN: EFFECT: changes the name of the row with this id. 中文: 效果：修改这个 id 对应记录的名字。
                    $pdo->prepare("UPDATE $table SET name = ? WHERE id = ?")->execute([$name, $id]);
                    flash('success', ucfirst($singular) . ' renamed.');
                }
            // EN: delete action. 中文: 删除动作。
            } elseif ($action === 'delete') {
                // EN: EFFECT: deletes the row; MySQL refuses if books still use it (foreign key). 中文: 效果：删除记录；如果还有书在使用它，MySQL 会拒绝（外键保护）。
                $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
                flash('success', ucfirst($singular) . ' deleted.');
            }
        // EN: catch database errors. 中文: 捕获数据库错误。
        } catch (PDOException $ex) {
            // EN: WHY: code 23000 = "integrity constraint violation" (duplicate name, or still in use). 中文: 为什么：错误码 23000 表示"违反完整性约束"（名字重复，或仍在使用）。
            if ($ex->getCode() === '23000') {
                // EN: EFFECT: pick the right friendly message depending on the action. 中文: 效果：根据动作选择合适的友好提示。
                flash('error', $action === 'delete'
                    ? "This $singular still has books. Move or delete those books first."
                    : "A $singular with that name already exists.");
            // EN: any other error is a real bug, so don't hide it. 中文: 其他错误是真正的 bug，不要隐藏。
            } else {
                // EN: EFFECT: throw the error again so it shows up. 中文: 效果：把错误再次抛出，让它显示出来。
                throw $ex;
            }
        }
        // EN: WHY: Post/Redirect/Get — after saving, redirect so refreshing doesn't resubmit the form. 中文: 为什么：提交后重定向（PRG 模式），刷新页面就不会重复提交表单。
        redirect('admin/' . $table . '.php');
    }

    // EN: WHY: books table uses a different column name for each lookup table. EFFECT: $fk = the foreign key column. 中文: 为什么：书籍表对每个关联表用的列名不同。效果：$fk 是对应的外键列名。
    $fk   = $table === 'categories' ? 'category_id' : 'author_id';
    // EN: WHY: LEFT JOIN keeps rows with zero books; COUNT counts books per row; GROUP BY groups per category/author; ORDER BY sorts by name. EFFECT: $rows = list with a book_count for each. 
    // 中文: 为什么：LEFT JOIN 让没有书的分类/作者也出现；COUNT 统计每个的书数；GROUP BY 按分类/作者分组；ORDER BY 按名字排序。效果：$rows 是带 book_count 的列表。
    $rows = $pdo->query("SELECT t.id, t.name, COUNT(b.id) AS book_count
                         FROM $table t LEFT JOIN books b ON b.$fk = t.id
                         GROUP BY t.id, t.name ORDER BY t.name")->fetchAll();

    // EN: title for header.php, e.g. "Categories". 中文: 给 header.php 用的标题，例如 "Categories"。
    $page_title = ucfirst($plural);
    // EN: EFFECT: print the top of the page. 中文: 效果：输出页面顶部。
    require __DIR__ . '/header.php';
    // EN: leave PHP, write HTML. 中文: 离开 PHP，写 HTML。
    ?>
    <?php // EN: page heading. | 中文: 页面标题。 ?>
    <div class="page-head"><h1><?= e(ucfirst($plural)) ?></h1></div>

    <?php // EN: form to add a new item (sends POST to the same page). | 中文: 添加新项目的表单（以 POST 提交给本页）。 ?>
    <form method="post" class="inline-add card-panel">
        <?php // EN: security token. | 中文: 安全令牌。 ?>
        <?= csrf_field() ?>
        <?php // EN: hidden field tells the PHP code above this is an "add". | 中文: 隐藏字段告诉上面的 PHP 代码这是 "add" 动作。 ?>
        <input type="hidden" name="action" value="add">
        <?php // EN: label linked to the input by for/id (accessibility + bigger click area). | 中文: label 通过 for/id 和输入框关联（无障碍，点击区域也更大）。 ?>
        <label for="new-name">New <?= e($singular) ?></label>
        <?php // EN: maxlength limits typing to 100; required stops empty submit. | 中文: maxlength 限制最多输入 100 个字符；required 不允许空提交。 ?>
        <input id="new-name" name="name" maxlength="100" required>
        <button class="btn btn-primary" type="submit">Add <?= e($singular) ?></button>
    </form>

    <?php // EN: wrapper that lets wide tables scroll on small screens. | 中文: 包裹层，让宽表格在小屏幕上可以滚动。 ?>
    <div class="table-wrap">
    <table class="table">
        <?php // EN: table header row. | 中文: 表头行。 ?>
        <thead><tr><th>Name</th><th>Books</th><th class="col-actions">Actions</th></tr></thead>
        <tbody>
        <?php // EN: one table row for each category/author. | 中文: 每个分类/作者生成一行。 ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td>
                    <?php // EN: rename form: the name is an editable input inside the table cell. | 中文: 重命名表单：名字就是单元格里可编辑的输入框。 ?>
                    <form method="post" class="row-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="rename">
                        <?php // EN: which row to rename; (int) keeps it a number. | 中文: 要重命名哪一行；(int) 保证是数字。 ?>
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <?php // EN: aria-label gives the field a name for screen readers (no visible label here). | 中文: aria-label 给输入框一个读屏软件能读的名字（这里没有可见的 label）。 ?>
                        <input name="name" value="<?= e($r['name']) ?>" maxlength="100" required aria-label="Name">
                        <button class="btn btn-small" type="submit">Save</button>
                    </form>
                </td>
                <?php // EN: how many books use this item. | 中文: 有多少本书在使用这一项。 ?>
                <td><?= (int) $r['book_count'] ?></td>
                <td class="col-actions">
                    <?php // EN: onsubmit confirm(): the browser asks "OK?"; "return false" cancels the submit. | 中文: onsubmit 的 confirm()：浏览器弹出确认框；返回 false 就取消提交。 ?>
                    <form method="post" onsubmit="return confirm('Delete this <?= e($singular) ?>?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
    // EN: EFFECT: print the bottom of the page. 中文: 效果：输出页面底部。
    require __DIR__ . '/footer.php';
}
