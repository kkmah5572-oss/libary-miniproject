-- SmartLib database  (import this file in phpMyAdmin > Import, or run: mysql -u root < database.sql)
-- WARNING: re-importing resets all tables and sample data.
-- EN: Lines starting with "-- " are comments; MySQL ignores them. 中文: 以 "-- " 开头的行是注释，MySQL 会忽略。

-- EN: WHY: IF NOT EXISTS avoids an error if the database already exists. utf8mb4 stores all languages/emoji; the collation decides how text is compared/sorted (case-insensitive).
-- EN: EFFECT: creates the "smartlib" database.
-- 中文: 为什么：IF NOT EXISTS 让数据库已存在时不报错。utf8mb4 能存所有语言和表情；collation 决定文字如何比较/排序（不区分大小写）。
-- 中文: 效果：创建名为 smartlib 的数据库。
CREATE DATABASE IF NOT EXISTS smartlib CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- EN: EFFECT: all following commands run inside smartlib. 中文: 效果：后面的命令都在 smartlib 里执行。
USE smartlib;

-- EN: WHY: tables with foreign keys can't be dropped in any order, so we switch the checks off while dropping. 中文: 为什么：有外键的表不能随便顺序删除，所以删除时先关掉外键检查。
SET FOREIGN_KEY_CHECKS = 0;
-- EN: EFFECT: delete old tables (if any) so re-importing starts fresh. 中文: 效果：删除旧表（如果有），这样重新导入时是全新的。
DROP TABLE IF EXISTS borrowings;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS authors;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
-- EN: switch the safety checks back on. 中文: 把外键检查重新打开。
SET FOREIGN_KEY_CHECKS = 1;

-- 1. users ---------------------------------------------------------------
-- EN: Table 1: everyone who can log in. 中文: 表 1：所有能登录的人。
CREATE TABLE users (
  -- EN: unique number per user; AUTO_INCREMENT fills it automatically; PRIMARY KEY = the row's identity. 中文: 每个用户的唯一编号；AUTO_INCREMENT 自动递增；PRIMARY KEY 是这一行的身份标识。
  id         INT AUTO_INCREMENT PRIMARY KEY,
  -- EN: display name, up to 100 characters, cannot be empty (NOT NULL). 中文: 显示名，最多 100 个字符，不能为空（NOT NULL）。
  name       VARCHAR(100) NOT NULL,
  -- EN: UNIQUE = no two users can share an email (used for login). 中文: UNIQUE 表示两个用户不能用同一个邮箱（登录要用）。
  email      VARCHAR(100) NOT NULL UNIQUE,
  -- EN: 255 characters is enough for a password_hash() result. 中文: 255 个字符足够存放 password_hash() 的结果。
  password   VARCHAR(255) NOT NULL,
  -- EN: ENUM = only these three values allowed; default is student. 中文: ENUM 表示只允许这三个值；默认是 student。
  role       ENUM('admin','librarian','student') NOT NULL DEFAULT 'student',
  -- EN: automatically records when the account was created. 中文: 自动记录账号创建时间。
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
-- EN: InnoDB is the engine that supports foreign keys and transactions. 中文: InnoDB 是支持外键和事务的存储引擎。
) ENGINE=InnoDB;

-- 2. categories ----------------------------------------------------------
-- EN: Table 2: book categories. 中文: 表 2：图书分类。
CREATE TABLE categories (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  -- EN: UNIQUE stops duplicate category names. 中文: UNIQUE 防止分类名重复。
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 3. authors -------------------------------------------------------------
-- EN: Table 3: authors (kept separate so one author can have many books). 中文: 表 3：作者（单独成表，一个作者可以有多本书）。
CREATE TABLE authors (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 4. books  (FK -> authors, categories) ----------------------------------
-- EN: Table 4: books, linked to authors and categories. 中文: 表 4：图书，关联作者和分类。
CREATE TABLE books (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(200) NOT NULL,
  -- EN: which author wrote it (a number that points to authors.id). 中文: 作者是谁（指向 authors.id 的编号）。
  author_id        INT NOT NULL,
  -- EN: which category it belongs to (points to categories.id). 中文: 属于哪个分类（指向 categories.id）。
  category_id      INT NOT NULL,
  -- EN: NULL = unknown year; SMALLINT is enough for years. 中文: NULL 表示年份未知；SMALLINT 足够存年份。
  year_published   SMALLINT NULL,
  -- EN: TEXT = long text, optional. 中文: TEXT 是长文本，可为空。
  description      TEXT NULL,
  -- EN: how many copies the library owns (default 1). 中文: 图书馆拥有多少册（默认 1）。
  total_copies     INT NOT NULL DEFAULT 1,
  -- EN: how many are on the shelf right now (default 1). 中文: 现在书架上有多少册（默认 1）。
  available_copies INT NOT NULL DEFAULT 1,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- EN: FOREIGN KEY: author_id must match a real author, and an author with books can't be deleted. 中文: FOREIGN KEY（外键）：author_id 必须对应真实存在的作者，有书的作者不能被删除。
  CONSTRAINT fk_books_author   FOREIGN KEY (author_id)   REFERENCES authors(id),
  -- EN: same idea for categories. 中文: 分类同理。
  CONSTRAINT fk_books_category FOREIGN KEY (category_id) REFERENCES categories(id),
  -- EN: CHECK: the database itself refuses impossible stock (negative, or more than the total). 中文: CHECK：数据库自己拒绝不可能的库存（负数，或超过总数）。
  CONSTRAINT chk_copies CHECK (available_copies >= 0 AND available_copies <= total_copies)
) ENGINE=InnoDB;

-- 5. borrowings  (FK -> users, books) ------------------------------------
-- EN: Table 5: one row per loan, linking a user to a book. 中文: 表 5：每次借阅一行，把用户和书连起来。
CREATE TABLE borrowings (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  -- EN: who borrowed (points to users.id). 中文: 谁借的（指向 users.id）。
  user_id     INT NOT NULL,
  -- EN: which book (points to books.id). 中文: 借的哪本书（指向 books.id）。
  book_id     INT NOT NULL,
  -- EN: DATE = year-month-day without time. 中文: DATE 只有年月日，没有时间。
  borrow_date DATE NOT NULL,
  -- EN: the deadline to bring it back. 中文: 归还的最后期限。
  due_date    DATE NOT NULL,
  -- EN: NULL until the book is returned. 中文: 书归还之前为 NULL。
  return_date DATE NULL,
  -- EN: current state of the loan. 中文: 借阅当前的状态。
  status      ENUM('borrowed','returned') NOT NULL DEFAULT 'borrowed',
  -- EN: foreign keys: loans can't point to missing users/books, and users/books with loans can't be deleted. 中文: 外键：借阅不能指向不存在的用户/书；有借阅记录的用户/书不能被删除。
  CONSTRAINT fk_borrow_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_borrow_book FOREIGN KEY (book_id) REFERENCES books(id)
) ENGINE=InnoDB;

-- Sample data -------------------------------------------------------------
-- Demo logins:  admin@smartlib.test / admin123
--               librarian@smartlib.test / librarian123
--               student@smartlib.test / student123   (also: mei@smartlib.test, arif@smartlib.test, same password)
-- EN: Sample data so the site isn't empty when you first open it. 中文: 示例数据，让网站第一次打开时不是空的。

-- EN: EFFECT: 5 users. The long "$2y$10$..." text is the password hashed with password_hash(), not the real password. id is automatic (1..5). 
-- 中文: 效果：5 个用户。那一长串 "$2y$10$..." 是 password_hash() 加密后的密码，不是真实密码。id 自动生成（1..5）。
INSERT INTO users (name, email, password, role) VALUES
('Site Admin',      'admin@smartlib.test',     '$2y$10$We7LodGHvTPvAMnrKagN2uTUswX4HvdJUFrrARd6AIQm/Bk5owpe6', 'admin'),
('Lena Librarian',  'librarian@smartlib.test', '$2y$10$QZx3iQ7uuAxdZuF9pF5ukey2iokC8gAf2klHAj7kakhB8lJdMlGA2', 'librarian'),
('Sam Student',     'student@smartlib.test',   '$2y$10$VhIeOrviO7E4fj3JV4ADZ.2oSFxkxBRsBAbwfz54bc4dErndjZLzi', 'student'),
('Mei Lin Tan',     'mei@smartlib.test',       '$2y$10$VhIeOrviO7E4fj3JV4ADZ.2oSFxkxBRsBAbwfz54bc4dErndjZLzi', 'student'),
('Arif Hakim',      'arif@smartlib.test',      '$2y$10$VhIeOrviO7E4fj3JV4ADZ.2oSFxkxBRsBAbwfz54bc4dErndjZLzi', 'student');

-- EN: EFFECT: 6 categories (ids 1..6 in this order). 中文: 效果：6 个分类（按此顺序 id 为 1..6）。
INSERT INTO categories (name) VALUES
('Fiction'),('Science'),('Technology'),('History'),('Self-Help'),('Philosophy');

-- EN: EFFECT: 16 authors; the number in each comment is its id, which books.author_id refers to. 中文: 效果：16 位作者；每行注释里的数字是它的 id，books.author_id 引用的就是这个数字。
INSERT INTO authors (name) VALUES
('George Orwell'),               -- 1
('Harper Lee'),                  -- 2
('Yuval Noah Harari'),           -- 3
('Stephen Hawking'),             -- 4
('Robert C. Martin'),            -- 5
('James Clear'),                 -- 6
('Antoine de Saint-Exupéry'),    -- 7
('Luke Welling'),                -- 8
('F. Scott Fitzgerald'),         -- 9
('Dale Carnegie'),               -- 10
('Carl Sagan'),                  -- 11
('Marcus Aurelius'),             -- 12
('Paulo Coelho'),                -- 13
('Andrew Hunt'),                 -- 14
('Jane Austen'),                 -- 15
('Richard Dawkins');             -- 16

-- EN: EFFECT: 16 books. Column order: title, author_id, category_id, year, description, total_copies, available_copies. Available < total where a book is currently on loan. 
-- EN: Note: 'humanity''s' uses two single quotes to write one apostrophe inside a text value.
-- 中文: 效果：16 本书。列顺序：书名、作者 id、分类 id、年份、简介、总册数、可借册数。有书正被借出时，可借 < 总数。
-- 中文: 注意：'humanity''s' 用两个单引号表示文字里的一个撇号。
INSERT INTO books (title, author_id, category_id, year_published, description, total_copies, available_copies) VALUES
('1984',                                   1, 1, 1949, 'A dystopian novel about surveillance, propaganda and the control of truth.', 5, 4),
('To Kill a Mockingbird',                  2, 1, 1960, 'A young girl watches her father defend a Black man accused of a crime in the American South.', 4, 4),
('Sapiens',                                3, 4, 2011, 'A brief history of humankind, from the first tools to the age of biotechnology.', 3, 2),
('A Brief History of Time',                4, 2, 1988, 'An accessible introduction to black holes, the Big Bang and the nature of time.', 3, 3),
('Clean Code',                             5, 3, 2008, 'A handbook of agile software craftsmanship: how to write code other people can read.', 4, 3),
('Atomic Habits',                          6, 5, 2018, 'Small, consistent changes that compound into remarkable results.', 6, 5),
('The Little Prince',                      7, 1, 1943, 'A pilot stranded in the desert meets a prince who has travelled from a tiny planet.', 5, 5),
('PHP and MySQL Web Development',          8, 3, 2016, 'A practical guide to building database-driven websites with PHP and MySQL.', 3, 2),
('The Great Gatsby',                       9, 1, 1925, 'Wealth, longing and illusion in the Jazz Age.', 4, 4),
('How to Win Friends and Influence People',10, 5, 1936, 'Classic advice on communication and building relationships.', 4, 4),
('Cosmos',                                11, 2, 1980, 'A tour of the universe and of humanity''s place within it.', 3, 3),
('Meditations',                           12, 6, 180,  'Private notes on duty, discipline and equanimity by a Roman emperor.', 3, 3),
('The Alchemist',                         13, 1, 1988, 'A shepherd boy travels from Spain to Egypt in search of treasure and purpose.', 4, 4),
('The Pragmatic Programmer',              14, 3, 1999, 'Practical habits for developers who want to keep improving their craft.', 3, 3),
('Pride and Prejudice',                   15, 1, 1813, 'Manners, money and misjudgement in Regency England.', 4, 4),
('The Selfish Gene',                      16, 2, 1976, 'A gene-centred view of evolution that introduced the idea of the meme.', 3, 3);

-- Borrowings use dates relative to today so the overdue list always has data.
-- Active loans (these match the reduced available_copies above)
-- EN: WHY: dates are calculated from today (CURDATE), so the sample always has loans due soon and some overdue, whenever you import. DATE_SUB = minus days, DATE_ADD = plus days.
-- EN: Column order: user_id, book_id, borrow_date, due_date, return_date, status. NULL return_date = not returned yet.
-- EN: Each active loan matches one missing copy in books.available_copies above.
-- 中文: 为什么：日期从今天（CURDATE）推算，所以无论什么时候导入，示例里都有快到期和已逾期的借阅。DATE_SUB = 减天数，DATE_ADD = 加天数。
-- 中文: 列顺序：用户 id、书 id、借出日、到期日、归还日、状态。归还日为 NULL 表示还没还。
-- 中文: 每条在借记录都对应上面 books.available_copies 里少掉的一册。
INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, return_date, status) VALUES
-- EN: Sam has "1984": due in 9 days (not late). 中文: Sam 借了《1984》：9 天后到期（未逾期）。
(3,  1, DATE_SUB(CURDATE(), INTERVAL 5  DAY),  DATE_ADD(CURDATE(), INTERVAL 9 DAY),  NULL, 'borrowed'),
-- EN: Sam has "Clean Code": 6 days overdue. 中文: Sam 借了《Clean Code》：已逾期 6 天。
(3,  5, DATE_SUB(CURDATE(), INTERVAL 20 DAY),  DATE_SUB(CURDATE(), INTERVAL 6 DAY),  NULL, 'borrowed'),
-- EN: Mei has "Sapiens": 11 days overdue. 中文: Mei 借了《Sapiens》：已逾期 11 天。
(4,  3, DATE_SUB(CURDATE(), INTERVAL 25 DAY),  DATE_SUB(CURDATE(), INTERVAL 11 DAY), NULL, 'borrowed'),
-- EN: Arif has the PHP book: due in 12 days. 中文: Arif 借了 PHP 那本书：12 天后到期。
(5,  8, DATE_SUB(CURDATE(), INTERVAL 2  DAY),  DATE_ADD(CURDATE(), INTERVAL 12 DAY), NULL, 'borrowed'),
-- EN: Mei has "Atomic Habits": due in 13 days. 中文: Mei 借了《Atomic Habits》：13 天后到期。
(4,  6, DATE_SUB(CURDATE(), INTERVAL 1  DAY),  DATE_ADD(CURDATE(), INTERVAL 13 DAY), NULL, 'borrowed');
-- Returned loans (history)
-- EN: EFFECT: 4 finished loans for the "Past borrowings" list. They have a return_date and status 'returned', so they don't change the stock. 
-- 中文: 效果：4 条已完成的借阅，用于 "Past borrowings" 列表。它们有归还日且状态为 'returned'，不影响库存。
INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, return_date, status) VALUES
(3,  6, DATE_SUB(CURDATE(), INTERVAL 45 DAY),  DATE_SUB(CURDATE(), INTERVAL 31 DAY), DATE_SUB(CURDATE(), INTERVAL 33 DAY), 'returned'),
(4,  2, DATE_SUB(CURDATE(), INTERVAL 40 DAY),  DATE_SUB(CURDATE(), INTERVAL 26 DAY), DATE_SUB(CURDATE(), INTERVAL 28 DAY), 'returned'),
(5,  1, DATE_SUB(CURDATE(), INTERVAL 60 DAY),  DATE_SUB(CURDATE(), INTERVAL 46 DAY), DATE_SUB(CURDATE(), INTERVAL 44 DAY), 'returned'),
(3, 12, DATE_SUB(CURDATE(), INTERVAL 30 DAY),  DATE_SUB(CURDATE(), INTERVAL 16 DAY), DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'returned');
