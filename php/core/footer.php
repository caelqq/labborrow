</main>
<footer class="site-footer">
    © 2026 Aeon Barrachael B. Alos. All rights reserved.
</footer>
<?php if (!empty($_SESSION['user_id'])): ?>
    <?php require_once __DIR__ . '/../auth/auth.php'; ?>
    <script src="js/idle.js" data-limit="<?= IDLE_SECONDS ?>" data-warn="<?= IDLE_WARN_SECONDS ?>"></script>
<?php endif; ?>
</body>
</html>