<?php
// EN: load session, database and helpers. 中文: 加载 session、数据库和辅助函数。
require __DIR__ . '/includes/init.php';
// EN: WHY: only students may borrow (matches the roles table). EFFECT: others get redirected/403. 中文: 为什么：只有学生可以借书（和权限表一致）。效果：其他人会被跳转或收到 403。
require_role(['student']);

// EN: WHY: this page only does work for form submits; opening it by address just goes back to the book list. 中文: 为什么：本页只处理表单提交；直接打开网址就回到书籍列表。
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('books.php');
}
// EN: check the CSRF token. 中文: 检查 CSRF 令牌。
csrf_verify();

// EN: the book chosen in the form; (int) makes it a number. 中文: 表单里选的书；(int) 转成数字。
$bookId = (int) ($_POST['book_id'] ?? 0);
// EN: WHY: the user id comes from the session (trusted), never from the form. 中文: 为什么：用户 id 取自 session（可信），绝不取自表单。
$userId = (int) $_SESSION['user_id'];

// EN: WHY: we use RuntimeException for "normal" problems (no copies left) so catch can show them nicely. 中文: 为什么：用 RuntimeException 表示"正常"问题（比如没库存），catch 可以友好地显示。
try {
    // EN: WHY: a transaction = all steps succeed together or none do. EFFECT: starts it. 中文: 为什么：事务 = 所有步骤要么一起成功，要么都不生效。效果：开始事务。
    $pdo->beginTransaction();

    // Lock the book row so two students can't take the last copy at the same time.
    // EN: FOR UPDATE locks this row until the transaction ends; a second student has to wait and then sees the updated number. 中文: FOR UPDATE 会锁住这一行直到事务结束；第二个学生必须等待，然后看到更新后的数量。
    $stmt = $pdo->prepare('SELECT title, available_copies FROM books WHERE id = ? FOR UPDATE');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();

    // EN: check 1: the book exists. 中文: 检查 1：书存在。
    if (!$book) {
        // EN: throw jumps to the catch block below. 中文: throw 会跳到下面的 catch。
        throw new RuntimeException('That book doesn\'t exist.');
    }
    // EN: check 2: at least one copy left. 中文: 检查 2：至少还有一册。
    if ((int) $book['available_copies'] < 1) {
        throw new RuntimeException('Sorry, all copies of this book are out.');
    }

    // EN: check 3: this student doesn't already hold this book. 中文: 检查 3：这个学生没有已经借着这本书。
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
    $stmt->execute([$userId, $bookId]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('You already have this book.');
    }

    // EN: check 4: the student is below the maximum number of loans. 中文: 检查 4：学生没有超过最大借阅数量。
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status = 'borrowed'");
    $stmt->execute([$userId]);
    if ((int) $stmt->fetchColumn() >= MAX_ACTIVE_LOANS) {
        throw new RuntimeException('You can hold up to ' . MAX_ACTIVE_LOANS . ' books at a time. Return one first.');
    }

    // EN: EFFECT: add the loan record. CURDATE() = today (MySQL); DATE_ADD(... INTERVAL 14 DAY) = due date. LOAN_DAYS is our own constant, so it's safe to join into the SQL text. 
    // 中文: 效果：新增一条借阅记录。CURDATE() 是今天（MySQL 函数）；DATE_ADD(... INTERVAL 14 DAY) 是到期日。LOAN_DAYS 是我们自己的常量，拼进 SQL 文本是安全的。
    $stmt = $pdo->prepare("INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status)
                           VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL " . LOAN_DAYS . " DAY), 'borrowed')");
    $stmt->execute([$userId, $bookId]);

    // EN: EFFECT: one fewer copy on the shelf. "available_copies - 1" is calculated inside the database (safe under the lock). 中文: 效果：书架上少一册。"available_copies - 1" 在数据库内计算（有锁保护，所以安全）。
    $pdo->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE id = ?')->execute([$bookId]);

    // EN: EFFECT: save all changes permanently and release the lock. 中文: 效果：永久保存所有修改并释放锁。
    $pdo->commit();
    flash('success', 'You borrowed "' . $book['title'] . '". Please return it within ' . LOAN_DAYS . ' days.');
    // EN: go to the student's loan list. 中文: 跳到学生的借阅列表。
    redirect('my-borrowings.php');
} catch (RuntimeException $ex) {
    // EN: EFFECT: undo everything done inside the transaction. 中文: 效果：撤销事务里做过的所有操作。
    $pdo->rollBack();
    // EN: show the reason (the text we threw above). 中文: 显示原因（就是上面 throw 的文字）。
    flash('error', $ex->getMessage());
    // EN: back to the book page. 中文: 回到这本书的页面。
    redirect('book.php?id=' . $bookId);
}
