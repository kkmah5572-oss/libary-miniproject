<?php
// EN: Opens the PHP file. Everything after this tag is PHP code.
// 中文: 打开 PHP 文件。这个标签之后的内容都会被当作 PHP 代码执行。

// Database connection. XAMPP defaults: user "root", empty password.
// EN: Keeps the connection settings in one place, so you only edit this file when your MySQL changes.
// 中文: 把数据库连接设置集中在一个地方，以后 MySQL 的账号变了只需要改这个文件。

// EN: WHY: the host is "localhost" because MySQL runs on your own computer. EFFECT: PHP will look for MySQL on this machine.
// 中文: 为什么：MySQL 运行在你自己的电脑上，所以主机写 localhost。效果：PHP 会在本机寻找 MySQL。
$DB_HOST = 'localhost';
// EN: WHY: the name of the database we created in database.sql. EFFECT: PHP connects to the "smartlib" database.
// 中文: 为什么：这是 database.sql 里创建的数据库名。效果：PHP 会连接到 smartlib 这个数据库。
$DB_NAME = 'smartlib';
// EN: WHY: XAMPP's default MySQL user is "root". EFFECT: PHP logs in to MySQL as root.
// 中文: 为什么：XAMPP 默认的 MySQL 用户就是 root。效果：PHP 用 root 身份登录 MySQL。
$DB_USER = 'root';
// EN: WHY: XAMPP's default root password is empty. EFFECT: no password is sent (change it if your MySQL has one).
// 中文: 为什么：XAMPP 默认 root 没有密码。效果：连接时不发送密码（如果你的 MySQL 有密码，要改这里）。
$DB_PASS = 'root';

// EN: WHY: connecting can fail (MySQL not started, wrong password), so we wrap it in try so we can show a friendly message. EFFECT: errors inside the try block jump to catch.
// 中文: 为什么：连接可能失败（MySQL 没启动、密码错），所以放进 try 里，失败时可以给出友好提示。效果：try 里出错会跳到 catch。
try {
    // EN: WHY: PDO is PHP's safe, modern database tool. EFFECT: creates the connection object and stores it in $pdo, which every other page uses.
    // 中文: 为什么：PDO 是 PHP 安全、现代的数据库工具。效果：创建连接对象并存进 $pdo，其他所有页面都用它。
    $pdo = new PDO(
        // EN: WHY: the DSN string tells PDO the database type, host, name and character set. utf8mb4 supports all languages and emoji. EFFECT: Chinese text and symbols are stored correctly.
        // 中文: 为什么：DSN 字符串告诉 PDO 数据库类型、主机、库名和字符集；utf8mb4 支持所有语言和表情。效果：中文和特殊符号不会变成乱码。
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        // EN: the user name passed to MySQL. 中文: 传给 MySQL 的用户名。
        $DB_USER,
        // EN: the password passed to MySQL. 中文: 传给 MySQL 的密码。
        $DB_PASS,
        // EN: start of the options array. 中文: 选项数组开始。
        [
            // EN: WHY: by default PDO stays silent on SQL errors. EFFECT: now every SQL error throws an exception, so we can catch it and never miss a bug.
            // 中文: 为什么：PDO 默认遇到 SQL 错误不会报错。效果：现在出错会抛出异常，我们可以捕获，不会漏掉 bug。
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // EN: WHY: easier to read rows by column name. EFFECT: results look like ['title' => '1984'] instead of numbered arrays.
            // 中文: 为什么：用列名读取数据更直观。效果：查询结果是 ['title' => '1984'] 这种形式，而不是数字下标。
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // EN: WHY: real (server-side) prepared statements are safer against SQL injection. EFFECT: values are sent to MySQL separately from the SQL text.
            // 中文: 为什么：真正的（服务器端）预处理语句更能防 SQL 注入。效果：数据和 SQL 语句分开发送给 MySQL。
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
// EN: runs only if the code in try threw a PDOException; $e holds the error. 中文: 只有 try 里抛出 PDOException 时才执行；$e 保存错误信息。
} catch (PDOException $e) {
    // EN: WHY: 500 means "server error", which is the honest status. EFFECT: the browser/search engines know the page failed.
    // 中文: 为什么：500 表示服务器错误，是诚实的状态码。效果：浏览器知道这个页面出错了。
    http_response_code(500);
    // EN: WHY: show a helpful hint instead of a scary stack trace (which could leak passwords). EFFECT: prints the message and stops the whole page immediately.
    // 中文: 为什么：给出有用的提示，而不是可能泄露密码的错误堆栈。效果：输出提示并立刻终止整个页面。
    exit('Database connection failed. Start MySQL, import database.sql, then check the settings in includes/db.php.');
}
