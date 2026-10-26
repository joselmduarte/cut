<?php
require __DIR__ . '/db.php';
require_login();

$tagFilter = trim((string)($_GET['tag'] ?? ''));
$games = app_games();

if ($tagFilter !== '') {
    $games = array_values(array_filter($games, function ($game) use ($tagFilter) {
        return tag_matches_game($game, $tagFilter);
    }));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_game'])) {
    $team = trim((string)($_POST['team'] ?? ''));
    $opponent = trim((string)($_POST['opponent'] ?? ''));
    $date = trim((string)($_POST['date'] ?? ''));
    $youtube_url = trim((string)($_POST['youtube_url'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($title === '') {
        $title = ($team !== '' ? $team : 'Novo jogo');
    }

    if ($youtube_url !== '') {
        $youtube_url = normalize_youtube_url($youtube_url);
    }

    $game_id = create_game([
        'title' => $title,
        'team' => $team,
        'opponent' => $opponent,
        'date' => $date,
        'youtube_url' => $youtube_url,
        'notes' => $notes,
    ]);

    header('Location: game.php?id=' . urlencode($game_id));
    exit;
}

$site_title = 'Futsal Cuts';
$allTags = app_tags();
$recentMoments = array_slice(moments_filtered([]), 0, 6);
$kindIcon = ['video' => 'video', 'board' => 'pitch', 'text' => 'file-text', 'cover' => 'image'];
$kindLabel = ['video' => 'Vídeo', 'board' => 'Quadro', 'text' => 'Texto', 'cover' => 'Capa'];
$perPage = 6;
include __DIR__ . '/partials/header.php';
?>

<div class="container">
    <div class="tabs tabs-right" role="tablist" aria-label="Secções">
        <a class="tab active" role="tab" href="index.php">Jogos</a>
        <a class="tab" role="tab" href="moments.php">Momentos</a>
        <a class="tab" role="tab" href="board.php">Quadro tático</a>
    </div>

    <section id="games-list" class="section-title-row">
        <h2 class="tid-title"><?= $tagFilter !== '' ? 'Jogos com «' . htmlspecialchars($tagFilter) . '»' : 'Últimos jogos' ?></h2>
        <div class="section-tools">
            <?php if ($tagFilter !== ''): ?>
                <a class="button accent small" href="presentation.php?tag=<?= urlencode($tagFilter) ?>"><?= icon('play') ?> Ver todas em sequência</a>
            <?php endif; ?>
            <button class="button accent" data-open-modal="newGameModal"><?= icon('plus') ?> Novo jogo</button>
        </div>
    </section>

    <?php if (!empty($allTags)): ?>
        <div class="tag-filter-list filter-strip">
            <span class="strip-label">Filtrar por padrão</span>
            <?php foreach ($allTags as $tag): ?>
                <a class="tag-filter <?= (strcasecmp($tagFilter, $tag) === 0) ? 'active' : '' ?>" href="index.php?tag=<?= urlencode($tag) ?>"><?= htmlspecialchars($tag) ?></a>
            <?php endforeach; ?>
            <?php if ($tagFilter !== ''): ?>
                <a class="tag-filter clear" href="index.php"><?= icon('x') ?> Limpar</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="cards tid-cards">
        <?php if (empty($games)): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('plus') ?></div>
                <h3>Ainda não há jogos</h3>
                <p>Cria o primeiro jogo e começa a marcar os momentos que importam para a equipa.</p>
                <button class="button accent" data-open-modal="newGameModal">Criar primeiro jogo</button>
            </div>
        <?php else: ?>
            <?php foreach ($games as $n => $game): ?>
                <?php
                $moment_count = count($game['moments'] ?? []);
                $anim_count = count(array_filter($game['moments'] ?? [], 'is_animated'));
                $date_label = $game['date'] ?: '—';
                ?>
                <article class="card game-card<?= $n >= $perPage ? ' more' : '' ?>"<?= $n >= $perPage ? ' hidden' : '' ?>>
                    <span class="corner" aria-hidden="true"><?= icon('info') ?></span>
                    <div class="avatar"><img src="assets/id-mark.png" alt="" width="40" height="40"></div>
                    <h3><a href="game.php?id=<?= urlencode($game['id']) ?>"><?= htmlspecialchars($game['title']) ?></a></h3>
                    <dl class="mini-kv">
                        <div><dt>Equipa</dt><dd><?= htmlspecialchars($game['team'] ?: '—') ?></dd></div>
                        <div><dt>Adversário</dt><dd><?= htmlspecialchars($game['opponent'] ?: '—') ?></dd></div>
                        <div><dt>Data</dt><dd class="amber"><?= htmlspecialchars($date_label) ?></dd></div>
                        <div><dt>Vídeo</dt><dd><?= !empty($game['youtube_url']) ? '<span class="chip-ok">Ligado</span>' : '—' ?></dd></div>
                        <div><dt>Momentos</dt><dd><?= $moment_count ?></dd></div>
                        <div><dt>Animações</dt><dd><?= $anim_count ?></dd></div>
                    </dl>
                    <div class="card-actions">
                        <a class="button action" href="game.php?id=<?= urlencode($game['id']) ?>">Abrir</a>
                        <a class="button secondary" href="presentation.php?id=<?= urlencode($game['id']) ?>"><?= icon('play') ?> Apresentar</a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if (count($games) > $perPage): ?>
        <div class="load-more-wrap">
            <button type="button" class="load-more" id="loadMore"><?= icon('plus') ?> Carregar mais <span class="count"><?= count($games) - $perPage ?></span></button>
        </div>
    <?php endif; ?>

    <div class="home-grid">
        <section>
            <div class="section-title-row"><h2 class="tid-title">Últimos momentos</h2><a class="button secondary small" href="moments.php">Ver todos</a></div>
            <?php if (empty($recentMoments)): ?>
                <div class="empty-state narrow"><p class="muted">Ainda não há momentos.</p></div>
            <?php else: ?>
                <div class="row-cards">
                    <?php foreach ($recentMoments as $m):
                        $type = $m['type'] ?? 'video';
                        $vid = youtube_video_id($m['_youtube'] ?? '');
                        ?>
                        <a class="row-card" href="game.php?id=<?= urlencode($m['_game_id']) ?>#moment-<?= urlencode($m['id'] ?? '') ?>">
                            <span class="corner" aria-hidden="true"><?= icon('info') ?></span>
                            <span class="rc-thumb">
                                <?php if ($type === 'board' && !empty($m['board'])): ?>
                                    <span class="board-thumb" data-board-thumb="<?= htmlspecialchars(json_encode($m['board'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"></span>
                                <?php elseif ($vid !== ''): ?>
                                    <img src="https://img.youtube.com/vi/<?= htmlspecialchars($vid) ?>/default.jpg" alt="" loading="lazy">
                                <?php else: ?>
                                    <?= icon($kindIcon[$type] ?? 'file-text') ?>
                                <?php endif; ?>
                            </span>
                            <span class="rc-main"><strong><?= htmlspecialchars(($m['title'] ?? '') !== '' ? $m['title'] : 'Momento') ?></strong><small class="amber"><?= htmlspecialchars($m['_game_title']) ?></small></span>
                            <span class="rc-type"><strong><?= $kindLabel[$type] ?? 'Painel' ?></strong>
                                <?php if (!empty($m['_animated'])): ?><span class="badge badge-anim"><?= icon('play') ?> Animação</span><?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section>
            <div class="section-title-row"><h2 class="tid-title">Acessos rápidos</h2></div>
            <div class="quick-tiles">
                <button type="button" class="tile" data-open-modal="newGameModal"><?= icon('plus') ?><span>Novo jogo</span></button>
                <a class="tile" href="moments.php"><?= icon('film') ?><span>Momentos</span></a>
                <a class="tile" href="board.php"><?= icon('pitch') ?><span>Quadro tático</span></a>
                <?php if (can_edit()): ?><a class="tile" href="tags.php"><?= icon('tag') ?><span>Gerir tags</span></a><?php endif; ?>
            </div>
        </section>
    </div>
</div>

<div id="newGameModal" class="modal hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Novo jogo</h2>
            <button class="close-button" type="button" data-close-modal="newGameModal"><?= icon('x') ?></button>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="new_game" value="1">

            <div class="field-grid">
                <label>
                    <span>Título</span>
                    <input type="text" name="title" placeholder="Ex.: Jogo 1 - U15">
                </label>

                <label>
                    <span>Equipa</span>
                    <input type="text" name="team" placeholder="Ex.: Futsal A">
                </label>

                <label>
                    <span>Adversário</span>
                    <input type="text" name="opponent" placeholder="Ex.: FC Porto B">
                </label>

                <label>
                    <span>Data</span>
                    <input type="date" name="date">
                </label>

                <label class="full-width">
                    <span>URL do YouTube</span>
                    <input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=...">
                </label>

                <label class="full-width">
                    <span>Notas</span>
                    <textarea name="notes" rows="3" placeholder="Objetivos da sessão, contexto, pontos a observar..."></textarea>
                </label>
            </div>

            <div class="form-actions">
                <button type="button" class="button secondary" data-close-modal="newGameModal">Cancelar</button>
                <button type="submit" class="button accent">Guardar jogo</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
