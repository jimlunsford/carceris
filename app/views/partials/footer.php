    </main>

    <footer class="site-footer">
        <div class="site-footer__brand">
            <a href="https://carceris.org/" target="_blank" rel="noopener noreferrer">Carceris</a>
            <span>- Secure daily logging for correctional facilities.</span>
        </div>

        <div class="site-footer__version">
            <span>Version <?= e(app_config('app', 'version', '0.6.11')) ?></span>
            <span>Licensed under AGPLv3</span>
        </div>
    </footer>
    <script src="/assets/js/app.js?v=<?= e(app_config('app', 'version', '0.6.11')) ?>" defer></script>
</body>
</html>
