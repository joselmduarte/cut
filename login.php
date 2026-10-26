<?php
require __DIR__ . '/db.php';

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (login_user($username, $password)) {
        header('Location: index.php');
        exit;
    }

    $notice = 'Credenciais inválidas. Tenta outra vez.';
}

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$site_title = 'Entrar | Futsal Cuts';
$hide_topbar = true;
include __DIR__ . '/partials/header.php';
?>

<div class="container auth-shell">
    <div class="auth-card">
        <div class="auth-head"><img src="assets/logo-tid.png" alt="Talent ID" height="42"></div>
        <div class="auth-body">
            <?php if ($notice !== ''): ?>
                <div class="alert danger"><?= htmlspecialchars($notice) ?></div>
            <?php endif; ?>

            <form method="post" class="stacked-form auth-form">
                <?= csrf_field() ?>
                <input type="hidden" name="login" value="1">

                <label>
                    <span>Utilizador</span>
                    <input type="text" name="username" autocomplete="username" required autofocus>
                </label>

                <label>
                    <span>Palavra-passe</span>
                    <div class="pw">
                        <input type="password" name="password" autocomplete="current-password" required>
                        <button type="button" class="pw-eye" aria-label="Mostrar ou esconder a palavra-passe">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </label>

                <button class="button accent block" type="submit">Entrar</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
