<?php
declare(strict_types=1);

$title = $title ?? APP_NAME;
$flashMessages = pull_flash_messages();
$currentUser = $currentUser ?? null;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title); ?> - <?php echo e(APP_NAME); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Archivo:wght@600;700&display=swap');
        :root {
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
        .container {
            width: min(100%, 980px);
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
        }
        .topbar-inner {
            width: min(100%, 980px);
            margin: 0 auto;
            padding: 14px 20px;
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
        .nav {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .nav a {
            margin-left: 0;
            padding: 8px 10px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--primary-dark);
            font-weight: 600;
            transition: background 0.2s ease;
        }
        .nav a:hover {
            background: #eaf3f2;
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
        @media (max-width: 920px) {
            .filter-grid {
                grid-template-columns: repeat(2, minmax(150px, 1fr));
            }
        }
        @media (max-width: 760px) {
            .hero {
                flex-direction: column;
                align-items: flex-start;
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
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="<?php echo e(url('explore.php')); ?>"><?php echo e(APP_NAME); ?></a>
            <nav class="nav">
                <?php if ($currentUser !== null): ?>
                    <span class="muted">Olá, <?php echo e((string) $currentUser['name']); ?></span>
                    <a href="<?php echo e(url('explore.php')); ?>">Explorar</a>
                    <a href="<?php echo e(url('my_games.php')); ?>">Meus Jogos</a>
                    <a href="<?php echo e(url('create_invite.php')); ?>">Criar Convite</a>
                    <a href="<?php echo e(url('profile.php')); ?>">Perfil</a>
                    <a href="<?php echo e(url('logout.php')); ?>">Sair</a>
                <?php else: ?>
                    <a href="<?php echo e(url('login.php')); ?>">Entrar</a>
                    <a href="<?php echo e(url('register.php')); ?>">Cadastrar</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="flash <?php echo e((string) $flash['type']); ?>">
                <?php echo e((string) $flash['message']); ?>
            </div>
        <?php endforeach; ?>
