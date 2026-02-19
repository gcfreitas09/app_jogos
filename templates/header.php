<?php
declare(strict_types=1);

$title = $title ?? APP_NAME;
$flashMessages = pull_flash_messages();
$currentUser = $currentUser ?? null;
$pageClass = isset($pageClass) ? trim((string) $pageClass) : '';

$currentPath = basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
if ($currentPath === '') {
    $currentPath = 'explore.php';
}

$contextLinks = [
    ['href' => url('explore.php'), 'label' => 'Explorar', 'matches' => ['explore.php', 'index.php']],
    ['href' => url('my_games.php'), 'label' => 'Meus Jogos', 'matches' => ['my_games.php', 'invite.php']],
    ['href' => url('create_invite.php'), 'label' => 'Criar Convite', 'matches' => ['create_invite.php']],
    ['href' => url('profile.php'), 'label' => 'Perfil', 'matches' => ['profile.php']],
];

$pageHints = [
    'my_games.php' => 'Acompanhe suas confirmacoes e gerencie convites criados por voce.',
    'create_invite.php' => 'Preencha os dados e compartilhe um convite objetivo para completar as vagas rapido.',
    'profile.php' => 'Mantenha preferencias e foto atualizadas para melhorar recomendacoes.',
    'invite.php' => 'Verifique os participantes e confirme as informacoes antes do horario do jogo.',
];
$currentPageHint = $pageHints[$currentPath] ?? 'Use os atalhos para navegar e organizar seus jogos com mais rapidez.';

$showContextRails = false;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title); ?> - <?php echo e(APP_NAME); ?></title>
    <script>
        (function () {
            var key = 'app_jogos_theme';
            var root = document.documentElement;
            var saved = null;
            try {
                saved = window.localStorage ? window.localStorage.getItem(key) : null;
            } catch (error) {
                saved = null;
            }

            if (saved === 'dark' || saved === 'light') {
                root.setAttribute('data-theme', saved);
                return;
            }

            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                root.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Archivo:wght@600;700&display=swap');
        :root {
            --layout-max: 1360px;
            --bg: #eef6f8;
            --bg-mesh: radial-gradient(circle at 15% 20%, #d2f4ea 0, transparent 45%),
                       radial-gradient(circle at 85% 0, #ffe4cc 0, transparent 36%),
                       linear-gradient(160deg, #edf5ff 0%, #f8fafc 60%, #f5fdf8 100%);
            --card: #ffffff;
            --text: #162531;
            --muted: #5c6f7b;
            --primary: #0b7f6f;
            --primary-dark: #06665a;
            --accent: #f97316;
            --success-bg: #e8faf5;
            --error-bg: #ffeaee;
            --border: #d6e1e7;
            --shadow: 0 12px 40px rgba(12, 42, 56, 0.12);
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: "Space Grotesk", "Trebuchet MS", sans-serif;
            background: var(--bg-mesh);
            color: var(--text);
            min-height: 100vh;
        }
        img, video, iframe {
            max-width: 100%;
        }
        .container {
            width: min(100%, var(--layout-max));
            margin: 0 auto;
            padding: 24px 20px 30px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
        }
        .card-soft {
            background: linear-gradient(180deg, #ffffff 0%, #fdfefe 100%);
        }
        h1, h2, h3 {
            font-family: "Archivo", "Verdana", sans-serif;
            letter-spacing: -0.02em;
            margin: 0 0 8px;
        }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(166, 188, 201, 0.35);
            min-height: 66px;
        }
        .app-corner-brand {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 700;
            text-decoration: none;
            color: var(--primary-dark);
            font-size: 20px;
            letter-spacing: -0.01em;
        }
        .topbar-inner {
            width: min(100%, var(--layout-max));
            margin: 0 auto;
            padding: 14px 20px 14px 180px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .brand {
            font-weight: 700;
            text-decoration: none;
            color: var(--primary-dark);
            font-size: 20px;
        }
        .user-spot {
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 20px;
            line-height: 1.1;
            letter-spacing: -0.01em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 280px;
        }
        .nav {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            min-width: 0;
        }
        .nav a {
            margin-left: 0;
            padding: 8px 10px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--primary-dark);
            font-weight: 600;
            transition: background 0.2s ease;
            white-space: nowrap;
        }
        .nav a.is-active {
            background: #e4f6f0;
            color: #085c52;
        }
        .nav a:hover {
            background: #eaf3f2;
        }
        .theme-switch {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px;
            border-radius: 999px;
            border: 1px solid #c7d8e2;
            background: #fff;
        }
        .theme-choice {
            width: 34px !important;
            min-width: 34px;
            height: 34px;
            padding: 0 !important;
            border-radius: 999px !important;
            border: 1px solid transparent !important;
            background: transparent !important;
            color: #31536a !important;
            font-size: 16px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none !important;
            transform: none !important;
        }
        .theme-choice:hover {
            background: #eaf5fa !important;
            transform: none !important;
            box-shadow: none !important;
        }
        .theme-choice.is-selected {
            background: #e4f6f0 !important;
            color: #0b6d5f !important;
            border-color: #9dd3c4 !important;
        }
        .muted {
            color: var(--muted);
        }
        .flash {
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 10px;
            font-size: 15px;
        }
        .flash.success {
            background: var(--success-bg);
            color: #0f5e46;
            border: 1px solid #bce8d8;
        }
        .flash.error {
            background: var(--error-bg);
            color: #8c2733;
            border: 1px solid #f4c8cf;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }
        input, select, textarea, button {
            width: 100%;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid #c9d8df;
            font-size: 15px;
            font-family: inherit;
            background: #fff;
        }
        textarea {
            min-height: 96px;
            resize: vertical;
        }
        input:focus, select:focus, textarea:focus {
            outline: 2px solid rgba(11, 127, 111, 0.35);
            border-color: var(--primary);
        }
        .grid {
            display: grid;
            gap: 14px;
        }
        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        button, .btn {
            background: var(--primary);
            color: #fff;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        button:hover, .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(6, 102, 90, 0.25);
        }
        .btn-outline {
            background: #fff;
            color: var(--primary-dark);
            border: 1px solid var(--primary);
        }
        .btn-outline:hover {
            background: #eef9f7;
            box-shadow: none;
        }
        .btn-lg {
            padding: 12px 18px;
            font-size: 15px;
            width: auto;
        }
        .btn-danger {
            background: #be3d4e;
            width: auto;
        }
        .btn-danger:hover {
            background: #a33343;
            box-shadow: 0 8px 18px rgba(190, 61, 78, 0.28);
        }
        .btn-disabled {
            background: #cfd9df;
            color: #64717d;
            width: auto;
            cursor: not-allowed;
        }
        .error-list {
            margin: 0 0 16px;
            padding-left: 20px;
            color: #b93045;
        }
        .hero {
            background: linear-gradient(130deg, #0d7f6d 0%, #0d5f98 55%, #174c74 100%);
            border-radius: 20px;
            color: #fff;
            padding: 24px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 16px 30px rgba(16, 71, 101, 0.28);
        }
        .hero h1 {
            margin: 0;
            font-size: clamp(26px, 4vw, 34px);
        }
        .hero p {
            margin: 8px 0 0;
            color: rgba(235, 245, 255, 0.88);
            max-width: 560px;
        }
        .hero-content {
            max-width: 700px;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(160px, 1fr));
            gap: 12px;
            align-items: end;
        }
        .filter-check label {
            margin-bottom: 8px;
        }
        .filter-check input {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: var(--accent);
            padding: 0;
            border: none;
            background: transparent;
        }
        .filter-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .section-block {
            margin-top: 14px;
        }
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .pill-count {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            background: #e7f2f9;
            color: #27587e;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 10px;
            border: 1px solid #c8deec;
        }
        .empty-state {
            padding: 18px;
            border-radius: 14px;
            color: #4d6476;
            border: 1px dashed #bfd1dc;
        }
        .invite-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px;
        }
        .invite-card {
            background: #fff;
            border: 1px solid #d4e1e7;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 8px 22px rgba(34, 62, 83, 0.08);
        }
        .invite-card-past {
            opacity: 0.74;
            filter: grayscale(0.12);
        }
        .invite-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge.sport {
            background: #fff4e8;
            color: #bf4d04;
        }
        .badge.status {
            border: 1px solid transparent;
        }
        .badge.status.open {
            background: #e8faf5;
            color: #0b664e;
            border-color: #bce8d8;
        }
        .badge.status.full {
            background: #ffecef;
            color: #9e2e3f;
            border-color: #efbec6;
        }
        .badge.status.ended {
            background: #eef1f4;
            color: #586678;
            border-color: #d3dce5;
        }
        .slots-row {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #e2eaef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }
        .actions-row {
            margin-top: 14px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .inline-form {
            display: inline-block;
            min-width: 120px;
            width: auto;
        }
        .inline-form button {
            width: auto;
        }
        .hint {
            color: #6b7d8d;
            font-size: 13px;
            font-weight: 600;
        }
        .context-rails {
            display: none;
        }
        .context-rail {
            position: fixed;
            top: 96px;
            width: 220px;
            z-index: 6;
            max-height: calc(100vh - 118px);
            overflow: auto;
        }
        .context-rail-left {
            left: max(12px, calc((100vw - 980px) / 2 - 236px));
        }
        .context-rail-right {
            right: max(12px, calc((100vw - 980px) / 2 - 236px));
        }
        .context-card {
            border: 1px solid #d4e1e8;
            border-radius: 14px;
            padding: 12px;
            background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
            box-shadow: 0 10px 22px rgba(18, 41, 57, 0.1);
            margin-bottom: 12px;
        }
        .context-card h3 {
            margin: 0 0 9px;
            font-size: 18px;
        }
        .context-links {
            display: grid;
            gap: 8px;
        }
        .context-link {
            display: block;
            text-decoration: none;
            border: 1px solid #d1dfe8;
            border-radius: 11px;
            background: #fff;
            color: #1e435b;
            font-weight: 700;
            padding: 8px 10px;
            font-size: 14px;
        }
        .context-link:hover {
            background: #ecf7fb;
        }
        .context-link.is-active {
            border-color: #8ec6b7;
            background: #e8f8f3;
            color: #065f54;
        }
        .context-note {
            font-size: 13px;
            color: #5e7688;
            margin: 0;
            line-height: 1.4;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .detail-label {
            display: block;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 4px;
        }
        .participant-list {
            display: grid;
            gap: 8px;
            margin-top: 10px;
        }
        .participant-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            border: 1px solid #dbe6ec;
            border-radius: 12px;
            padding: 10px 12px;
            background: #fcfefe;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
        }
        th, td {
            border-bottom: 1px solid #e3ebf0;
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .text-right {
            text-align: right;
        }
        html[data-theme='dark'] {
            --bg: #0f1722;
            --bg-mesh: radial-gradient(circle at 15% 20%, #123037 0, transparent 45%),
                       radial-gradient(circle at 85% 0, #2f2418 0, transparent 36%),
                       linear-gradient(165deg, #0b131c 0%, #111b28 58%, #101f19 100%);
            --card: #172433;
            --text: #e3edf5;
            --muted: #9ab0c1;
            --primary: #13a58f;
            --primary-dark: #0f8d7b;
            --accent: #f59e0b;
            --success-bg: #163a2d;
            --error-bg: #42232b;
            --border: #314556;
            --shadow: 0 12px 36px rgba(0, 0, 0, 0.38);
        }
        html[data-theme='dark'] .topbar {
            background: rgba(12, 20, 30, 0.92);
            border-bottom-color: #2a3a49;
        }
        html[data-theme='dark'] .nav a.is-active {
            background: #18383f;
            color: #b9ece3;
        }
        html[data-theme='dark'] .nav a:hover {
            background: #1b3040;
        }
        html[data-theme='dark'] .app-corner-brand,
        html[data-theme='dark'] .user-spot {
            color: #d9e8f5;
        }
        html[data-theme='dark'] .theme-switch {
            background: #1a2b3a;
            border-color: #325168;
        }
        html[data-theme='dark'] .theme-choice {
            color: #c4d9ea !important;
        }
        html[data-theme='dark'] .theme-choice:hover {
            background: #20374a !important;
        }
        html[data-theme='dark'] .theme-choice.is-selected {
            background: #2b5563 !important;
            border-color: #4d8aa0 !important;
            color: #dff5ff !important;
        }
        html[data-theme='dark'] input,
        html[data-theme='dark'] select,
        html[data-theme='dark'] textarea {
            background: #162736;
            border-color: #345067;
            color: #e3edf5;
        }
        html[data-theme='dark'] .btn-outline {
            background: #1a2b3a;
            color: #b5dcec;
            border-color: #39607d;
        }
        html[data-theme='dark'] .btn-outline:hover {
            background: #24384b;
        }
        html[data-theme='dark'] .card,
        html[data-theme='dark'] .card-soft,
        html[data-theme='dark'] .invite-card,
        html[data-theme='dark'] .participant-item,
        html[data-theme='dark'] table,
        html[data-theme='dark'] tr,
        html[data-theme='dark'] .context-card,
        html[data-theme='dark'] .context-link,
        html[data-theme='dark'] .side-card,
        html[data-theme='dark'] .game-card,
        html[data-theme='dark'] .mobile-filters,
        html[data-theme='dark'] .mobile-summary div,
        html[data-theme='dark'] .my-calendar-panel,
        html[data-theme='dark'] .my-calendar-info,
        html[data-theme='dark'] .my-calendar-item,
        html[data-theme='dark'] .side-item,
        html[data-theme='dark'] .creator-row,
        html[data-theme='dark'] .community-item,
        html[data-theme='dark'] .community-creator-row {
            background: #172433 !important;
            border-color: #30495d !important;
            color: #e3edf5 !important;
        }
        html[data-theme='dark'] .muted,
        html[data-theme='dark'] .hint,
        html[data-theme='dark'] .context-note,
        html[data-theme='dark'] .side-empty,
        html[data-theme='dark'] .location-inline,
        html[data-theme='dark'] .my-calendar-item small,
        html[data-theme='dark'] .community-item small,
        html[data-theme='dark'] .community-creator-row small {
            color: #9ab0c1 !important;
        }
        html[data-theme='dark'] .meta-chip,
        html[data-theme='dark'] .pill-count,
        html[data-theme='dark'] .toggle-field,
        html[data-theme='dark'] .quick-chip,
        html[data-theme='dark'] .my-calendar-tag {
            background: #1e3242 !important;
            border-color: #35536a !important;
            color: #c4ddee !important;
        }
        html[data-theme='dark'] .my-calendar-day {
            background: #162836 !important;
            border-color: #345067 !important;
            color: #dce9f2 !important;
        }
        html[data-theme='dark'] .my-calendar-day.has-games {
            background: #183b34 !important;
            border-color: #2e7567 !important;
        }
        html[data-theme='dark'] .my-calendar-day.is-selected {
            background: #225146 !important;
            border-color: #4ca695 !important;
        }
        html[data-theme='dark'] .my-calendar-day small {
            background: #17b39a !important;
            color: #092a24 !important;
        }
        html[data-theme='dark'] .community-item:hover,
        html[data-theme='dark'] .my-calendar-item:hover,
        html[data-theme='dark'] .context-link:hover,
        html[data-theme='dark'] .side-item:hover {
            background: #1f3445 !important;
        }
        html[data-theme='dark'] .hero {
            box-shadow: 0 18px 34px rgba(0, 0, 0, 0.42);
        }
        html[data-theme='dark'] .leaflet-container {
            filter: brightness(0.9) contrast(1.05) saturate(0.92);
        }
        @media (max-width: 920px) {
            .topbar {
                min-height: auto;
            }
            .app-corner-brand {
                left: 12px;
                font-size: 14px;
            }
            .topbar-inner {
                padding: 10px 12px 10px 86px;
                gap: 8px;
                align-items: flex-start;
            }
            .user-spot {
                display: none;
            }
            .nav {
                width: 100%;
                flex-wrap: nowrap;
                overflow-x: auto;
                overflow-y: hidden;
                padding-bottom: 4px;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }
            .nav a,
            .theme-switch {
                flex: 0 0 auto;
            }
            .theme-switch {
                padding: 2px;
                gap: 2px;
            }
            .theme-choice {
                width: 26px !important;
                min-width: 26px;
                height: 26px;
                font-size: 12px;
            }
            .filter-grid {
                grid-template-columns: repeat(2, minmax(150px, 1fr));
            }
        }
        @media (max-width: 760px) {
            body {
                font-size: 14px;
            }
            .app-corner-brand {
                left: 8px;
                font-size: 13px;
            }
            .topbar-inner {
                padding: 8px 10px 8px 92px;
            }
            .nav a {
                font-size: 13px;
                padding: 7px 9px;
            }
            .theme-switch {
                padding: 1px;
                gap: 1px;
            }
            .theme-choice {
                width: 22px !important;
                min-width: 22px;
                height: 22px;
                font-size: 10px;
            }
            h1 {
                font-size: 1.75rem;
            }
            h2 {
                font-size: 1.45rem;
            }
            h3 {
                font-size: 1.2rem;
            }
            .container {
                padding: 14px 10px 20px;
            }
            .card {
                padding: 14px;
                border-radius: 14px;
            }
            .hero {
                padding: 18px;
                border-radius: 16px;
                flex-direction: column;
                align-items: flex-start;
            }
            .hero h1 {
                font-size: clamp(22px, 7vw, 30px);
            }
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .filter-actions {
                grid-template-columns: 1fr;
            }
            .participant-item {
                flex-direction: column;
                align-items: flex-start;
            }
            label,
            .muted,
            .hint {
                font-size: 13px;
            }
            input,
            select,
            textarea,
            button {
                font-size: 15px;
            }
            .actions-row .btn,
            .actions-row .inline-form,
            .actions-row .inline-form button {
                width: 100%;
            }
            .btn-lg {
                width: 100%;
            }
            .context-rails {
                display: none !important;
            }
        }
        @media (max-width: 560px) {
            body {
                font-size: 13px;
            }
            .app-corner-brand {
                left: 6px;
                font-size: 11px;
            }
            .topbar-inner {
                padding-left: 88px;
            }
            .nav a {
                font-size: 12px;
                padding: 6px 8px;
            }
            .theme-switch {
                padding: 1px;
                gap: 1px;
            }
            h1 {
                font-size: 1.55rem;
            }
            h2 {
                font-size: 1.3rem;
            }
            h3 {
                font-size: 1.1rem;
            }
            .theme-choice {
                width: 20px !important;
                min-width: 20px;
                height: 20px;
                font-size: 9px;
            }
            label,
            .muted,
            .hint {
                font-size: 12px;
            }
            input,
            select,
            textarea,
            button {
                font-size: 14px;
            }
        }
        @media (max-width: 680px) {
            table, thead, tbody, tr, th, td {
                display: block;
                width: 100%;
            }
            tr {
                border: 1px solid #dce7ed;
                border-radius: 8px;
                margin-bottom: 10px;
                padding: 8px;
                background: #fff;
            }
            th {
                display: none;
            }
            td {
                border: none;
                padding: 6px 0;
            }
        }
        @media (min-width: 1460px) {
            .context-rails {
                display: block;
            }
        }
    </style>
</head>
<body<?php echo $pageClass !== '' ? ' class="' . e($pageClass) . '"' : ''; ?>>
    <header class="topbar">
        <a class="app-corner-brand" href="<?php echo e(url('explore.php')); ?>"><?php echo e(APP_NAME); ?></a>
        <div class="topbar-inner">
            <?php if ($currentUser !== null): ?>
                <span class="user-spot"><?php echo e((string) $currentUser['name']); ?></span>
            <?php else: ?>
                <span class="user-spot">Bem-vindo</span>
            <?php endif; ?>
            <nav class="nav">
                <?php if ($currentUser !== null): ?>
                    <div class="theme-switch" role="group" aria-label="Alternar tema">
                        <button type="button" class="theme-choice" data-theme-choice="light" aria-label="Tema claro" title="Tema claro">&#9728;</button>
                        <button type="button" class="theme-choice" data-theme-choice="dark" aria-label="Tema escuro" title="Tema escuro">&#9790;</button>
                    </div>
                    <?php foreach ($contextLinks as $link): ?>
                        <?php $isActive = in_array($currentPath, $link['matches'], true); ?>
                        <a class="<?php echo $isActive ? 'is-active' : ''; ?>" href="<?php echo e($link['href']); ?>">
                            <?php echo e($link['label']); ?>
                        </a>
                    <?php endforeach; ?>
                    <a href="<?php echo e(url('logout.php')); ?>">Sair</a>
                <?php else: ?>
                    <div class="theme-switch" role="group" aria-label="Alternar tema">
                        <button type="button" class="theme-choice" data-theme-choice="light" aria-label="Tema claro" title="Tema claro">&#9728;</button>
                        <button type="button" class="theme-choice" data-theme-choice="dark" aria-label="Tema escuro" title="Tema escuro">&#9790;</button>
                    </div>
                    <a href="<?php echo e(url('login.php')); ?>">Entrar</a>
                    <a href="<?php echo e(url('register.php')); ?>">Cadastrar</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <?php if ($showContextRails): ?>
        <div class="context-rails">
            <aside class="context-rail context-rail-left">
                <section class="context-card">
                    <h3>Navega&ccedil;&atilde;o</h3>
                    <div class="context-links">
                        <?php foreach ($contextLinks as $link): ?>
                            <?php $isActive = in_array($currentPath, $link['matches'], true); ?>
                            <a class="context-link <?php echo $isActive ? 'is-active' : ''; ?>" href="<?php echo e($link['href']); ?>">
                                <?php echo e($link['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <section class="context-card">
                    <h3>Resumo</h3>
                    <p class="context-note"><?php echo e($currentPageHint); ?></p>
                </section>
            </aside>
            <aside class="context-rail context-rail-right">
                <section class="context-card">
                    <h3>A&ccedil;&otilde;es r&aacute;pidas</h3>
                    <div class="context-links">
                        <a class="context-link" href="<?php echo e(url('create_invite.php')); ?>">+ Novo convite</a>
                        <a class="context-link" href="<?php echo e(url('my_games.php')); ?>">Ver meus jogos</a>
                        <a class="context-link" href="<?php echo e(url('explore.php')); ?>">Buscar partidas</a>
                    </div>
                </section>
                <section class="context-card">
                    <h3>Dica</h3>
                    <p class="context-note">Use filtros por esporte, n&iacute;vel e data para achar jogos com vagas mais r&aacute;pido.</p>
                </section>
            </aside>
        </div>
    <?php endif; ?>
    <script>
        (function () {
            var key = 'app_jogos_theme';
            var root = document.documentElement;
            var controls = Array.prototype.slice.call(document.querySelectorAll('[data-theme-choice]'));
            if (!controls.length) {
                return;
            }

            function getTheme() {
                return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            }

            function apply(theme) {
                if (theme === 'dark') {
                    root.setAttribute('data-theme', 'dark');
                } else {
                    root.removeAttribute('data-theme');
                    theme = 'light';
                }
                controls.forEach(function (control) {
                    var isSelected = control.getAttribute('data-theme-choice') === theme;
                    control.classList.toggle('is-selected', isSelected);
                    control.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                });
            }

            apply(getTheme());

            controls.forEach(function (control) {
                control.addEventListener('click', function () {
                    var targetTheme = control.getAttribute('data-theme-choice') === 'dark' ? 'dark' : 'light';
                    apply(targetTheme);
                    try {
                        if (window.localStorage) {
                            window.localStorage.setItem(key, targetTheme);
                        }
                    } catch (error) {
                        // Ignora erro de armazenamento.
                    }
                });
            });
        })();
    </script>
    <main class="container">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="flash <?php echo e((string) $flash['type']); ?>">
                <?php echo e((string) $flash['message']); ?>
            </div>
        <?php endforeach; ?>
