<?php
$site_title = $site_title ?? 'Futsal Cuts';
$nav_page = [
    'index.php' => 'games', 'game.php' => 'games', 'presentation.php' => 'games', 'export.php' => 'games',
    'moments.php' => 'moments', 'board.php' => 'board', 'tags.php' => 'tags',
    'users.php' => 'user', 'account.php' => 'user',
][basename($_SERVER['SCRIPT_NAME'] ?? '')] ?? '';
$nav_cls = fn (string $k) => $nav_page === $k ? ' class="active"' : '';
$user = function_exists('current_user') ? current_user() : null;
$user_label = is_array($user) ? ($user['name'] ?? $user['username'] ?? 'Conta') : 'Conta';
?>
<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars($site_title) ?></title>
    <meta name="theme-color" content="#2e4052">
    <meta name="application-name" content="Futsal Cuts">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Futsal Cuts">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="<?= asset('assets/styles.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/theme.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/tid.css') ?>">
</head>
<body class="role-<?= htmlspecialchars(current_role() ?: 'guest') ?>">
<?php include __DIR__ . '/icons.php'; ?>
    <div class="app-shell">
        <header class="topbar" id="topbar">
            <div class="container topbar-inner">
                <?php if (is_logged_in()): ?>
                <button type="button" class="nav-toggle" id="navToggle" aria-label="Menu" aria-expanded="false" aria-controls="mainNav"><?= icon('menu') ?></button>
                <?php endif; ?>

                <a href="index.php" class="brand-logo" aria-label="Talent ID · Futsal Cuts">
                    <img src="assets/logo-tid.png" alt="Talent ID" height="30">
                    <span class="brand-product">Futsal Cuts</span>
                </a>

                <?php if (is_logged_in()): ?>
                <nav class="nav" id="mainNav" aria-label="Navegação principal">
                    <a href="index.php"<?= $nav_cls('games') ?>><?= icon('video') ?><span>Jogos</span></a>
                    <a href="moments.php"<?= $nav_cls('moments') ?>><?= icon('film') ?><span>Momentos</span></a>
                    <a href="board.php"<?= $nav_cls('board') ?>><?= icon('pitch') ?><span>Quadro</span></a>
                    <?php if (can_edit()): ?><a href="tags.php"<?= $nav_cls('tags') ?>><?= icon('tag') ?><span>Tags</span></a><?php endif; ?>

                    <div class="nav-right">
                        <form class="top-search" action="moments.php" method="get" role="search">
                            <input type="search" name="q" placeholder="Procura" aria-label="Procurar momentos">
                            <button type="submit" aria-label="Procurar"><?= icon('search') ?></button>
                        </form>

                        <div class="nav-user" id="navUser">
                            <button type="button" class="nav-user-btn" id="navUserBtn" aria-haspopup="true" aria-expanded="false" title="<?= htmlspecialchars($user_label) ?>">
                                <?= icon('user') ?>
                                <span class="nav-user-name"><?= htmlspecialchars($user_label) ?></span>
                                <?= icon('chevron-down', 'chev') ?>
                            </button>
                            <div class="nav-user-menu" id="navUserMenu" hidden>
                                <a href="account.php"<?= $nav_page === 'user' ? ' class="active"' : '' ?>><?= icon('user') ?><span>Conta</span></a>
                                <?php if (is_admin()): ?><a href="users.php"><?= icon('users') ?><span>Utilizadores</span></a><?php endif; ?>
                                <button type="button" class="menu-item" id="installBtn" hidden><?= icon('install') ?><span>Instalar app</span></button>
                                <p class="install-hint" id="installHint" hidden>No iPad/iPhone: toque em Partilhar e depois «Adicionar ao ecrã principal».</p>
                                <a href="logout.php" class="nav-logout"><?= icon('logout') ?><span>Sair</span></a>
                            </div>
                        </div>
                    </div>
                </nav>
                <?php endif; ?>
            </div>
        </header>

        <main>
