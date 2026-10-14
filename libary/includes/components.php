<?php
// Reusable pieces of HTML.
// EN: WHY: the same book card / badge / pager appears on many pages; writing it once avoids copy-paste. 中文: 为什么：书籍卡片、状态标签、分页在很多页面都出现，写一次就不用复制粘贴。

// A book shown as a library index card.
// EN: Takes one book row ($b, an array from the database) and returns its HTML card as text. 中文: 接收一本书的数据 $b（来自数据库的数组），返回它的 HTML 卡片文字。
function book_card(array $b): string
{
    // EN: (int) forces a whole number. EFFECT: $avail = copies on the shelf now. 中文: (int) 强制转成整数。效果：$avail = 现在书架上的册数。
    $avail = (int) $b['available_copies'];
    // EN: EFFECT: $total = all copies the library owns. 中文: 效果：$total = 图书馆拥有的总册数。
    $total = (int) $b['total_copies'];
    // EN: WHY: the CSS has 8 colours (cat-1..cat-8). "% 8" loops category ids back into 1-8. EFFECT: each category gets a colour class name. 
    // 中文: 为什么：CSS 里有 8 种颜色（cat-1 到 cat-8）。"% 8" 让分类 id 循环落在 1-8。效果：每个分类得到一个颜色类名。
    $cat   = 'cat-' . ((((int) $b['category_id']) - 1) % 8 + 1);
    // EN: "??" gives '' if the description is missing (NULL). 中文: "??" 表示描述为空（NULL）时用空字符串。
    $desc  = $b['description'] ?? '';
    // EN: WHY: long text would break the card layout. mb_strlen counts characters correctly (also Chinese). 中文: 为什么：文字太长会撑坏卡片。mb_strlen 能正确计算字符数（包括中文）。
    if (mb_strlen($desc) > 120) {
        // EN: EFFECT: keep the first 117 characters and add "..." (total 120). 中文: 效果：保留前 117 个字符再加 "..."（总共 120）。
        $desc = mb_substr($desc, 0, 117) . '...';
    }
    // EN: WHY: show stock status at a glance. "cond ? A : B" means: if true use A, else B. 中文: 为什么：让人一眼看到库存状态。"条件 ? A : B" 表示为真用 A，否则用 B。
    $chip = $avail > 0
        // EN: in stock: green label like "4 of 5 on the shelf". 中文: 有库存：绿色标签，例如 "4 of 5 on the shelf"。
        ? '<span class="chip chip-ok">' . $avail . ' of ' . $total . ' on the shelf</span>'
        // EN: out of stock: red label. 中文: 没库存：红色标签。
        : '<span class="chip chip-out">All copies out</span>';

    // EN: WHY: ob_start() starts "capturing" output, so we can write normal HTML below and still return it as text. 中文: 为什么：ob_start() 开始"捕获"输出，这样下面可以直接写 HTML，又能把它当文字返回。
    ob_start(); ?>
    <?php // EN: <article> = one self-contained card; $cat adds its colour class. | 中文: <article> 表示一张独立的卡片；$cat 加上颜色类名。 ?>
    <article class="book-card <?= $cat ?>">
        <?php // EN: category tab; links to the list filtered by this category. | 中文: 分类标签；链接到按该分类筛选的列表。 ?>
        <a class="book-tab" href="<?= e(url('books.php?category=' . (int) $b['category_id'])) ?>"><?= e($b['category_name']) ?></a>
        <?php // EN: header part of the card. | 中文: 卡片的头部。 ?>
        <header class="book-head">
            <?php // EN: the title is a link to the detail page; e() makes the text safe. | 中文: 标题是通往详情页的链接；e() 让文字安全。 ?>
            <h3><a href="<?= e(url('book.php?id=' . (int) $b['id'])) ?>"><?= e($b['title']) ?></a></h3>
            <?php // EN: author, plus ", year" only if a year exists. | 中文: 作者；只有存在年份时才加上 ", 年份"。 ?>
            <p class="book-author"><?= e($b['author_name']) ?><?= $b['year_published'] ? ', ' . (int) $b['year_published'] : '' ?></p>
        </header>
        <?php // EN: the shortened description. | 中文: 截短后的简介。 ?>
        <p class="book-desc"><?= e($desc) ?></p>
        <?php // EN: footer: stock label and a Details link. | 中文: 底部：库存标签和 Details 链接。 ?>
        <footer class="book-foot">
            <?php // EN: $chip is our own safe HTML, so no e() here. | 中文: $chip 是我们自己生成的安全 HTML，所以这里不用 e()。 ?>
            <?= $chip ?>
            <a class="link-strong" href="<?= e(url('book.php?id=' . (int) $b['id'])) ?>">Details</a>
        </footer>
    </article>
    <?php
    // EN: EFFECT: stop capturing and return everything captured as one string. 中文: 效果：停止捕获，并把捕获到的内容作为一个字符串返回。
    return ob_get_clean();
}

// Status badge for a loan row. $row needs: status, is_overdue (0/1)
// EN: WHY: the same "Returned / Overdue / On loan" label is used in many tables. 中文: 为什么："已归还/逾期/借出中"标签在很多表格里都用到。
function loan_badge(array $row): string
{
    // EN: if the book was returned... 中文: 如果书已归还……
    if ($row['status'] === 'returned') {
        // EN: EFFECT: grey "Returned" label. 中文: 效果：灰色的 "Returned" 标签。
        return '<span class="chip chip-muted">Returned</span>';
    }
    // EN: !empty() is safe even if the key is missing or 0. Overdue = red, otherwise green "On loan". 中文: !empty() 即使键不存在或为 0 也安全。逾期显示红色，否则显示绿色 "On loan"。
    return !empty($row['is_overdue'])
        ? '<span class="chip chip-out">Overdue</span>'
        : '<span class="chip chip-ok">On loan</span>';
}

// Page numbers for lists.
// EN: $page = current page, $pages = total pages, $query = other URL parameters to keep (like search text). 中文: $page 是当前页，$pages 是总页数，$query 是要保留的其他网址参数（比如搜索词）。
function pagination(int $page, int $pages, array $query = []): string
{
    // EN: WHY: one page needs no page numbers. EFFECT: returns nothing. 中文: 为什么：只有一页就不需要页码。效果：什么都不返回。
    if ($pages <= 1) {
        return '';
    }
    // EN: start the HTML text. 中文: 开始拼 HTML 文字。
    $html = '<nav class="pager" aria-label="Pages">';
    // EN: loop from page 1 to the last page. 中文: 从第 1 页循环到最后一页。
    for ($i = 1; $i <= $pages; $i++) {
        // EN: WHY: keep the search filters in each link. http_build_query turns an array into "q=php&page=2". 中文: 为什么：每个链接都要保留搜索条件。http_build_query 把数组变成 "q=php&page=2"。
        $q = http_build_query(array_merge($query, ['page' => $i]));
        // EN: current page = plain highlighted number; other pages = links. ".=" appends to $html. 中文: 当前页是高亮的普通数字；其他页是链接。".=" 表示追加到 $html。
        $html .= $i === $page
            ? '<span class="pager-current">' . $i . '</span>'
            : '<a href="?' . e($q) . '">' . $i . '</a>';
    }
    // EN: close the <nav> tag and return the finished HTML. 中文: 关闭 <nav> 标签并返回完成的 HTML。
    return $html . '</nav>';
}
