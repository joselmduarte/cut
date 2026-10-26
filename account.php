<?php
require __DIR__ . '/db.php';
require_login();

$user = current_user();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['password'] ?? '');
    $new2 = (string)($_POST['password2'] ?? '');
    if (!password_verify($cur, (string)$user['pass_hash'])) {
        $err = 'A palavra-passe atual não está correta.';
    } elseif (strlen($new) < 8) {
        $err = 'A nova palavra-passe precisa de pelo menos 8 caracteres.';
    } elseif ($new !== $new2) {
        $err = 'As palavras-passe novas não coincidem.';
    } else {
        $users = load_users();
        foreach ($users as $i => $u) {
            if (strtolower($u['username']) === strtolower($user['username'])) {
                $users[$i]['pass_hash'] = password_hash($new, PASSWORD_DEFAULT);
            }
        }
        save_users($users);
        $msg = 'Palavra-passe alterada.';
    }
}

$site_title = 'Conta | Futsal Cuts';
include __DIR__ . '/partials/header.php';
?>
<div class="container account-page">
    <?= crumbs([['Início', 'index.php'], 'Conta']) ?>
    <section class="page-hero slim">
        <div>
            <p class="eyebrow">Conta</p>
            <h1><?= htmlspecialchars($user['username']) ?></h1>
            <p class="muted">Perfil: <?= ['admin' => 'Administrador', 'editor' => 'Editor', 'viewer' => 'Só visualização'][$user['role']] ?? '' ?></p>
        </div>
    </section>
    <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <div class="panel form-panel">
        <h3>Alterar palavra-passe</h3>
        <form method="post" class="stacked-form">
            <?= csrf_field() ?>
            <div class="field-grid">
                <label><span>Palavra-passe atual</span><input type="password" name="current" autocomplete="current-password" required></label>
                <label><span>Nova palavra-passe</span><input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
                <label><span>Repetir nova</span><input type="password" name="password2" minlength="8" autocomplete="new-password" required></label>
            </div>
            <div class="form-actions"><button class="button accent" type="submit">Guardar</button></div>
        </form>
    </div>
    <section class="panel install-panel">
        <h3>Instalar como aplicação</h3>
        <p class="muted">Funciona no tablet Windows, Android e iPad. Precisa do site em HTTPS.</p>
        <ul class="install-steps">
            <li><strong>Windows (Edge ou Chrome):</strong> menu ⋯ → Aplicações → Instalar este site como aplicação (ou o botão «Instalar app» no menu do utilizador).</li>
            <li><strong>Android (Chrome):</strong> menu ⋮ → Instalar aplicação / Adicionar ao ecrã principal.</li>
            <li><strong>iPad / iPhone (Safari):</strong> Partilhar → Adicionar ao ecrã principal.</li>
        </ul>
        <button type="button" class="button accent" id="installBtn2" hidden><?= icon('install') ?> Instalar agora</button>
    </section>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
