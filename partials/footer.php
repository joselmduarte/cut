<?php
$site_title = $site_title ?? 'Futsal Cuts';
?>
        </main>
        <footer class="site-foot">© <?= date('Y') ?> Talent ID · Futsal Cuts</footer>
    </div>
    <script src="<?= asset('assets/app.js') ?>"></script>
    <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register('sw.js', { updateViaCache: 'none' }).catch(function () {});
      });
    }
    </script>
</body>
</html>
