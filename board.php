<?php
require __DIR__ . '/db.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_board_moment'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!can_edit()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sem permissão']);
        exit;
    }
    $gameId = (string)($_POST['game_id'] ?? '');
    $title = trim((string)($_POST['title'] ?? 'Quadro tático'));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $boardJson = (string)($_POST['board_json'] ?? '');
    if ($gameId === '' || !app_game($gameId)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Jogo inválido']);
        exit;
    }
    try {
        $mid = create_panel($gameId, [
            'ptype' => 'board',
            'title' => $title !== '' ? $title : 'Quadro tático',
            'category' => 'Quadro',
            'notes' => $notes,
            'tags' => (string)($_POST['tags'] ?? ''),
            'board_json' => $boardJson,
        ], null);
        echo json_encode(['ok' => true, 'id' => $mid, 'game' => $gameId]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$games = app_games();
$site_title = 'Quadro | Futsal Cuts';
include __DIR__ . '/partials/header.php';
?>
<div class="container free-board">
    <?= crumbs([['Início', 'index.php'], 'Quadro tático']) ?>
    <section class="page-title-row"><h1 class="tid-title">Quadro tático</h1></section>

    <div class="board-layout">
        <aside class="board-menu" aria-label="Ferramentas do quadro">
            <div class="bm-group">
                <span class="bm-title">Posições</span>
                <button type="button" class="button" id="fbReset"><?= icon('reset') ?> Repor</button>
                <button type="button" class="button" id="fbFlip"><?= icon('flip') ?> Inverter</button>
            </div>
            <div class="bm-group">
                <span class="bm-title">Animação</span>
                <button type="button" class="button" id="fbFrame"><?= icon('layers') ?> Fotograma</button>
                <button type="button" class="button" id="fbPlay"><?= icon('play') ?> Animar</button>
                <button type="button" class="button" id="fbClearFrames"><?= icon('trash') ?> Limpar</button>
                <span class="bm-info" id="fbFrameInfo">Fotogramas: 0</span>
            </div>
            <div class="bm-group">
                <span class="bm-title">Guardar</span>
                <button type="button" class="button accent" id="fbSaveMoment"><?= icon('save') ?> Em momentos</button>
                <button type="button" class="button" id="fbPng"><?= icon('image') ?> PNG</button>
            </div>
            <div class="bm-group">
                <span class="bm-title">Ver</span>
                <button type="button" class="button" id="fbFs"><?= icon('maximize') ?> Ecrã inteiro</button>
            </div>
        </aside>
        <div id="freeBoard" class="board-host"></div>
    </div>
</div>

<div id="saveBoardModal" class="modal hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Guardar quadro como momento</h2>
            <button class="close-button" type="button" data-close-modal="saveBoardModal" aria-label="Fechar"><?= icon('x') ?></button>
        </div>
        <form id="saveBoardForm" class="stacked-form">
            <label>
                <span>Jogo</span>
                <select name="game_id" id="fbGameId" required>
                    <option value="">— escolher jogo —</option>
                    <?php foreach ($games as $g): ?>
                        <option value="<?= htmlspecialchars($g['id']) ?>"><?= htmlspecialchars($g['title'] ?: $g['id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Título do momento</span>
                <input type="text" name="title" id="fbMomentTitle" value="Quadro tático" required>
            </label>
            <label>
                <span>Tags</span>
                <input type="text" name="tags" id="fbMomentTags" list="fbTagList" placeholder="padrão ofensivo, transição">
                <datalist id="fbTagList"><?php foreach (app_tags() as $tg): ?><option value="<?= htmlspecialchars($tg) ?>"><?php endforeach; ?></datalist>
            </label>
            <label>
                <span>Notas</span>
                <textarea name="notes" id="fbMomentNotes" rows="2" placeholder="Descrição da jogada…"></textarea>
            </label>
            <p class="muted" id="fbSaveHint">Inclui a posição atual e os fotogramas da animação.</p>
            <div class="form-actions">
                <button type="button" class="button secondary" data-close-modal="saveBoardModal">Cancelar</button>
                <button type="submit" class="button accent">Guardar</button>
            </div>
        </form>
    </div>
</div>
<script type="application/json" id="fbCsrf"><?= json_encode(['csrf' => csrf_token()]) ?></script>
<?php include __DIR__ . '/partials/footer.php'; ?>
