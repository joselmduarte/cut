<?php
require __DIR__ . '/db.php';
require_login();

$id = $_GET['id'] ?? '';
$game = app_game($id);
if (!$game) {
    header('Location: index.php');
    exit;
}
$moments = $game['moments'] ?? [];
$vid = youtube_video_id($game['youtube_url'] ?? '');
function fmt_t(int $s): string { return sprintf('%02d:%02d', intdiv(max(0, $s), 60), max(0, $s) % 60); }
$h = fn ($t) => htmlspecialchars((string)$t, ENT_QUOTES);
?><!doctype html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<title><?= $h($game['title']) ?> – PDF</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/theme.css">
<style>
*{-webkit-print-color-adjust:exact;print-color-adjust:exact;box-sizing:border-box}
@page{size:A4 landscape;margin:0}
body{margin:0;background:#05081a;font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#eef1ff}
.bar{position:sticky;top:0;z-index:5;display:flex;gap:12px;align-items:center;justify-content:center;padding:12px;background:#0b1026;border-bottom:1px solid rgba(255,255,255,.1)}
.slide{width:297mm;height:210mm;margin:12px auto;padding:16mm 18mm;overflow:hidden;position:relative;
  background:linear-gradient(160deg,#2a2070 0%,#141a50 45%,#0b1026 100%);page-break-after:always;break-after:page;display:flex;flex-direction:column}
.slide .eyebrow{color:#8e97c7;text-transform:uppercase;letter-spacing:.12em;font-size:11pt;margin:0 0 4mm}
.slide h1,.slide h2{margin:0 0 5mm;text-transform:uppercase;letter-spacing:.05em}
.slide h1{font-size:34pt}.slide h2{font-size:26pt}
.center{justify-content:center;align-items:center;text-align:center}
.body{font-size:17pt;line-height:1.5}
.foot{position:absolute;left:18mm;right:18mm;bottom:8mm;display:flex;justify-content:space-between;color:#8e97c7;font-size:9pt}
.vid{display:flex;gap:10mm;align-items:flex-start}
.vid img{width:120mm;border-radius:8px;border:1px solid rgba(255,255,255,.15)}
.meta{font-size:13pt;color:#c9d0ff;line-height:1.7}
.tag{display:inline-block;padding:2px 10px;border-radius:99px;background:rgba(255,77,109,.18);color:#ff8aa0;margin:0 6px 6px 0;font-size:10pt}
a.go{display:inline-block;margin-top:6mm;padding:8px 16px;border-radius:10px;background:linear-gradient(135deg,#7a5cff,#3d8bff);color:#fff;text-decoration:none;font-weight:700}
.toc{columns:2;column-gap:14mm;font-size:13pt;line-height:1.9}
.board-host svg{width:100%;max-height:140mm}
.slide{position:relative}
.slide:not(.cv)::after{content:"";position:absolute;right:12mm;top:9mm;width:11mm;height:11mm;background:url(assets/id-mark.png) center/contain no-repeat}
@media screen{.slide{zoom:.62}}
@media print{.bar{display:none}.slide{margin:0;zoom:1}body{background:none}}
</style>
</head>
<body>
<div class="bar">
    <button class="button accent" onclick="window.print()">🖨 Imprimir / Guardar como PDF</button>
    <a class="button secondary" href="game.php?id=<?= urlencode($id) ?>">Voltar</a>
    <span class="muted">No diálogo escolhe «Guardar como PDF», formato A4 horizontal, sem margens, com «Gráficos de fundo».</span>
</div>

<section class="slide center">
    <img src="assets/logo-tid.png" alt="Talent ID" style="height:16mm;margin-bottom:8mm">
    <p class="eyebrow">Análise de jogo</p>
    <h1><?= $h($game['title']) ?></h1>
    <?php if (!empty($game['match_date'])): ?><p class="body"><?= $h($game['match_date']) ?></p><?php endif; ?>
    <p class="body"><?= count($moments) ?> slides</p>
</section>

<?php foreach ($moments as $i => $m): $type = $m['type'] ?? 'video'; $n = $i + 1; ?>
<?php if ($type === 'index'): ?>
<section class="slide">
    <p class="eyebrow">Índice</p>
    <h2><?= $h($m['title'] ?: 'Índice') ?></h2>
    <div class="toc">
        <?php foreach ($moments as $j => $o): if (($o['type'] ?? '') === 'index') { continue; } ?>
            <div><?= $j + 1 ?>. <?= $h($o['title'] ?? '') ?></div>
        <?php endforeach; ?>
    </div>
    <div class="foot"><span><?= $h($game['title']) ?></span><span><?= $n ?></span></div>
</section>
<?php elseif ($type === 'text'): ?>
<section class="slide center">
    <?php if (!empty($m['category'])): ?><p class="eyebrow"><?= $h($m['category']) ?></p><?php endif; ?>
    <h1><?= $h($m['title']) ?></h1>
    <?php if (!empty($m['notes'])): ?><div class="body"><?= nl2br($h($m['notes'])) ?></div><?php endif; ?>
    <div class="foot"><span><?= $h($game['title']) ?></span><span><?= $n ?></span></div>
</section>
<?php elseif ($type === 'cover'): $cv = $m['cover'] ?? []; ?>
<section class="slide center cv" style="background:<?= $h($cv['bg'] ?? '#0b1026') ?>;color:<?= $h($cv['fg'] ?? '#ffffff') ?>">
    <?php if (!empty($cv['emblem']) || !empty($cv['emblem2'])): ?>
        <div style="display:flex;gap:18mm;align-items:center;justify-content:center;margin-bottom:8mm">
            <?php foreach (['emblem', 'emblem2'] as $ek): if (!empty($cv[$ek])): ?><img src="<?= $h($cv[$ek]) ?>" alt="" style="max-height:62mm;max-width:90mm;border:0;border-radius:0;object-fit:contain"><?php endif; endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($m['category'])): ?><p class="eyebrow" style="color:inherit;opacity:.8"><?= $h($m['category']) ?></p><?php endif; ?>
    <h1 style="color:inherit"><?= $h($m['title']) ?></h1>
    <?php if (!empty($m['notes'])): ?><div class="body" style="color:inherit"><?= nl2br($h($m['notes'])) ?></div><?php endif; ?>
</section>
<?php elseif ($type === 'board'): ?>
<section class="slide">
    <?php if (!empty($m['category'])): ?><p class="eyebrow"><?= $h($m['category']) ?></p><?php endif; ?>
    <h2><?= $h($m['title']) ?></h2>
    <div class="board-host" data-board="<?= $h(json_encode($m['board'] ?? null)) ?>"></div>
    <?php if (!empty($m['notes'])): ?><div class="meta"><?= nl2br($h($m['notes'])) ?></div><?php endif; ?>
    <div class="foot"><span><?= $h($game['title']) ?></span><span><?= $n ?></span></div>
</section>
<?php else: ?>
<section class="slide">
    <p class="eyebrow"><?= $h($m['category'] ?: 'Momento') ?> · <?= fmt_t((int)$m['start']) ?> – <?= fmt_t((int)$m['end']) ?></p>
    <h2><?= $h($m['title']) ?></h2>
    <div class="vid">
        <?php if ($vid): ?><img src="https://i.ytimg.com/vi/<?= $h($vid) ?>/hqdefault.jpg" alt=""><?php endif; ?>
        <div class="meta">
            <?php if (!empty($m['athlete'])): ?><div>Jogador: <strong><?= $h($m['athlete']) ?></strong></div><?php endif; ?>
            <div>
                <?php foreach ((array)($m['tags'] ?? []) as $t): ?><span class="tag"><?= $h($t) ?></span><?php endforeach; ?>
            </div>
            <?php if (!empty($m['notes'])): ?><div class="body"><?= nl2br($h($m['notes'])) ?></div><?php endif; ?>
            <?php if ($vid): ?><a class="go" href="https://www.youtube.com/watch?v=<?= $h($vid) ?>&t=<?= (int)$m['start'] ?>s">▶ Ver no vídeo (<?= fmt_t((int)$m['start']) ?>)</a><?php endif; ?>
        </div>
    </div>
    <div class="foot"><span><?= $h($game['title']) ?></span><span><?= $n ?></span></div>
</section>
<?php endif; ?>
<?php endforeach; ?>

<script src="assets/app.js"></script>
<script>
document.querySelectorAll('[data-board]').forEach(function (el) {
  var b = null; try { b = JSON.parse(el.getAttribute('data-board')); } catch (e) {}
  renderBoard(el, b, { readonly: true });
});
<?php if (!empty($_GET['print'])): ?>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 900); });<?php endif; ?>
</script>
</body>
</html>
