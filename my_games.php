<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_auth();

$currentUser = $authService->currentUser();
if ($currentUser === null) {
    $authService->logout();
    set_flash('error', 'Sua sessão expirou. Faça login novamente.');
    redirect('login.php');
}

$games = $inviteService->getMyGames((int) $currentUser['id']);
$joinedGames = $games['joined'];
$createdGames = $games['created'];
$waitlistGames = $games['waitlist'];
$historyGames = $games['history'];

$calendarGames = [];
$calendarSeenIds = [];
$calendarSources = [
    ['items' => $joinedGames, 'role' => 'Inscrito'],
    ['items' => $createdGames, 'role' => 'Criado por voce'],
    ['items' => $waitlistGames, 'role' => 'Fila de espera'],
];

foreach ($calendarSources as $source) {
    foreach ($source['items'] as $invite) {
        $inviteId = (int) ($invite['id'] ?? 0);
        if ($inviteId <= 0 || isset($calendarSeenIds[$inviteId])) {
            continue;
        }
        if (!empty($invite['is_past'])) {
            continue;
        }

        $startsAtRaw = (string) ($invite['starts_at'] ?? '');
        $dateKey = strlen($startsAtRaw) >= 10 ? substr($startsAtRaw, 0, 10) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
            continue;
        }

        $calendarSeenIds[$inviteId] = true;
        $calendarGames[] = [
            'id' => $inviteId,
            'date' => $dateKey,
            'sport' => (string) ($invite['sport'] ?? ''),
            'location_name' => (string) ($invite['location_name'] ?? ''),
            'starts_at_label' => (string) ($invite['starts_at_label'] ?? ''),
            'players_count' => (int) ($invite['players_count'] ?? 0),
            'max_players' => (int) ($invite['max_players'] ?? 0),
            'role' => (string) $source['role'],
            'url' => url('invite.php?id=' . (string) $inviteId),
        ];
    }
}

$calendarGamesJson = json_encode(
    $calendarGames,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
if (!is_string($calendarGamesJson)) {
    $calendarGamesJson = '[]';
}

$returnPath = 'my_games.php';

$title = 'Meus Jogos';
require __DIR__ . '/templates/header.php';
?>
<style>
    .my-calendar-panel {
        margin-bottom: 14px;
    }
    .my-calendar-layout {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: 14px;
        align-items: start;
    }
    .my-calendar-grid-wrap {
        border: 1px solid #d6e4eb;
        border-radius: 14px;
        padding: 12px;
        background: #ffffff;
    }
    .my-calendar-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 12px;
    }
    .my-calendar-month {
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
    }
    .my-calendar-nav {
        width: auto;
        min-width: 50px;
        padding: 8px 10px;
    }
    .my-calendar-weekdays {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 8px;
    }
    .my-calendar-weekdays span {
        text-align: center;
        color: #5c6f7b;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .my-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 8px;
    }
    .my-calendar-day {
        border: 1px solid #d3e1e8;
        border-radius: 12px;
        min-height: 56px;
        background: #fff;
        color: #173248;
        font-weight: 700;
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .my-calendar-day.is-empty {
        cursor: default;
        border-style: dashed;
        opacity: 0.45;
    }
    .my-calendar-day.has-games::after {
        content: '';
        position: absolute;
        bottom: 8px;
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #0b7f6f;
    }
    .my-calendar-day.is-today {
        border-color: #f59e0b;
        background: #fff7e8;
        color: #7a3c00;
    }
    .my-calendar-day.is-today:not(.is-selected)::before {
        content: 'Hoje';
        position: absolute;
        top: 5px;
        right: 6px;
        font-size: 10px;
        font-weight: 700;
        color: #b45309;
    }
    .my-calendar-day.is-selected {
        border-color: #0b7f6f;
        box-shadow: 0 0 0 2px rgba(11, 127, 111, 0.25);
    }
    .my-calendar-info {
        border: 1px solid #d6e4eb;
        border-radius: 14px;
        padding: 12px;
        background: #fff;
        min-height: 372px;
    }
    .my-calendar-info h3 {
        margin-bottom: 10px;
    }
    .my-calendar-list {
        display: grid;
        gap: 10px;
    }
    .my-calendar-item {
        border: 1px solid #d7e6ed;
        border-radius: 12px;
        padding: 10px;
        background: #fcfeff;
    }
    .my-calendar-item-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }
    .my-calendar-tag {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 4px 8px;
        font-size: 12px;
        border: 1px solid #b6ceda;
        background: #eaf5fa;
        color: #2a5874;
        font-weight: 700;
    }
    .my-calendar-item p {
        margin: 0;
    }
    html[data-theme='dark'] .my-calendar-grid-wrap,
    html[data-theme='dark'] .my-calendar-info,
    html[data-theme='dark'] .my-calendar-item {
        background: #172433;
        border-color: #30495d;
    }
    html[data-theme='dark'] .my-calendar-day {
        background: #1a2b3a;
        border-color: #36516a;
        color: #dbe8f2;
    }
    html[data-theme='dark'] .my-calendar-day.has-games::after {
        background: #2cd6be;
    }
    html[data-theme='dark'] .my-calendar-day.is-today {
        border-color: #f7b955;
        background: #3a2a12;
        color: #ffd79e;
    }
    html[data-theme='dark'] .my-calendar-day.is-today:not(.is-selected)::before {
        color: #ffbf66;
    }
    html[data-theme='dark'] .my-calendar-day.is-selected {
        border-color: #2cd6be;
        box-shadow: 0 0 0 2px rgba(44, 214, 190, 0.28);
    }
    html[data-theme='dark'] .my-calendar-weekdays span {
        color: #9ab0c1;
    }
    html[data-theme='dark'] .my-calendar-tag {
        border-color: #40657d;
        background: #1f3445;
        color: #b7d7ea;
    }
    @media (max-width: 980px) {
        .my-calendar-layout {
            grid-template-columns: 1fr;
        }
        .my-calendar-info {
            min-height: auto;
        }
    }
</style>
<section class="hero">
    <div class="hero-content">
        <h1>Meus Jogos</h1>
        <p>Acompanhe convites que você participa, cria e aguarda na fila de espera.</p>
    </div>
    <a class="btn btn-lg" href="<?php echo e(url('create_invite.php')); ?>">Criar Convite</a>
</section>

<section class="card card-soft my-calendar-panel">
    <div class="section-head">
        <h2>Calendário dos meus jogos</h2>
        <span class="pill-count"><?php echo e((string) count($calendarGames)); ?> no total</span>
    </div>
    <div class="my-calendar-layout">
        <div class="my-calendar-grid-wrap">
            <div class="my-calendar-toolbar">
                <button type="button" class="btn btn-outline my-calendar-nav" id="my-calendar-prev">&lt;</button>
                <div class="my-calendar-month" id="my-calendar-month-label">-</div>
                <button type="button" class="btn btn-outline my-calendar-nav" id="my-calendar-next">&gt;</button>
            </div>
            <div class="my-calendar-weekdays">
                <span>Seg</span>
                <span>Ter</span>
                <span>Qua</span>
                <span>Qui</span>
                <span>Sex</span>
                <span>Sáb</span>
                <span>Dom</span>
            </div>
            <div class="my-calendar-grid" id="my-calendar-grid"></div>
        </div>
        <div class="my-calendar-info">
            <h3 id="my-calendar-selected-title">Jogos do dia</h3>
            <div class="my-calendar-list" id="my-calendar-list"></div>
        </div>
    </div>
    <script type="application/json" id="my-games-calendar-data"><?php echo $calendarGamesJson; ?></script>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Inscritos / Aceitos</h2>
        <span class="pill-count"><?php echo e((string) count($joinedGames)); ?></span>
    </div>
    <?php if ($joinedGames === []): ?>
        <div class="empty-state card-soft">Você não está inscrito em jogos futuros.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($joinedGames as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="badge status <?php echo e((string) $invite['status_derived']); ?>"><?php echo e((string) $invite['status_label']); ?></span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted">Início: <?php echo e((string) $invite['starts_at_label']); ?></p>
                    <p class="muted">Vagas: <?php echo e((string) $invite['players_count']); ?> / <?php echo e((string) $invite['max_players']); ?></p>
                    <div class="actions-row">
                        <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Detalhes</a>
                        <?php if ($invite['can_leave']): ?>
                            <form method="post" action="<?php echo e(url('leave_invite.php')); ?>" class="inline-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo e($returnPath); ?>">
                                <button type="submit" class="btn-danger">Sair</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Criados por mim</h2>
        <span class="pill-count"><?php echo e((string) count($createdGames)); ?></span>
    </div>
    <?php if ($createdGames === []): ?>
        <div class="empty-state card-soft">Você ainda não criou convites futuros.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($createdGames as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="badge status <?php echo e((string) $invite['status_derived']); ?>"><?php echo e((string) $invite['status_label']); ?></span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted">Início: <?php echo e((string) $invite['starts_at_label']); ?></p>
                    <p class="muted">Jogadores: <?php echo e((string) $invite['players_count']); ?> / <?php echo e((string) $invite['max_players']); ?></p>
                    <p class="muted">Fila: <?php echo e((string) $invite['waitlist_count']); ?></p>
                    <div class="actions-row">
                        <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Abrir convite</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Fila de espera</h2>
        <span class="pill-count"><?php echo e((string) count($waitlistGames)); ?></span>
    </div>
    <?php if ($waitlistGames === []): ?>
        <div class="empty-state card-soft">Você não está em nenhuma fila de espera.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($waitlistGames as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="pill-count">Fila</span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted">Início: <?php echo e((string) $invite['starts_at_label']); ?></p>
                    <p class="muted">Fila atual: <?php echo e((string) $invite['waitlist_count']); ?> pessoa(s)</p>
                    <div class="actions-row">
                        <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Detalhes</a>
                        <?php if ($invite['can_leave']): ?>
                            <form method="post" action="<?php echo e(url('leave_invite.php')); ?>" class="inline-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo e($returnPath); ?>">
                                <button type="submit" class="btn-danger">Sair da fila</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Histórico</h2>
        <span class="pill-count"><?php echo e((string) count($historyGames)); ?></span>
    </div>
    <?php if ($historyGames === []): ?>
        <div class="empty-state card-soft">Sem histórico de jogos no momento.</div>
    <?php else: ?>
        <div class="invite-grid past-grid">
            <?php foreach ($historyGames as $invite): ?>
                <article class="invite-card invite-card-past">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="badge status ended">Encerrado</span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted"><?php echo e((string) $invite['starts_at_label']); ?></p>
                    <div class="actions-row">
                        <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Ver detalhes</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var calendarDataNode = document.getElementById('my-games-calendar-data');
    var gridNode = document.getElementById('my-calendar-grid');
    var monthLabelNode = document.getElementById('my-calendar-month-label');
    var listNode = document.getElementById('my-calendar-list');
    var titleNode = document.getElementById('my-calendar-selected-title');
    var prevButton = document.getElementById('my-calendar-prev');
    var nextButton = document.getElementById('my-calendar-next');

    if (!calendarDataNode || !gridNode || !monthLabelNode || !listNode || !titleNode || !prevButton || !nextButton) {
        return;
    }

    var games = [];
    try {
        games = JSON.parse(calendarDataNode.textContent || '[]');
    } catch (error) {
        games = [];
    }
    if (!Array.isArray(games)) {
        games = [];
    }

    var gamesByDate = {};
    games.forEach(function (game) {
        if (!game || typeof game.date !== 'string') {
            return;
        }
        if (!gamesByDate[game.date]) {
            gamesByDate[game.date] = [];
        }
        gamesByDate[game.date].push(game);
    });

    var monthNames = [
        'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];

    var today = new Date();
    var cursorYear = today.getFullYear();
    var cursorMonth = today.getMonth();
    var selectedDate = '';
    var todayKey = '';

    function toDateKey(year, month, day) {
        var mm = String(month + 1).padStart(2, '0');
        var dd = String(day).padStart(2, '0');
        return String(year) + '-' + mm + '-' + dd;
    }

    todayKey = toDateKey(today.getFullYear(), today.getMonth(), today.getDate());

    function formatDatePt(dateKey) {
        var parts = dateKey.split('-');
        if (parts.length !== 3) {
            return dateKey;
        }
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderSelectedList(dateKey) {
        var dayGames = gamesByDate[dateKey] || [];
        titleNode.textContent = 'Jogos em ' + formatDatePt(dateKey);
        listNode.innerHTML = '';

        if (dayGames.length === 0) {
            listNode.innerHTML = '<div class="empty-state card-soft">Nenhum jogo neste dia.</div>';
            return;
        }

        dayGames.forEach(function (game) {
            var item = document.createElement('article');
            item.className = 'my-calendar-item';
            item.innerHTML = ''
                + '<div class="my-calendar-item-top">'
                + '<span class="my-calendar-tag">' + escapeHtml(game.role) + '</span>'
                + '<span class="badge sport">' + escapeHtml(game.sport) + '</span>'
                + '</div>'
                + '<p><strong>' + escapeHtml(game.location_name) + '</strong></p>'
                + '<p class="muted">Inicio: ' + escapeHtml(game.starts_at_label) + '</p>'
                + '<p class="muted">Jogadores: ' + escapeHtml(game.players_count) + ' / ' + escapeHtml(game.max_players) + '</p>'
                + '<div class="actions-row" style="margin-top:8px;">'
                + '<a class="btn btn-outline" href="' + escapeHtml(game.url) + '">Abrir convite</a>'
                + '</div>';
            listNode.appendChild(item);
        });
    }

    function renderCalendar() {
        monthLabelNode.textContent = monthNames[cursorMonth] + ' ' + String(cursorYear);
        gridNode.innerHTML = '';

        var firstDay = new Date(cursorYear, cursorMonth, 1);
        var firstWeekday = (firstDay.getDay() + 6) % 7;
        var daysInMonth = new Date(cursorYear, cursorMonth + 1, 0).getDate();

        for (var i = 0; i < firstWeekday; i += 1) {
            var emptyCell = document.createElement('div');
            emptyCell.className = 'my-calendar-day is-empty';
            gridNode.appendChild(emptyCell);
        }

        for (var day = 1; day <= daysInMonth; day += 1) {
            var dateKey = toDateKey(cursorYear, cursorMonth, day);
            var hasGames = Array.isArray(gamesByDate[dateKey]) && gamesByDate[dateKey].length > 0;
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'my-calendar-day';
            if (hasGames) {
                button.classList.add('has-games');
            }
            if (dateKey === todayKey) {
                button.classList.add('is-today');
            }
            if (selectedDate === dateKey) {
                button.classList.add('is-selected');
            }
            button.dataset.date = dateKey;
            button.textContent = String(day);
            button.addEventListener('click', function () {
                selectedDate = this.dataset.date || '';
                renderCalendar();
                renderSelectedList(selectedDate);
            });
            gridNode.appendChild(button);
        }
    }

    var defaultDate = toDateKey(today.getFullYear(), today.getMonth(), today.getDate());
    if (gamesByDate[defaultDate]) {
        selectedDate = defaultDate;
    } else {
        var sortedKeys = Object.keys(gamesByDate).sort();
        selectedDate = sortedKeys.length > 0 ? sortedKeys[0] : defaultDate;
    }

    if (selectedDate) {
        var parts = selectedDate.split('-');
        if (parts.length === 3) {
            cursorYear = Number(parts[0]);
            cursorMonth = Number(parts[1]) - 1;
        }
    }

    prevButton.addEventListener('click', function () {
        cursorMonth -= 1;
        if (cursorMonth < 0) {
            cursorMonth = 11;
            cursorYear -= 1;
        }
        renderCalendar();
    });

    nextButton.addEventListener('click', function () {
        cursorMonth += 1;
        if (cursorMonth > 11) {
            cursorMonth = 0;
            cursorYear += 1;
        }
        renderCalendar();
    });

    renderCalendar();
    renderSelectedList(selectedDate);
});
</script>
<?php
require __DIR__ . '/templates/footer.php';
