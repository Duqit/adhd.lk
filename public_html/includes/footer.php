    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-col" style="max-width: 320px;">
                <a href="/" class="logo" style="margin-bottom: 0.75rem;">ADHD.lk</a>
                <p style="font-size: 0.88rem; line-height: 1.6;"><?php echo t('footer_tagline'); ?></p>
            </div>
            <div class="footer-col">
                <h4><?php echo t('footer_tools'); ?></h4>
                <a href="/timer"><?php echo t('footer_timer'); ?></a>
                <a href="/dashboard"><?php echo t('footer_planner'); ?></a>
                <a href="/routines"><?php echo t('footer_routines'); ?></a>
                <a href="/habits"><?php echo t('footer_habits'); ?></a>
            </div>
            <div class="footer-col">
                <h4><?php echo t('footer_wellbeing'); ?></h4>
                <a href="/journal"><?php echo t('footer_journal'); ?></a>
                <a href="/breathe"><?php echo t('footer_calm'); ?></a>
                <a href="/assessment"><?php echo t('footer_selftest'); ?></a>
                <a href="/articles"><?php echo t('footer_articles'); ?></a>
                <a href="/article/<?php echo t('dir_slug'); ?>"><?php echo t('footer_help_sl'); ?></a>
            </div>
            <div class="footer-col">
                <h4><?php echo t('footer_site_col'); ?></h4>
                <a href="/about"><?php echo t('footer_about'); ?></a>
                <a href="/editorial-policy"><?php echo t('footer_editorial'); ?></a>
                <a href="/contact"><?php echo t('footer_contact'); ?></a>
            </div>
            <div class="footer-col">
                <h4><?php echo t('footer_legal'); ?></h4>
                <a href="/privacy"><?php echo t('footer_privacy'); ?></a>
                <a href="/terms"><?php echo t('footer_terms'); ?></a>
                <a href="/data_deletion_instructions"><?php echo t('footer_deletion'); ?></a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo t('footer_copyright'); ?></p>
            <p class="footer-credit"><?php echo sprintf(t('footer_developed_by'), '<a href="https://duqit.com" rel="noopener">Duqit Labs</a>'); ?> · <a href="https://github.com/duqit/adhd.lk" rel="noopener"><?php echo t('footer_open_source'); ?></a></p>
        </div>
    </footer>
    <script src="/js/store.js?v=20260828a"></script>
    <script src="/js/global.js?v=20260828a"></script>
</body>
</html>
