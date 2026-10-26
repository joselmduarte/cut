<?php
require __DIR__ . '/db.php';
require_login();
require_admin();

$roles = ['admin' => 'Administrador', 'editor' => 'Editor', 'viewer' => 'Só visualização'];
$me = current_user()['username'];
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');
    $name = trim((string)($_POST['username'] ?? ''));
    $users = load_users();
    $admins = count(array_filter($users, fn ($u) => ($u['role'] ?? '') === 'admin'));
    $idx = null;
    foreach ($users as $i => $u) {
        if (strtolower($u['username']) === strtolower($name)) {
            $idx = $i;
        }
    }
    $role = (string)($_POST['role'] ?? 'viewer');
    $pass = (string)($_POST['password'] ?? '');

    if ($act === 'add') {
        if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $name)) {
            $err = 'Nome de utilizador: 3 a 32 caracteres (letras, números, ponto, hífen, underscore).';
        } elseif ($idx !== null) {
            $err = 'Esse utilizador já existe.';
        } elseif (strlen($pass) < 8 || !isset($roles[$role])) {
            $err = 'A palavra-passe precisa de pelo menos 8 caracteres e o perfil tem de ser válido.';
        } else {
            $users[] = ['username' => $name, 'pass_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => $role, 'created_at' => date('c')];
            save_users($users);
            $msg = 'Utilizador criado.';
        }
    } elseif ($idx === null) {
        $err = 'Utilizador não encontrado.';
    } elseif ($act === 'role') {
        if (!isset($roles[$role])) {
            $err = 'Perfil inválido.';
        } elseif ($users[$idx]['role'] === 'admin' && $role !== 'admin' && $admins <= 1) {
            $err = 'Tem de existir pelo menos um administrador.';
        } else {
            $users[$idx]['role'] = $role;
            save_users($users);
            $msg = 'Perfil atualizado.';
        }
    } elseif ($act === 'reset') {
        if (strlen($pass) < 8) {
            $err = 'A palavra-passe precisa de pelo menos 8 caracteres.';
        } else {
            $users[$idx]['pass_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            save_users($users);
            $msg = 'Palavra-passe alterada.';
        }
    } elseif ($act === 'delete') {
        if (strtolower($users[$idx]['username']) === strtolower($me)) {
            $err = 'Não podes apagar a tua própria conta.';
        } elseif ($users[$idx]['role'] === 'admin' && $admins <= 1) {
            $err = 'Tem de existir pelo menos um administrador.';
        } else {
            array_splice($users, $idx, 1);
            save_users($users);
            $msg = 'Utilizador removido.';
        }
    }
}

$users = load_users();
$site_title = 'Utilizadores | Futsal Cuts';
include __DIR__ . '/partials/header.php';
?>
<div class="container">
    <?= crumbs([['Início', 'index.php'], 'Utilizadores']) ?>
    <section class="page-hero slim">
        <div>
            <p class="eyebrow">Administração</p>
            <h1>Utilizadores</h1>
            <p class="muted">Administrador: tudo, incluindo utilizadores. Editor: cria e edita jogos e momentos. Só visualização: vê jogos, apresentação e PDF, sem alterar nada.</p>
        </div>
    </section>

    <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="panel">
        <div class="table-scroll">
        <table class="table">
            <thead><tr><th>Utilizador</th><th>Perfil</th><th>Nova palavra-passe</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($u['username']) ?></strong><?= strtolower($u['username']) === strtolower($me) ? ' <span class="badge">tu</span>' : '' ?></td>
                    <td>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                            <select name="role" onchange="this.form.submit()">
                                <?php foreach ($roles as $k => $label): ?>
                                    <option value="<?= $k ?>" <?= ($u['role'] ?? '') === $k ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reset">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                            <input type="password" name="password" minlength="8" placeholder="mín. 8 caracteres" autocomplete="new-password" required>
                            <button class="button small" type="submit">Alterar</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" class="inline-form" onsubmit="return confirm('Remover este utilizador?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                            <button class="button small danger" type="submit">Remover</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="panel form-panel">
        <h3>Novo utilizador</h3>
        <form method="post" class="stacked-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="field-grid">
                <label><span>Utilizador</span><input type="text" name="username" autocomplete="off" required></label>
                <label><span>Palavra-passe</span><input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
                <label><span>Perfil</span>
                    <select name="role">
                        <?php foreach ($roles as $k => $label): ?><option value="<?= $k ?>" <?= $k === 'viewer' ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-actions"><button class="button accent" type="submit">Criar utilizador</button></div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
