# SmartLib 图书馆管理系统

PHP + MySQL + HTML + CSS。四种角色：Admin、Librarian、Student、Guest。


## 测试账号

| 角色 | 邮箱 | 密码 |
|---|---|---|
| Admin | admin@smartlib.test | admin123 |
| Librarian | librarian@smartlib.test | librarian123 |
| Student | student@smartlib.test | student123 |

另有学生 mei@smartlib.test 和 arif@smartlib.test（密码同样是 student123）。
任何人也可以点 **Create account** 自己注册，注册出来的一定是 Student。

## 页面如何互相连接

```
Guest ── index.php ─┬─ books.php ── book.php ──(Log in to borrow)──> login.php?next=book.php?id=..
                    ├─ register.php ──(成功)──> login.php
                    └─ login.php ──(成功)──> Student: books.php / 员工: admin/dashboard.php

Student ─ book.php ──[Borrow]──> borrow.php ──> my-borrowings.php
Librarian ─ admin/return.php ──[Mark returned]──> 书的库存 +1 ──> 学生的记录变成 Returned
Admin/Librarian ─ admin/books.php ⇄ admin/book-form.php   (分类、作者来自 categories.php / authors.php)
所有角色 ─ 右上角 Log out ──> logout.php ──> login.php
```

## 权限表（与你的 Data Management System 表一致）

| 数据 | Create | Read | Update | Delete |
|---|---|---|---|---|
| Users | Admin | Admin | Admin | Admin |
| Books / Categories / Authors | Admin, Librarian | 所有人 | Admin, Librarian | Admin, Librarian |
| Borrowings | Student | Admin, Librarian, Student | Librarian | Admin |

## 业务规则

- 借期 14 天，每个学生最多同时借 3 本（在 `includes/init.php` 里修改 `LOAN_DAYS` 和 `MAX_ACTIVE_LOANS`）。
- 同一本书不能重复借；没有库存不能借。
- 还书由 Librarian 处理；Admin 可以在 Borrowings 页面删除记录。
- 有借阅记录的书、用户不能被删除（数据库外键保护）。

## 文件结构

```
smartlib/
├── database.sql            建库、5 张表、示例数据
├── index.php  books.php  book.php
├── register.php  login.php  logout.php
├── borrow.php  my-borrowings.php
├── admin/
│   ├── dashboard.php  books.php  book-form.php
│   ├── categories.php  authors.php
│   ├── borrowings.php  overdue.php  return.php
│   └── users.php  user-form.php
├── includes/
│   ├── db.php              数据库连接（改密码在这里）
│   ├── init.php            session、辅助函数、权限检查、CSRF
│   ├── components.php      书籍卡片、状态标签、分页
│   ├── header.php  footer.php   导航栏会按登录状态/角色变化
│   └── lookup.php          分类和作者共用的管理页面
└── css/style.css
```

## 数据库（5 张表，5 个外键关系）

- `books.author_id` -> `authors.id`
- `books.category_id` -> `categories.id`
- `borrowings.user_id` -> `users.id`
- `borrowings.book_id` -> `books.id`

（`categories` 和 `authors` 各对应多本书，`users` 和 `books` 各对应多条借阅记录。）

## 安全措施（演示时可以讲）

- 密码用 `password_hash()` 保存，用 `password_verify()` 验证
- 所有 SQL 使用 PDO 预处理语句（防 SQL 注入）
- 所有输出用 `htmlspecialchars`（防 XSS）
- 所有 POST 表单带 CSRF 令牌
- 登录成功后 `session_regenerate_id()`，登出时彻底销毁 session
- 借书使用数据库事务和行锁，避免两个人同时借走最后一本
