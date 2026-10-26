<?php
require __DIR__ . '/db.php';
require_login();

$id = $_GET['id'] ?? '';
$game = app_game($id);
if (!$game) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_moment'])) {
    $start = (int)($_POST['start'] ?? 0);
    $end = (int)($_POST['end'] ?? ($start + 10));
    $moment = [
        'title' => trim((string)($_POST['title'] ?? 'Momento')),
        'athlete' => trim((string)($_POST['athlete'] ?? '')),
        'category' => trim((string)($_POST['category'] ?? 'Geral')),
        'tags' => trim((string)($_POST['tags'] ?? '')),
        'start' => $start,
        'end' => $end,
        'notes' => trim((string)($_POST['notes'] ?? '')),
        'importance' => (int)($_POST['importance'] ?? 2),
    ];

    create_moment($id, $moment);
    header('Location: game.php?id=' . urlencode($id));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder'])) {
    $ids = json_decode((string)$_POST['reorder'], true);
    if (is_array($ids)) {
        reorder_moments($id, $ids);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_panel'])) {
    $mid = (string)($_POST['moment_id'] ?? '');
    $pos = panel_position($game['moments'] ?? [], (string)($_POST['after'] ?? ''));
    $existing = null;
    foreach (($game['moments'] ?? []) as $em) {
        if (($em['id'] ?? '') === $mid) {
            $existing = $em;
        }
    }
    $isCover = $mid === '' ? (string)($_POST['ptype'] ?? '') === 'cover' : (($existing['type'] ?? '') === 'cover');
    if ($isCover) {
        $_POST['cover_data'] = cover_from_request($_POST, $_FILES, $existing['cover'] ?? null);
    }
    if ($mid === '') {
        $mid = create_panel($id, $_POST, $pos);
    } else {
        update_panel($id, $mid, $_POST, $pos);
    }
    header('Location: game.php?id=' . urlencode($id) . '#moment-' . urlencode($mid));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_moment'])) {
    $t = max(0, (int)($_POST['time'] ?? 0));
    $before = max(0, min(60, (int)($_POST['before'] ?? 5)));
    $after = max(1, min(60, (int)($_POST['after'] ?? 5)));
    $mid = create_moment($id, [
        'title' => 'Momento ' . format_time($t),
        'start' => max(0, $t - $before),
        'end' => $t + $after,
        'quick' => true,
    ]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'id' => $mid, 'time' => format_time($t)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_moment'])) {
    $mid = (string)($_POST['moment_id'] ?? '');
    update_moment($id, $mid, $_POST);
    header('Location: game.php?id=' . urlencode($id) . '#moment-' . urlencode($mid));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_game'])) {
    delete_game($id);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_game'])) {
    update_game($id, [
        'title' => $_POST['title'] ?? '',
        'team' => $_POST['team'] ?? '',
        'opponent' => $_POST['opponent'] ?? '',
        'date' => $_POST['date'] ?? '',
        'youtube_url' => $_POST['youtube_url'] ?? '',
        'notes' => $_POST['notes'] ?? '',
    ]);
    header('Location: game.php?id=' . urlencode($id));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_moment'])) {
    delete_moment($id, (string)($_POST['moment_id'] ?? ''));
    header('Location: game.php?id=' . urlencode($id));
    exit;
}

$game['moments'] = $game['moments'] ?? [];
$tags = app_tags();
$site_title = $game['title'] . ' | Futsal Cuts';
include __DIR__ . '/partials/header.php';
?>

<div class="container">
    <?= crumbs([['Jogos', 'index.php'], $game['title']]) ?>

    <div class="action-bar">
        <div class="btn-group">
            <?php if (can_edit()): ?><button type="button" class="button" data-open-modal="editGameModal"><?= icon('edit') ?> Editar</button><?php endif; ?>
            <a class="button" href="presentation.php?id=<?= urlencode($id) ?>"><?= icon('play') ?> Apresentar</a>
            <a class="button" href="export.php?id=<?= urlencode($id) ?>" target="_blank"><?= icon('printer') ?> PDF</a>
        </div>
        <form method="post" class="inline-form" onsubmit="return confirm('Apagar este jogo e todos os momentos?');">
            <?= csrf_field() ?>
            <input type="hidden" name="delete_game" value="1">
            <button type="submit" class="button small danger"><?= icon('trash') ?> Apagar jogo</button>
        </form>
    </div>

    <div class="game-layout">
    <aside class="sidebar">
        <div class="panel side-info">
            <div class="kv"><span class="k">Jogo</span><span class="v strong"><?= htmlspecialchars($game['title']) ?></span></div>
            <div class="kv"><span class="k">Equipa</span><span class="v"><?= htmlspecialchars($game['team'] ?: '—') ?></span></div>
            <div class="kv"><span class="k">Adversário</span><span class="v"><?= htmlspecialchars($game['opponent'] ?: '—') ?></span></div>
            <div class="kv"><span class="k">Data</span><span class="v"><?= htmlspecialchars($game['date'] ?: '—') ?></span></div>
            <div class="kv"><span class="k">Vídeo</span><span class="v"><?php if (!empty($game['youtube_url'])): ?><a class="amber-link" href="<?= htmlspecialchars($game['youtube_url']) ?>" target="_blank" rel="noopener">YouTube <?= icon('external') ?></a><?php else: ?>—<?php endif; ?></span></div>
            <div class="kv"><span class="k">Momentos</span><span class="v"><?= count($game['moments'] ?? []) ?> · <?= count(array_filter($game['moments'] ?? [], 'is_animated')) ?> animação(ões)</span></div>
            <?php if (!empty($game['notes'])): ?>
                <div class="kv"><span class="k">Notas</span><span class="v"><?= nl2br(htmlspecialchars($game['notes'])) ?></span></div>
            <?php endif; ?>
        </div>

        <div class="panel panel-bar">
            <div class="bar-head"><span>Momentos</span></div>
            <div class="moment-list">
                <?php if (empty($game['moments'])): ?>
                    <p class="muted">Nenhum momento.</p>
                <?php else: ?>
                    <?php foreach ($game['moments'] as $moment): ?>
                        <?php if (in_array($moment['type'] ?? '', ['text', 'index', 'board', 'cover'], true)): ?>
                            <a class="moment-item" data-id="<?= htmlspecialchars($moment['id']) ?>" href="#moment-<?= htmlspecialchars($moment['id']) ?>"><span class="reorder"><button type="button" class="mv" data-move="-1" title="Subir" aria-label="Subir"><?= icon('chevron-up') ?></button><span class="drag-handle" title="Arrastar para reordenar"><?= icon('grip') ?></span><button type="button" class="mv" data-move="1" title="Descer" aria-label="Descer"><?= icon('chevron-down') ?></button></span>
                                <span class="moment-time"><?= icon(['board' => 'pitch', 'index' => 'list', 'cover' => 'image'][$moment['type'] ?? ''] ?? 'file-text') ?></span>
                                <span><strong><?= htmlspecialchars($moment['title'] ?: 'Painel') ?></strong><small><?= ['board' => 'Quadro tático', 'index' => 'Índice', 'cover' => 'Capa'][$moment['type'] ?? ''] ?? 'Painel de texto' ?></small></span>
                            </a>
                        <?php continue; endif; ?>
                        <a class="moment-item" data-id="<?= htmlspecialchars($moment['id']) ?>" href="#moment-<?= htmlspecialchars($moment['id']) ?>"><span class="reorder"><button type="button" class="mv" data-move="-1" title="Subir" aria-label="Subir"><?= icon('chevron-up') ?></button><span class="drag-handle" title="Arrastar para reordenar"><?= icon('grip') ?></span><button type="button" class="mv" data-move="1" title="Descer" aria-label="Descer"><?= icon('chevron-down') ?></button></span>
                            <span class="moment-time"><?= format_time((int)($moment['start'] ?? 0)) ?></span>
                            <span>
                                <strong><?= htmlspecialchars($moment['title'] ?: 'Momento') ?></strong>
                                <small><?= htmlspecialchars($moment['category'] ?: 'Geral') ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </aside>

    <main class="content">
        <?php if (!empty($game['youtube_url'])): ?>
            <div class="video-stage" id="videoStage">
            <div class="video-shell">
                <iframe
                    id="gamePlayer"
                    src="<?= htmlspecialchars(youtube_embed_url($game['youtube_url'], 0, 0, true)) ?>"
                    title="Vídeo do jogo"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen>
                </iframe>
            </div>
            <div class="quick-bar">
                <button type="button" class="button accent big" data-quick-mark><?= icon('zap') ?> Marcar rápido <small>(tecla M)</small></button>
                <label>antes <select id="qBefore"><option>3</option><option selected>5</option><option>8</option><option>10</option></select> s</label>
                <label>depois <select id="qAfter"><option>3</option><option selected>5</option><option>8</option><option>10</option></select> s</label>
                <span class="muted" id="quickStatus">Marca no momento e completa os dados depois.</span>
                <button type="button" class="button small" id="videoSize" title="Tecla V"><?= icon('maximize') ?> <span id="videoSizeLabel">Vídeo: normal</span></button>
                <button type="button" class="button small" id="videoFs"><?= icon('maximize') ?> Ecrã inteiro</button>
                <button type="button" class="button small" onclick="location.reload()">Atualizar lista</button>
            </div>
            </div>
        <?php else: ?>
            <div class="empty-state narrow">
                <h3>Sem vídeo associado</h3>
                <p>Adicione a URL do YouTube para esta análise.</p>
            </div>
        <?php endif; ?>

        <div class="panel form-panel">
            <h3>Adicionar momento</h3>
            <?php if (!empty($game['youtube_url'])): ?>
                <div class="mark-bar">
                    <button type="button" class="button accent" data-mark-now><?= icon('clock') ?> Marcar agora (−5 s / +5 s)</button>
                    <small class="muted" id="markStatus">Vê o vídeo e carrega no momento certo: o início e o fim são preenchidos.</small>
                </div>
            <?php endif; ?>
            <form method="post" class="stacked-form">
            <?= csrf_field() ?>
                <input type="hidden" name="new_moment" value="1">

                <div class="field-grid">
                    <label>
                        <span>Título</span>
                        <input type="text" name="title" placeholder="Ex.: Pressão alta no 3º setor">
                    </label>

                    <label>
                        <span>Jogador / atleta</span>
                        <input type="text" name="athlete" placeholder="Ex.: João Silva">
                    </label>

                    <label>
                        <span>Categoria</span>
                        <input type="text" name="category" placeholder="Ex.: Defensiva">
                    </label>

                    <label>
                        <span>Importância</span>
                        <select name="importance">
                            <option value="1">Baixa</option>
                            <option value="2" selected>Média</option>
                            <option value="3">Alta</option>
                        </select>
                    </label>

                    <label>
                        <span>Início (segundos)</span>
                        <input type="number" name="start" min="0" value="0">
                    </label>

                    <label>
                        <span>Fim (segundos)</span>
                        <input type="number" name="end" min="1" value="20">
                    </label>

                    <label class="full-width">
                        <span>Tags</span>
                        <input type="text" name="tags" placeholder="pressão alta, transição, contra-ataque" list="tag-suggestions">
                    </label>

                    <label class="full-width">
                        <span>Notas</span>
                        <textarea name="notes" rows="3" placeholder="Descreva o padrão, a opção, a correção ou a referência ao atleta..."></textarea>
                    </label>
                </div>

                <div class="form-actions">
                    <button class="button accent" type="submit">Guardar momento</button>
                </div>
            </form>
            <datalist id="tag-suggestions">
                <?php foreach ($tags as $tag): ?>
                    <option value="<?= htmlspecialchars($tag) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="panel">
            <div class="section-header inline-header">
                <h3>Lista de momentos</h3>
                <button type="button" class="button accent small" data-new-panel><?= icon('plus') ?> Painel</button>
                <a class="button secondary small" href="tags.php">Gerir tags</a>
            </div>
            <?php if (empty($game['moments'])): ?>
                <p class="muted">Ainda não há momentos marcados.</p>
            <?php else: ?>
                <div class="moments-grid">
                <?php foreach ($game['moments'] as $moment): ?>
                    <?php if (in_array($moment['type'] ?? '', ['text', 'index', 'board', 'cover'], true)): ?>
                        <article id="moment-<?= htmlspecialchars($moment['id']) ?>" class="moment-card panel-card">
                            <div class="moment-header"><div>
                                <h4><?= htmlspecialchars($moment['title'] ?: 'Painel') ?></h4>
                                <p class="muted"><?= icon(['board' => 'pitch', 'index' => 'list', 'cover' => 'image'][$moment['type'] ?? ''] ?? 'file-text') ?> <?= ['board' => 'Quadro tático', 'index' => 'Índice', 'cover' => 'Capa'][$moment['type'] ?? ''] ?? 'Painel de texto' ?><?= !empty($moment['category']) ? ' • ' . htmlspecialchars($moment['category']) : '' ?></p>
                            </div></div>
                            <?php if (!empty($moment['notes'])): ?><p><?= nl2br(htmlspecialchars($moment['notes'])) ?></p><?php endif; ?>
                            <?php if (is_animated($moment)): ?><p><span class="badge badge-anim"><?= icon('play') ?> Animação · <?= count((array)($moment['board']['frames'] ?? [])) ?> fotogramas</span></p><?php endif; ?>
                            <?php if (!empty($moment['tags'])): ?><div class="tag-list"><?php foreach ((array)$moment['tags'] as $tg): ?><a class="tag" href="moments.php?tag=<?= urlencode($tg) ?>"><?= htmlspecialchars($tg) ?></a><?php endforeach; ?></div><?php endif; ?>
                            <div class="card-actions">
                                <button type="button" class="button small" data-edit-panel="<?= htmlspecialchars(json_encode(['id' => $moment['id'], 'title' => $moment['title'] ?? '', 'category' => $moment['category'] ?? '', 'notes' => $moment['notes'] ?? '', 'type' => $moment['type'] ?? 'text', 'board' => $moment['board'] ?? null, 'cover' => $moment['cover'] ?? null, 'tags' => array_values((array)($moment['tags'] ?? []))], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">Editar</button>
                                <form method="post" class="inline-form" onsubmit="return confirm('Remover este painel?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delete_moment" value="1">
                                    <input type="hidden" name="moment_id" value="<?= htmlspecialchars($moment['id']) ?>">
                                    <button type="submit" class="button small danger">Remover</button>
                                </form>
                            </div>
                        </article>
                    <?php continue; endif; ?>
                    <article id="moment-<?= htmlspecialchars($moment['id']) ?>" class="moment-card">
                        <div class="moment-header">
                            <div>
                                <h4><?= htmlspecialchars($moment['title'] ?: 'Momento') ?></h4>
                                <p class="muted">
                                    <?= htmlspecialchars($moment['athlete'] ?: 'Sem atleta') ?> • <?= htmlspecialchars($moment['category'] ?: 'Geral') ?>
                                </p>
                            </div>
                            <div class="moment-meta">
                                <?php if (!empty($moment['quick'])): ?><span class="badge badge-todo">Por completar</span><?php endif; ?>
                                <span class="badge importance-<?= (int)($moment['importance'] ?? 2) ?>"><?= (int)($moment['importance'] ?? 2) ?> / 3</span>
                            </div>
                        </div>

                        <div class="moment-row">
                            <span>Início: <?= format_time((int)($moment['start'] ?? 0)) ?></span>
                            <span>Fim: <?= format_time((int)($moment['end'] ?? 0)) ?></span>
                        </div>

                        <?php if (!empty($moment['tags'])): ?>
                            <div class="tag-list">
                                <?php foreach ((array)($moment['tags'] ?? []) as $tag): ?>
                                    <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($moment['notes'])): ?>
                            <p><?= nl2br(htmlspecialchars($moment['notes'])) ?></p>
                        <?php endif; ?>

                        <div class="card-actions">
                            <a class="button small action" href="presentation.php?id=<?= urlencode($id) ?>&moment=<?= urlencode($moment['id']) ?>">Ver no player</a>
                            <button type="button" class="button small" data-edit-moment="<?= htmlspecialchars(json_encode([
                                'id' => $moment['id'], 'title' => $moment['title'] ?? '', 'athlete' => $moment['athlete'] ?? '',
                                'category' => $moment['category'] ?? '', 'importance' => $moment['importance'] ?? 2,
                                'start' => $moment['start'] ?? 0, 'end' => $moment['end'] ?? 0,
                                'tags' => implode(', ', (array)($moment['tags'] ?? [])), 'notes' => $moment['notes'] ?? '',
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">Editar</button>
                            <form method="post" class="inline-form" onsubmit="return confirm('Remover este momento?');">
            <?= csrf_field() ?>
                                <input type="hidden" name="delete_moment" value="1">
                                <input type="hidden" name="moment_id" value="<?= htmlspecialchars($moment['id']) ?>">
                                <button type="submit" class="button small danger">Remover</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    </div>
</div>

<div id="panelModal" class="modal hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Painel de texto</h2>
            <button class="close-button" type="button" data-close-modal="panelModal"><?= icon('x') ?></button>
        </div>
        <form method="post" id="panelForm" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="save_panel" value="1">
            <input type="hidden" name="moment_id" value="">
            <div class="field-grid">
                <label class="full-width"><span>Tipo de slide</span>
                    <select name="ptype">
                        <option value="text">Texto / título de secção</option>
                        <option value="board">Quadro tático (campo com 5 vs 5)</option>
                        <option value="cover">Capa (cor de fundo, emblema e título)</option>
                        <option value="index">Índice (menu para saltar para um slide)</option>
                    </select>
                </label>
                <label class="full-width"><span>Etiqueta / secção (opcional)</span><input type="text" name="category" placeholder="Ex.: DEFESA"></label>
                <label class="full-width"><span>Título</span><input type="text" name="title" placeholder="Ex.: Cantos - fechar o meio" required></label>
                <div class="full-width" id="coverField" hidden>
                    <div class="field-grid">
                        <label><span>Cor de fundo</span><input type="color" name="cover_bg" value="#0b1026"></label>
                        <label><span>Cor do texto</span><input type="color" name="cover_fg" value="#ffffff"></label>
                        <label><span>Emblema (PNG, JPG ou WEBP, até 2 MB)</span><input type="file" name="cover_emblem" accept="image/png,image/jpeg,image/webp,image/gif"></label>
                        <label><span>2.º emblema (opcional, adversário)</span><input type="file" name="cover_emblem2" accept="image/png,image/jpeg,image/webp,image/gif"></label>
                        <label class="check"><input type="checkbox" name="remove_emblem" value="1"> Remover emblema atual</label>
                        <label class="check"><input type="checkbox" name="remove_emblem2" value="1"> Remover 2.º emblema</label>
                    </div>
                    <div class="cover-preview" id="coverPreview"></div>
                </div>
                <div class="full-width" id="boardField" hidden>
                    <span class="muted">Arrasta os jogadores e a bola para a posição inicial. Duplo toque num jogador para mudar o número.</span>
                    <div id="boardEditor" class="board-host"></div>
                    <div class="board-tools">
                        <button type="button" class="button small" id="pbFrame"><?= icon('layers') ?> Gravar fotograma</button>
                        <button type="button" class="button small" id="pbPlay"><?= icon('play') ?> Animar</button>
                        <button type="button" class="button small" id="pbUndoFrame"><?= icon('reset') ?> Remover último</button>
                        <button type="button" class="button small danger" id="pbClear"><?= icon('trash') ?> Limpar</button>
                        <span class="muted" id="pbInfo">Fotogramas: 0</span>
                    </div>
                    <p class="muted">Posiciona os jogadores, grava um fotograma, muda as posições e grava outro. A animação junta os fotogramas pela ordem. As cores das equipas e dos guarda-redes mudam-se tocando nas bolinhas «Casa», «GR», «GR» e «Fora» no campo.</p>
                    <input type="hidden" name="board_json" value="">
                </div>
                <label class="full-width" id="tagsField"><span>Tags (para filtrar em Momentos)</span><input type="text" name="tags" placeholder="padrão ofensivo, transição" list="tag-suggestions"></label>
                <label class="full-width" id="notesField"><span>Texto (opcional — sem texto fica como painel de título)</span><textarea name="notes" rows="5" placeholder="Ex.: Comportamento: manter a marcação à zona..."></textarea></label>
                <label class="full-width"><span>Posição na apresentação</span>
                    <select name="after">
                        <option value="">No fim (ao editar: manter posição)</option>
                        <option value="__start">No início</option>
                        <?php foreach ($game['moments'] as $pm): ?>
                            <option value="<?= htmlspecialchars($pm['id']) ?>">Depois de: <?= htmlspecialchars($pm['title'] ?: 'Momento') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-actions">
                <button type="button" class="button secondary" data-close-modal="panelModal">Cancelar</button>
                <button type="submit" class="button accent">Guardar painel</button>
            </div>
        </form>
    </div>
</div>

<div id="editMomentModal" class="modal hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Editar momento</h2>
            <button class="close-button" type="button" data-close-modal="editMomentModal"><?= icon('x') ?></button>
        </div>
        <form method="post" id="editMomentForm">
            <?= csrf_field() ?>
            <input type="hidden" name="edit_moment" value="1">
            <input type="hidden" name="moment_id" value="">
            <div class="field-grid">
                <label><span>Título</span><input type="text" name="title"></label>
                <label><span>Jogador / atleta</span><input type="text" name="athlete"></label>
                <label><span>Categoria</span><input type="text" name="category"></label>
                <label><span>Importância</span><select name="importance"><option value="1">Baixa</option><option value="2">Média</option><option value="3">Alta</option></select></label>
                <label><span>Início (s)</span><input type="number" name="start" min="0"></label>
                <label><span>Fim (s)</span><input type="number" name="end" min="1"></label>
                <label class="full-width"><span>Tags</span><input type="text" name="tags" list="tag-suggestions"></label>
                <label class="full-width"><span>Notas</span><textarea name="notes" rows="3"></textarea></label>
            </div>
            <div class="form-actions">
                <button type="button" class="button secondary" data-close-modal="editMomentModal">Cancelar</button>
                <button type="submit" class="button accent">Guardar alterações</button>
            </div>
        </form>
    </div>
</div>


<?php if (can_edit()): ?>
<div id="editGameModal" class="modal hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Editar jogo</h2>
            <button class="close-button" type="button" data-close-modal="editGameModal"><?= icon('x') ?></button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="edit_game" value="1">
            <div class="field-grid">
                <label>
                    <span>Título</span>
                    <input type="text" name="title" value="<?= htmlspecialchars($game['title'] ?? '') ?>" required>
                </label>
                <label>
                    <span>Equipa</span>
                    <input type="text" name="team" value="<?= htmlspecialchars($game['team'] ?? '') ?>">
                </label>
                <label>
                    <span>Adversário</span>
                    <input type="text" name="opponent" value="<?= htmlspecialchars($game['opponent'] ?? '') ?>">
                </label>
                <label>
                    <span>Data</span>
                    <input type="date" name="date" value="<?= htmlspecialchars($game['date'] ?? '') ?>">
                </label>
                <label class="full-width">
                    <span>URL do YouTube</span>
                    <input type="url" name="youtube_url" value="<?= htmlspecialchars($game['youtube_url'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=...">
                </label>
                <label class="full-width">
                    <span>Notas</span>
                    <textarea name="notes" rows="3"><?= htmlspecialchars($game['notes'] ?? '') ?></textarea>
                </label>
            </div>
            <div class="form-actions">
                <button type="button" class="button secondary" data-close-modal="editGameModal">Cancelar</button>
                <button type="submit" class="button accent">Guardar alterações</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
