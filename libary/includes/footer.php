<?php // EN: closes the <main> opened in header.php, so every page's content sits inside it. | 中文: 关闭 header.php 里打开的 <main>，让每个页面的内容都在它里面。 ?>
</main>
<?php // EN: the page footer, same on every page. | 中文: 页面底部，每页都一样。 ?>
<footer class="site-footer">
    <div class="wrap footer-row">
        <span>SmartLib library system</span>
        <?php // EN: WHY: prints the constants from init.php, so the text always matches the real rules. | 中文: 为什么：直接输出 init.php 里的常量，文字总是和真实规则一致。 ?>
        <span>Loans last <?= LOAN_DAYS ?> days. Students can hold up to <?= MAX_ACTIVE_LOANS ?> books.</span>
    </div>
</footer>
<?php // EN: closes the <body> and <html> tags opened in header.php. | 中文: 关闭 header.php 里打开的 <body> 和 <html> 标签。 ?>
</body>
</html>
