<?php
// EN: load session, database and helpers (one folder up, because this file is inside /admin/). 中文: 加载 session、数据库和辅助函数（因为本文件在 /admin/ 里，所以要往上一级）。
require __DIR__ . '/../includes/init.php';
// EN: load the shared function that draws the manage page. 中文: 加载负责绘制管理页面的公共函数。
require __DIR__ . '/../includes/lookup.php';
// EN: WHY: same page code as categories, only the table/words differ. EFFECT: shows the "Authors" management page (staff only, checked inside). 
// 中文: 为什么：和分类页是同一套代码，只有表名和用词不同。效果：显示 "Authors" 管理页（权限在函数内部检查，仅员工可用）。
render_lookup_page('authors', 'author', 'authors');
