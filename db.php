<?php
if (session_status() === PHP_SESSION_NONE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

if (!function_exists('app_config')) {
    function app_config(?string $key = null, $default = null)
    {
        static $config = null;

        if ($config === null) {
            $config = require __DIR__ . '/config.php';
        }

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
    }
}

if (!function_exists('app_url')) {
    function app_url(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        $base = rtrim($script, '/');
        return $scheme . '://' . $host . ($base === '/' || $base === '\\' ? '' : $base) . '/';
    }
}

if (!function_exists('normalize_youtube_url')) {
    function normalize_youtube_url(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $id = youtube_video_id($value);
        return $id !== '' ? 'https://www.youtube.com/watch?v=' . $id : $value;
    }
}

if (!function_exists('youtube_video_id')) {
    function youtube_video_id(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // Recupera links gravados com o bug antigo (ex.: ...watch?v=httpswwwyoutubecomwatchvXXXXXXXXXXX)
        if (preg_match('/v=https?[A-Za-z0-9_-]*?([A-Za-z0-9_-]{11})$/', $url, $m)) {
            return $m[1];
        }

        $patterns = [
            '/(?:youtube\.com\/watch\?(?:[^#]*&)?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/|youtube\.com\/live\/)([A-Za-z0-9_-]{11})/',
            '/^([A-Za-z0-9_-]{11})$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $match)) {
                return $match[1];
            }
        }

        return '';
    }
}

if (!function_exists('youtube_embed_url')) {
    function youtube_embed_url(string $url, int $start = 0, int $end = 0, bool $api = false): string
    {
        $videoId = youtube_video_id($url);
        if ($videoId === '') {
            return '';
        }

        $params = ['rel=0', 'modestbranding=1'];
        if ($start > 0) {
            $params[] = 'start=' . $start;
        }
        if ($end > $start) {
            $params[] = 'end=' . $end;
        }
        if ($api) {
            $params[] = 'enablejsapi=1';
        }

        return 'https://www.youtube.com/embed/' . $videoId . '?' . implode('&', $params);
    }
}

if (!function_exists('format_time')) {
    function format_time(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;
        return sprintf('%02d:%02d', $minutes, $remaining);
    }
}

if (!function_exists('default_tags')) {
    function default_tags(): array
    {
        return [
            'pressão alta',
            'transição',
            'bola longa',
            'cruzamento',
            'defesa',
            'ataque posicional',
            'contra-ataque',
            'espaço interior',
            'jogo em linha',
            'exercício',
            'padrão ofensivo',
            'padrão defensivo',
        ];
    }
}

if (!function_exists('empty_data')) {
    function empty_data(): array
    {
        return [
            'games' => [],
            'sessions' => [],
            'tags' => default_tags(),
        ];
    }
}

if (!function_exists('ensure_storage')) {
    function ensure_storage(): void
    {
        $dataFile = app_config('data_file', __DIR__ . '/data/app.json');
        $folder = dirname($dataFile);
        if (!is_dir($folder)) {
            mkdir($folder, 0775, true);
        }

        if (!file_exists($dataFile)) {
            file_put_contents($dataFile, json_encode(empty_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
    }
}

if (!function_exists('load_data')) {
    function load_data(): array
    {
        ensure_storage();
        $file = app_config('data_file', __DIR__ . '/data/app.json');
        $raw = @file_get_contents($file);

        if ($raw === false || trim($raw) === '') {
            $data = empty_data();
            save_data($data);
            return $data;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = empty_data();
            save_data($decoded);
        }

        foreach (['games', 'sessions', 'tags'] as $section) {
            if (!isset($decoded[$section]) || !is_array($decoded[$section])) {
                $decoded[$section] = [];
            }
        }

        return $decoded;
    }
}

if (!function_exists('save_data')) {
    function save_data(array $data): void
    {
        ensure_storage();
        $file = app_config('data_file', __DIR__ . '/data/app.json');
        $tmp = $file . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $file)) {
            throw new RuntimeException('Não foi possível gravar em data/. Verifica as permissões da pasta.');
        }
    }
}

if (!function_exists('app_games')) {
    function app_games(): array
    {
        $data = load_data();
        $games = $data['games'] ?? [];
        usort($games, function ($a, $b) {
            return strtotime((string)($b['date'] ?? '1970-01-01')) - strtotime((string)($a['date'] ?? '1970-01-01'));
        });
        return $games;
    }
}

if (!function_exists('app_game')) {
    function app_game(string $id): ?array
    {
        foreach (app_games() as $game) {
            if (($game['id'] ?? '') === $id) {
                $game['moments'] = $game['moments'] ?? [];
                usort($game['moments'], fn ($a, $b) => moment_sort_key($a) <=> moment_sort_key($b));
                return $game;
            }
        }
        return null;
    }
}

if (!function_exists('create_game')) {
    function create_game(array $payload): string
    {
        $data = load_data();
        $id = 'game_' . time() . '_' . substr(md5(uniqid('', true)), 0, 6);

        $game = [
            'id' => $id,
            'title' => trim((string)($payload['title'] ?? 'Novo jogo')),
            'team' => trim((string)($payload['team'] ?? '')),
            'opponent' => trim((string)($payload['opponent'] ?? '')),
            'date' => trim((string)($payload['date'] ?? '')),
            'youtube_url' => normalize_youtube_url((string)($payload['youtube_url'] ?? '')),
            'notes' => trim((string)($payload['notes'] ?? '')),
            'moments' => [],
            'created_at' => date('c'),
        ];

        $data['games'][] = $game;
        save_data($data);
        return $id;
    }
}


if (!function_exists('update_game')) {
    function update_game(string $game_id, array $payload): bool
    {
        $data = load_data();
        foreach (($data['games'] ?? []) as $i => $game) {
            if (($game['id'] ?? '') !== $game_id) {
                continue;
            }
            if (array_key_exists('title', $payload)) {
                $title = trim((string)$payload['title']);
                $data['games'][$i]['title'] = $title !== '' ? $title : ($data['games'][$i]['title'] ?? 'Jogo');
            }
            if (array_key_exists('team', $payload)) {
                $data['games'][$i]['team'] = trim((string)$payload['team']);
            }
            if (array_key_exists('opponent', $payload)) {
                $data['games'][$i]['opponent'] = trim((string)$payload['opponent']);
            }
            if (array_key_exists('date', $payload)) {
                $data['games'][$i]['date'] = trim((string)$payload['date']);
            }
            if (array_key_exists('youtube_url', $payload)) {
                $data['games'][$i]['youtube_url'] = normalize_youtube_url((string)$payload['youtube_url']);
            }
            if (array_key_exists('notes', $payload)) {
                $data['games'][$i]['notes'] = trim((string)$payload['notes']);
            }
            $data['games'][$i]['updated_at'] = date('c');
            save_data($data);
            return true;
        }
        return false;
    }
}

if (!function_exists('create_moment')) {
    function create_moment(string $game_id, array $payload): string
    {
        $data = load_data();
        $gameIndex = null;

        foreach (($data['games'] ?? []) as $index => $game) {
            if (($game['id'] ?? '') === $game_id) {
                $gameIndex = $index;
                break;
            }
        }

        if ($gameIndex === null) {
            throw new RuntimeException('Jogo não encontrado.');
        }

        $id = 'moment_' . time() . '_' . substr(md5(uniqid('', true)), 0, 6);
        $start = max(0, (int)($payload['start'] ?? 0));
        $end = max($start + 1, (int)($payload['end'] ?? ($start + 10)));

        $moment = [
            'id' => $id,
            'title' => trim((string)($payload['title'] ?? 'Momento')),
            'athlete' => trim((string)($payload['athlete'] ?? '')),
            'category' => trim((string)($payload['category'] ?? 'Geral')),
            'tags' => array_values(array_filter(array_map('trim', preg_split('/,|\n/', (string)($payload['tags'] ?? ''))))),
            'start' => $start,
            'end' => $end,
            'notes' => trim((string)($payload['notes'] ?? '')),
            'importance' => (int)($payload['importance'] ?? 2),
            'quick' => !empty($payload['quick']),
            'created_at' => date('c'),
        ];

        if (!empty($data['games'][$gameIndex]['manual_order'])) {
            $max = 0.0;
            foreach (($data['games'][$gameIndex]['moments'] ?? []) as $om) {
                $max = max($max, moment_sort_key($om));
            }
            $moment['pos'] = $max + 1;
        }
        $data['games'][$gameIndex]['moments'][] = $moment;
        save_data($data);
        return $id;
    }
}

if (!function_exists('delete_moment')) {
    function delete_moment(string $game_id, string $moment_id): void
    {
        $data = load_data();
        foreach (($data['games'] ?? []) as $gameIndex => $game) {
            if (($game['id'] ?? '') === $game_id) {
                $data['games'][$gameIndex]['moments'] = array_values(array_filter(
                    $game['moments'] ?? [],
                    fn ($moment) => ($moment['id'] ?? '') !== $moment_id
                ));
                save_data($data);
                return;
            }
        }
    }
}

if (!function_exists('app_tags')) {
    function app_tags(): array
    {
        $data = load_data();
        $tags = array_key_exists('tags', $data) ? (array)$data['tags'] : default_tags();
        foreach (($data['games'] ?? []) as $g) {
            foreach (($g['moments'] ?? []) as $m) {
                foreach ((array)($m['tags'] ?? []) as $t) {
                    $t = trim((string)$t);
                    if ($t !== '') {
                        $tags[] = $t;
                    }
                }
            }
        }
        $seen = [];
        $out = [];
        foreach ($tags as $t) {
            $k = mb_strtolower((string)$t);
            if ($k !== '' && !isset($seen[$k])) {
                $seen[$k] = 1;
                $out[] = $t;
            }
        }
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }
}

if (!function_exists('add_tag')) {
    function add_tag(string $tag): bool
    {
        $tag = trim($tag);
        if ($tag === '') {
            return false;
        }

        $data = load_data();
        $existing = $data['tags'] ?? [];
        $existing[] = $tag;
        $existing = array_values(array_unique($existing));
        sort($existing);
        $data['tags'] = $existing;
        save_data($data);
        return true;
    }
}

if (!function_exists('rename_tag')) {
    function rename_tag(string $old, string $new): bool
    {
        $old = trim($old);
        $new = trim($new);
        if ($old === '' || $new === '') {
            return false;
        }

        $swap = function ($t) use ($old, $new) {
            return strcasecmp((string)$t, $old) === 0 ? $new : $t;
        };

        $data = load_data();
        $tags = array_values(array_unique(array_map($swap, $data['tags'] ?? [])));
        sort($tags);
        $data['tags'] = $tags;

        foreach (($data['games'] ?? []) as $gi => $game) {
            foreach (($game['moments'] ?? []) as $mi => $moment) {
                if (empty($moment['tags']) || !is_array($moment['tags'])) {
                    continue;
                }
                $data['games'][$gi]['moments'][$mi]['tags'] = array_values(array_unique(array_map($swap, $moment['tags'])));
            }
        }

        save_data($data);
        return true;
    }
}

if (!function_exists('delete_tag')) {
    function delete_tag(string $tag): bool
    {
        $tag = trim($tag);
        if ($tag === '') {
            return false;
        }

        $keep = function ($t) use ($tag) {
            return strcasecmp((string)$t, $tag) !== 0;
        };

        $data = load_data();
        $data['tags'] = array_values(array_filter($data['tags'] ?? [], $keep));

        foreach (($data['games'] ?? []) as $gi => $game) {
            foreach (($game['moments'] ?? []) as $mi => $moment) {
                if (empty($moment['tags']) || !is_array($moment['tags'])) {
                    continue;
                }
                $data['games'][$gi]['moments'][$mi]['tags'] = array_values(array_filter($moment['tags'], $keep));
            }
        }

        save_data($data);
        return true;
    }
}

if (!function_exists('moments_by_tag')) {
    function moments_by_tag(string $tag): array
    {
        $tag = strtolower(trim($tag));
        $out = [];
        if ($tag === '') {
            return $out;
        }
        foreach (app_games() as $game) {
            foreach ($game['moments'] ?? [] as $moment) {
                if (($moment['type'] ?? 'video') !== 'video' && !empty($moment['type'])) {
                    // include video moments primarily; also plain moments without type
                }
                $matched = false;
                foreach ((array)($moment['tags'] ?? []) as $value) {
                    if (strtolower(trim((string)$value)) === $tag) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }
                $m = $moment;
                $m['_game_id'] = $game['id'] ?? '';
                $m['_game_title'] = $game['title'] ?? '';
                $m['_youtube'] = $game['youtube_url'] ?? '';
                $out[] = $m;
            }
        }
        return $out;
    }
}

if (!function_exists('tag_matches_game')) {
    function tag_matches_game(array $game, string $tag): bool
    {
        $tag = strtolower(trim($tag));
        if ($tag === '') {
            return true;
        }

        foreach (($game['moments'] ?? []) as $moment) {
            foreach ((array)($moment['tags'] ?? []) as $value) {
                if (strtolower(trim((string)$value)) === $tag) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return current_user() !== null;
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!is_logged_in()) {
            header('Location: login.php');
            exit;
        }
    }
}

if (!function_exists('login_user')) {
    function login_user(string $username, string $password): bool
    {
        $u = find_user(trim($username));
        $ok = $u && !empty($u['pass_hash']) && password_verify($password, (string)$u['pass_hash']);

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['futsal_user'] = $u['username'];
            return true;
        }

        usleep(600000);
        return false;
    }
}

if (!function_exists('logout_user')) {
    function logout_user(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Pedido inválido ou sessão expirada. Volta atrás e tenta de novo.');
    }
}

if (!function_exists('delete_game')) {
    function delete_game(string $game_id): void
    {
        $data = load_data();
        $data['games'] = array_values(array_filter($data['games'] ?? [], fn ($g) => ($g['id'] ?? '') !== $game_id));
        save_data($data);
    }
}

if (!function_exists('update_moment')) {
    function update_moment(string $game_id, string $moment_id, array $p): void
    {
        $data = load_data();
        foreach (($data['games'] ?? []) as $gi => $game) {
            if (($game['id'] ?? '') !== $game_id) {
                continue;
            }
            foreach (($game['moments'] ?? []) as $mi => $m) {
                if (($m['id'] ?? '') !== $moment_id) {
                    continue;
                }
                $start = max(0, (int)($p['start'] ?? 0));
                $m['title'] = trim((string)($p['title'] ?? '')) ?: 'Momento';
                $m['athlete'] = trim((string)($p['athlete'] ?? ''));
                $m['category'] = trim((string)($p['category'] ?? '')) ?: 'Geral';
                $m['tags'] = array_values(array_filter(array_map('trim', preg_split('/,|\n/', (string)($p['tags'] ?? '')))));
                $m['start'] = $start;
                $m['end'] = max($start + 1, (int)($p['end'] ?? ($start + 10)));
                $m['notes'] = trim((string)($p['notes'] ?? ''));
                $m['importance'] = (int)($p['importance'] ?? 2);
                $m['quick'] = false;
                $data['games'][$gi]['moments'][$mi] = $m;
                save_data($data);
                return;
            }
        }
    }
}

if (!function_exists('moment_sort_key')) {
    function moment_sort_key(array $m): float
    {
        return isset($m['pos']) ? (float)$m['pos'] : (float)($m['start'] ?? 0);
    }
}

if (!function_exists('panel_position')) {
    // '' = sem alteração / no fim; '__start' = no início; id = logo a seguir a esse momento
    function panel_position(array $moments, string $after): ?float
    {
        if ($after === '__start') {
            return -1.0;
        }
        foreach ($moments as $m) {
            if ($after !== '' && ($m['id'] ?? '') === $after) {
                return moment_sort_key($m) + 0.5;
            }
        }
        return null;
    }
}

if (!function_exists('create_panel')) {
    function create_panel(string $game_id, array $p, ?float $pos): string
    {
        $data = load_data();
        foreach (($data['games'] ?? []) as $gi => $g) {
            if (($g['id'] ?? '') !== $game_id) {
                continue;
            }
            if ($pos === null) {
                $pos = 0.0;
                foreach (($g['moments'] ?? []) as $m) {
                    $pos = max($pos, moment_sort_key($m));
                }
                $pos += 1;
            }
            $id = 'panel_' . time() . '_' . substr(md5(uniqid('', true)), 0, 6);
            $data['games'][$gi]['moments'][] = [
                'id' => $id, 'type' => panel_type($p), 'board' => clean_board((string)($p['board_json'] ?? '')),
                'cover' => panel_type($p) === 'cover' ? ($p['cover_data'] ?? null) : null,
                'title' => trim((string)($p['title'] ?? '')) ?: 'Painel',
                'category' => trim((string)($p['category'] ?? '')),
                'notes' => trim((string)($p['notes'] ?? '')),
                'pos' => $pos, 'start' => 0, 'end' => 0, 'athlete' => '', 'tags' => tags_from_input($p['tags'] ?? ''), 'importance' => 2,
                'created_at' => date('c'),
            ];
            save_data($data);
            return $id;
        }
        throw new RuntimeException('Jogo não encontrado.');
    }
}

if (!function_exists('update_panel')) {
    function update_panel(string $game_id, string $moment_id, array $p, ?float $pos): void
    {
        $data = load_data();
        foreach (($data['games'] ?? []) as $gi => $g) {
            if (($g['id'] ?? '') !== $game_id) {
                continue;
            }
            foreach (($g['moments'] ?? []) as $mi => $m) {
                if (($m['id'] ?? '') !== $moment_id) {
                    continue;
                }
                $m['title'] = trim((string)($p['title'] ?? '')) ?: 'Painel';
                $m['category'] = trim((string)($p['category'] ?? ''));
                $m['notes'] = trim((string)($p['notes'] ?? ''));
                if (array_key_exists('tags', $p)) {
                    $m['tags'] = tags_from_input($p['tags']);
                }
                if (($m['type'] ?? '') === 'board' && ($nb = clean_board((string)($p['board_json'] ?? ''))) !== null) {
                    $m['board'] = $nb;
                }
                if (($m['type'] ?? '') === 'cover' && isset($p['cover_data'])) {
                    $m['cover'] = $p['cover_data'];
                }
                if ($pos !== null) {
                    $m['pos'] = $pos;
                }
                $data['games'][$gi]['moments'][$mi] = $m;
                save_data($data);
                return;
            }
        }
    }
}

if (!function_exists('panel_type')) {
    function panel_type(array $p): string
    {
        $t = (string)($p['ptype'] ?? 'text');
        return in_array($t, ['text', 'index', 'board', 'cover'], true) ? $t : 'text';
    }
}

if (!function_exists('clean_board')) {
    function clean_board(string $json): ?array
    {
        $b = json_decode($json, true);
        if (!is_array($b) || empty($b['players']) || !is_array($b['players'])) {
            return null;
        }
        $pl = [];
        foreach (array_slice($b['players'], 0, 20) as $p) {
            $pl[] = [
                't' => (($p['t'] ?? 'a') === 'b') ? 'b' : 'a',
                'l' => mb_substr((string)($p['l'] ?? ''), 0, 3),
                'g' => empty($p['g']) ? 0 : 1,
                'x' => max(-20, min(420, (float)($p['x'] ?? 0))),
                'y' => max(-10, min(270, (float)($p['y'] ?? 0))),
            ];
        }
        $out = ['players' => $pl, 'ball' => [
            'x' => max(-20, min(420, (float)($b['ball']['x'] ?? 200))),
            'y' => max(-10, min(270, (float)($b['ball']['y'] ?? 110))),
        ]];
        if (!empty($b['colors']) && is_array($b['colors'])) {
            $out['colors'] = [
                'a' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($b['colors']['a'] ?? '')) ? $b['colors']['a'] : '#131316',
                'b' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($b['colors']['b'] ?? '')) ? $b['colors']['b'] : '#d42a42',
                'ga' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($b['colors']['ga'] ?? '')) ? $b['colors']['ga'] : '#e0a100',
                'gb' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($b['colors']['gb'] ?? '')) ? $b['colors']['gb'] : '#e0650a',
            ];
        }
        if (!empty($b['frames']) && is_array($b['frames'])) {
            $frames = [];
            foreach (array_slice($b['frames'], 0, 40) as $fr) {
                if (!is_array($fr) || empty($fr['players'])) continue;
                $fpl = [];
                foreach (array_slice($fr['players'], 0, 20) as $p) {
                    $fpl[] = [
                        't' => (($p['t'] ?? 'a') === 'b') ? 'b' : 'a',
                        'l' => mb_substr((string)($p['l'] ?? ''), 0, 3),
                        'g' => empty($p['g']) ? 0 : 1,
                        'x' => max(-20, min(420, (float)($p['x'] ?? 0))),
                        'y' => max(-10, min(270, (float)($p['y'] ?? 0))),
                    ];
                }
                $frames[] = [
                    'players' => $fpl,
                    'ball' => [
                        'x' => max(-20, min(420, (float)($fr['ball']['x'] ?? 200))),
                        'y' => max(-10, min(270, (float)($fr['ball']['y'] ?? 110))),
                    ],
                ];
            }
            if ($frames) {
                $out['frames'] = $frames;
            }
        }
        return $out;
    }
}

if (!function_exists('reorder_moments')) {
    function reorder_moments(string $game_id, array $ids): void
    {
        $data = load_data();
        foreach (($data['games'] ?? []) as $gi => $g) {
            if (($g['id'] ?? '') !== $game_id) {
                continue;
            }
            $pos = [];
            $n = 1;
            foreach ($ids as $oid) {
                $pos[(string)$oid] = $n++;
            }
            foreach (($g['moments'] ?? []) as $mi => $m) {
                $data['games'][$gi]['moments'][$mi]['pos'] = $pos[$m['id'] ?? ''] ?? $n++;
            }
            $data['games'][$gi]['manual_order'] = true;
            save_data($data);
            return;
        }
    }
}

/* ---------- Utilizadores e permissões ---------- */
if (!function_exists('users_file')) {
    function users_file(): string
    {
        return dirname((string)app_config('data_file', __DIR__ . '/data/app.json')) . '/users.json';
    }
}

if (!function_exists('save_users')) {
    function save_users(array $users): void
    {
        $file = users_file();
        $tmp = $file . '.tmp';
        $json = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $file)) {
            throw new RuntimeException('Não foi possível gravar os utilizadores. Verifica as permissões de data/.');
        }
    }
}

if (!function_exists('load_users')) {
    function load_users(): array
    {
        $file = users_file();
        if (!is_file($file)) {
            $seed = [[
                'username' => (string)app_config('login_user', 'coach'),
                'pass_hash' => (string)app_config('login_pass_hash', ''),
                'role' => 'admin',
                'created_at' => date('c'),
            ]];
            save_users($seed);
            return $seed;
        }
        $u = json_decode((string)file_get_contents($file), true);
        return is_array($u) ? $u : [];
    }
}

if (!function_exists('find_user')) {
    function find_user(string $name): ?array
    {
        foreach (load_users() as $u) {
            if (strtolower((string)($u['username'] ?? '')) === strtolower($name) && $name !== '') {
                return $u;
            }
        }
        return null;
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        static $cache = false;
        if ($cache !== false) {
            return $cache;
        }
        $n = (string)($_SESSION['futsal_user'] ?? '');
        $u = $n !== '' ? find_user($n) : null;
        if ($n !== '' && !$u) {
            unset($_SESSION['futsal_user']);
        }
        return $cache = $u;
    }
}

if (!function_exists('current_role')) {
    function current_role(): string
    {
        return (string)(current_user()['role'] ?? '');
    }
}

if (!function_exists('can_edit')) {
    function can_edit(): bool
    {
        return in_array(current_role(), ['admin', 'editor'], true);
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return current_role() === 'admin';
    }
}

if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        if (!is_admin()) {
            http_response_code(403);
            exit('Sem permissão.');
        }
    }
}

// Conta só de visualização: nenhum pedido que altere dados (exceto mudar a própria palavra-passe)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && is_logged_in() && !can_edit()
    && !in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['account.php', 'logout.php'], true)) {
    http_response_code(403);
    exit('Sem permissão: conta só de visualização.');
}

/* ---------- Capas (emblemas e cores) ---------- */
if (!function_exists('upload_dir')) {
    function upload_dir(): string
    {
        $d = __DIR__ . '/uploads';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        if (!is_file($d . '/.htaccess')) {
            @file_put_contents($d . '/.htaccess', "Options -Indexes\nRemoveHandler .php .phtml .phar\n<FilesMatch \"\\.(php|phtml|phar|html?)$\">\n    Require all denied\n</FilesMatch>\n");
        }
        if (!is_file($d . '/index.html')) {
            @file_put_contents($d . '/index.html', '');
        }
        return $d;
    }
}

if (!function_exists('save_emblem')) {
    // Devolve o caminho relativo (uploads/xxx.png) ou null se o ficheiro não for uma imagem válida.
    function save_emblem(array $file): ?string
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            return null;
        }
        $info = @getimagesize($file['tmp_name']);
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'][$info['mime'] ?? ''] ?? null;
        if (!$ext) {
            return null;
        }
        $name = 'emblem_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], upload_dir() . '/' . $name)) {
            return null;
        }
        return 'uploads/' . $name;
    }
}

if (!function_exists('delete_upload')) {
    function delete_upload(?string $rel): void
    {
        if ($rel && preg_match('#^uploads/emblem_[a-f0-9]{16}\.(png|jpg|webp|gif)$#', $rel)) {
            @unlink(__DIR__ . '/' . $rel);
        }
    }
}

if (!function_exists('cover_from_request')) {
    function cover_from_request(array $post, array $files, ?array $old): array
    {
        $col = fn ($v, $d) => preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v) ? (string)$v : $d;
        $c = [
            'bg' => $col($post['cover_bg'] ?? '', $old['bg'] ?? '#0b1026'),
            'fg' => $col($post['cover_fg'] ?? '', $old['fg'] ?? '#ffffff'),
            'emblem' => $old['emblem'] ?? '',
            'emblem2' => $old['emblem2'] ?? '',
        ];
        foreach (['emblem' => 'cover_emblem', 'emblem2' => 'cover_emblem2'] as $k => $field) {
            if (!empty($post['remove_' . $k])) {
                delete_upload($c[$k]);
                $c[$k] = '';
            }
            if (isset($files[$field]) && ($new = save_emblem($files[$field]))) {
                delete_upload($c[$k]);
                $c[$k] = $new;
            }
        }
        return $c;
    }
}

/* ---------- Auxiliares de interface (ícones SVG, versão dos ficheiros, migalhas) ---------- */
if (!function_exists('asset')) {
    // Acrescenta ?v=<data de modificação> para o browser/PWA apanhar sempre a versão nova.
    function asset(string $path): string
    {
        $f = __DIR__ . '/' . ltrim($path, '/');
        return $path . (is_file($f) ? '?v=' . filemtime($f) : '');
    }
}

if (!function_exists('icon')) {
    function icon(string $name, string $class = ''): string
    {
        return '<svg class="ic' . ($class !== '' ? ' ' . htmlspecialchars($class) : '') . '" aria-hidden="true" focusable="false"><use href="#i-' . htmlspecialchars($name) . '"/></svg>';
    }
}

if (!function_exists('crumbs')) {
    // crumbs([['Jogos', 'index.php'], 'Título atual'])
    function crumbs(array $items): string
    {
        $out = [];
        foreach ($items as $it) {
            if (is_array($it)) {
                $out[] = '<a href="' . htmlspecialchars($it[1]) . '">' . htmlspecialchars($it[0]) . '</a>';
            } else {
                $out[] = '<span class="crumb-now">' . htmlspecialchars((string)$it) . '</span>';
            }
        }
        return '<nav class="crumbs" aria-label="Localização">' . implode('<span class="crumb-sep" aria-hidden="true"></span>', $out) . '</nav>';
    }
}

/* ---------- Tags, animações e filtros da biblioteca de momentos ---------- */
if (!function_exists('tags_from_input')) {
    function tags_from_input($raw): array
    {
        $parts = is_array($raw) ? $raw : preg_split('/,|\n/', (string)$raw);
        $out = [];
        $seen = [];
        foreach ($parts as $t) {
            $t = trim((string)$t);
            $k = mb_strtolower($t);
            if ($t !== '' && !isset($seen[$k])) {
                $seen[$k] = 1;
                $out[] = $t;
            }
        }
        return $out;
    }
}

if (!function_exists('is_animated')) {
    // Quadro com pelo menos 2 fotogramas
    function is_animated(array $m): bool
    {
        return ($m['type'] ?? '') === 'board' && count((array)($m['board']['frames'] ?? [])) >= 2;
    }
}

if (!function_exists('moments_filtered')) {
    /**
     * Filtros: tag, q (texto), kind (all|video|board|text|cover), anim (any|with|without), game (id).
     * 'with' = só quadros com animação; 'without' = tudo menos animações.
     * Os painéis de índice (menus) nunca entram.
     */
    function moments_filtered(array $f): array
    {
        $tag = mb_strtolower(trim((string)($f['tag'] ?? '')));
        $q = mb_strtolower(trim((string)($f['q'] ?? '')));
        $kind = (string)($f['kind'] ?? 'all');
        $anim = (string)($f['anim'] ?? 'any');
        $gameId = (string)($f['game'] ?? '');
        $out = [];
        foreach (app_games() as $game) {
            if ($gameId !== '' && ($game['id'] ?? '') !== $gameId) {
                continue;
            }
            $moments = (array)($game['moments'] ?? []);
            usort($moments, fn ($a, $b) => moment_sort_key($a) <=> moment_sort_key($b));
            foreach ($moments as $m) {
                $type = $m['type'] ?? 'video';
                if ($type === 'index') {
                    continue;
                }
                if ($kind !== 'all' && $kind !== '' && ($type === 'video' ? 'video' : $type) !== $kind) {
                    continue;
                }
                $isAnim = is_animated($m);
                if (($anim === 'with' && !$isAnim) || ($anim === 'without' && $isAnim)) {
                    continue;
                }
                if ($tag !== '') {
                    $hit = false;
                    foreach ((array)($m['tags'] ?? []) as $t) {
                        if (mb_strtolower(trim((string)$t)) === $tag) {
                            $hit = true;
                            break;
                        }
                    }
                    if (!$hit) {
                        continue;
                    }
                }
                if ($q !== '') {
                    $hay = mb_strtolower(($m['title'] ?? '') . ' ' . ($m['athlete'] ?? '') . ' ' . ($m['category'] ?? '') . ' ' . ($m['notes'] ?? '') . ' ' . ($game['title'] ?? '') . ' ' . implode(' ', (array)($m['tags'] ?? [])));
                    if (mb_strpos($hay, $q) === false) {
                        continue;
                    }
                }
                $m['_game_id'] = $game['id'] ?? '';
                $m['_game_title'] = $game['title'] ?? '';
                $m['_youtube'] = $game['youtube_url'] ?? '';
                $m['_animated'] = $isAnim;
                $out[] = $m;
            }
        }
        return $out;
    }
}

if (!function_exists('filters_from_request')) {
    function filters_from_request(array $src): array
    {
        $anim = (string)($src['anim'] ?? 'any');
        $kind = (string)($src['kind'] ?? 'all');
        return [
            'tag' => trim((string)($src['tag'] ?? '')),
            'q' => trim((string)($src['q'] ?? '')),
            'kind' => in_array($kind, ['all', 'video', 'board', 'text', 'cover'], true) ? $kind : 'all',
            'anim' => in_array($anim, ['any', 'with', 'without'], true) ? $anim : 'any',
            'game' => trim((string)($src['game'] ?? '')),
        ];
    }
}
