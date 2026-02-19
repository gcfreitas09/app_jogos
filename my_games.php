<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_auth();

$currentUser = $authService->currentUser();
if ($currentUser === null) {
    $authService->logout();
    set_flash('error', 'Sua sessao expirou. Faca login novamente.');
    redirect('login.php');
}

$games = $inviteService->getMyGames((int) $currentUser['id']);
$joinedGames = $games['joined'];
$createdGames = $games['created'];
$waitlistGames = $games['waitlist'];
$historyGames = $games['history'];

$returnPath = 'my_games.php';

$calendarGames = [];
$calendarSeen = [];
$appendCalendarGames = static function (array $invites, string $roleLabel) use (&$calendarGames, &$calendarSeen): void {
    foreach ($invites as $invite) {
        if ((bool) ($invite['is_past'] ?? false)) {
            continue;
        }

        $startsAtRaw = trim((string) ($invite['starts_at'] ?? ''));
        if ($startsAtRaw === '' && isset($invite['starts_at_label'])) {
            $parsed = DateTimeImmutable::createFromFormat('d/m/Y H:i', (string) $invite['starts_at_label']);
            if ($parsed !== false) {
                $startsAtRaw = $parsed->format('Y-m-d H:i:s');
            }
        }
        if ($startsAtRaw === '' || strlen($startsAtRaw) < 10) {
            continue;
        }

        $dateKey = substr($startsAtRaw, 0, 10);
        $id = (int) ($invite['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $eventKey = $dateKey . '#' . (string) $id . '#' . $roleLabel;
        if (isset($calendarSeen[$eventKey])) {
            continue;
        }
        $calendarSeen[$eventKey] = true;

        $calendarGames[] = [
            'id' => $id,
            'date' => $dateKey,
            'role' => $roleLabel,
            'sport' => (string) ($invite['sport'] ?? ''),
            'location_name' => (string) ($invite['location_name'] ?? ''),
            'starts_at_label' => (string) ($invite['starts_at_label'] ?? ''),
            'players_count' => (string) ($invite['players_count'] ?? '0'),
            'max_players' => (string) ($invite['max_players'] ?? '0'),
            'url' => url('invite.php?id=' . (string) $id),
        ];
    }
};

$appendCalendarGames($joinedGames, 'Inscrito');
$appendCalendarGames($createdGames, 'Criado por voce');
$appendCalendarGames($waitlistGames, 'Fila de espera');

usort(
    $calendarGames,
    static fn (array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date'])
);

$calendarGamesJson = json_encode(
    $calendarGames,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if (!is_string($calendarGamesJson)) {
    $calendarGamesJson = '[]';
}

$title = 'Meus Jogos';
require __DIR__ . '/templates/header.php';
?>
<style>
    .my-calendar-card {
        margin-top: 12px;
    }
    .my-calendar-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(260px, 1fr);
        gap: 14px;
    }
    .my-calendar-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 12px;
    }
    .my-calendar-nav-btn {
        width: 56px;
        min-width: 56px;
        padding: 8px;
        border-radius: 12px;
        border: 1px solid #c8d7e1;
        background: #fff;
        color: #1d4b68;
        font-weight: 700;
    }
    .my-calendar-weekdays,
    .my-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 8px;
    }
    .my-calendar-weekdays {
        margin-bottom: 8px;
    }
    .my-calendar-weekday {
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        color: #557088;
        text-transform: uppercase;
    }
    .my-calendar-day {
        min-height: 58px;
        border-radius: 12px;
        border: 1px solid #ccdae4;
        background: #ffffff;
        color: #18334a;
        font-weight: 700;
        position: relative;
        padding: 0;
    }
    .my-calendar-day.is-empty {
        visibility: hidden;
    }
    .my-calendar-day.has-games::after {
        content: '';
        position: absolute;
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #14b8a6;
        bottom: 7px;
        left: 50%;
        transform: translateX(-50%);
    }
    .my-calendar-day.is-today {
        background: #fff3db;
        border-color: #f59e0b;
        color: #8a4b00;
        box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.22);
    }
    .my-calendar-day.is-today.has-games::after {
        background: #f59e0b;
    }
    .my-calendar-day.is-selected {
        background: #e8f7f2;
        border-color: #0b7f6f;
        color: #0a5f53;
    }
    .my-calendar-day.is-today.is-selected {
        background: #ffe6bf;
        border-color: #f59e0b;
        color: #7a4000;
    }
    .my-calendar-list-wrap {
        border: 1px solid #d1dfe8;
        border-radius: 14px;
        padding: 12px;
        background: #f9fcff;
        min-height: 360px;
    }
    .my-calendar-list {
        display: grid;
        gap: 10px;
        margin-top: 10px;
    }
    .my-calendar-item {
        border: 1px solid #d7e3eb;
        border-radius: 12px;
        padding: 10px;
        background: #ffffff;
    }
    .my-calendar-item-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }
    .my-calendar-tag {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 999px;
        background: #edf5ff;
        color: #25557b;
        font-size: 12px;
        font-weight: 700;
    }
    html[data-theme='dark'] .my-calendar-nav-btn,
    html[data-theme='dark'] .my-calendar-day,
    html[data-theme='dark'] .my-calendar-item,
    html[data-theme='dark'] .my-calendar-list-wrap {
        background: #13273a;
        border-color: #294860;
        color: #e7f2fb;
    }
    html[data-theme='dark'] .my-calendar-weekday {
        color: #95b4ca;
    }
    html[data-theme='dark'] .my-calendar-day.is-selected {
        background: #1a3f5a;
        border-color: #3f7398;
        color: #eaf6ff;
    }
    html[data-theme='dark'] .my-calendar-day.is-today {
        background: #3f2f16;
        border-color: #f2b650;
        color: #ffe8bf;
        box-shadow: 0 0 0 2px rgba(242, 182, 80, 0.26);
    }
    html[data-theme='dark'] .my-calendar-day.is-today.has-games::after {
        background: #ffd38a;
    }
    html[data-theme='dark'] .my-calendar-day.is-today.is-selected {
        background: #5a421d;
        border-color: #ffc96e;
        color: #ffefcc;
    }
    html[data-theme='dark'] .my-calendar-tag {
        background: #1a3f5a;
        color: #cde6fb;
    }
    @media (max-width: 980px) {
        .my-calendar-layout {
            grid-template-columns: 1fr;
        }
        .my-calendar-list-wrap {
            min-height: 0;
        }
    }
</style>

<section class="hero">
    <div class="hero-content">
        <h1>Meus Jogos</h1>
        <p>Acompanhe convites em que voce participa, cria e aguarda na fila de espera.</p>
    </div>
    <a class="btn btn-lg" href="<?php echo e(url('create_invite.php')); ?>">Criar Convite</a>
</section>

<section class="section-block my-calendar-card">
    <div class="section-head">
        <h2>Calendario dos meus jogos</h2>
        <span class="pill-count"><?php echo e((string) count($calendarGames)); ?> no total</span>
    </div>
    <section class="card card-soft">
        <div class="my-calendar-layout">
            <div>
                <div class="my-calendar-head">
                    <button type="button" class="my-calendar-nav-btn" id="my-calendar-prev">&lt;</button>
                    <h3 id="my-calendar-month-label">Mes</h3>
                    <button type="button" class="my-calendar-nav-btn" id="my-calendar-next">&gt;</button>
                </div>
                <div class="my-calendar-weekdays">
                    <div class="my-calendar-weekday">Seg</div>
                    <div class="my-calendar-weekday">Ter</div>
                    <div class="my-calendar-weekday">Qua</div>
                    <div class="my-calendar-weekday">Qui</div>
                    <div class="my-calendar-weekday">Sex</div>
                    <div class="my-calendar-weekday">Sab</div>
                    <div class="my-calendar-weekday">Dom</div>
                </div>
                <div id="my-calendar-grid" class="my-calendar-grid"></div>
            </div>
            <div class="my-calendar-list-wrap">
                <h3 id="my-calendar-selected-title">Jogos</h3>
                <div id="my-calendar-list" class="my-calendar-list"></div>
            </div>
        </div>
        <script type="application/json" id="my-games-calendar-data"><?php echo $calendarGamesJson; ?></script>
    </section>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Inscritos / Aceitos</h2>
        <span class="pill-count"><?php echo e((string) count($joinedGames)); ?></span>
    </div>
    <?php if ($joinedGames === []): ?>
        <div class="empty-state card-soft">Voce nao esta inscrito em jogos futuros.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($joinedGames as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="badge status <?php echo e((string) $invite['status_derived']); ?>"><?php echo e((string) $invite['status_label']); ?></span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted">Inicio: <?php echo e((string) $invite['starts_at_label']); ?></p>
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
        <div class="empty-state card-soft">Voce ainda nao criou convites futuros.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($createdGames as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="badge status <?php echo e((string) $invite['status_derived']); ?>"><?php echo e((string) $invite['status_label']); ?></span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted">Inicio: <?php echo e((string) $invite['starts_at_label']); ?></p>
                    <p class="muted">Jogadores: <?php echo e((string) $invite['players_count']); ?> / <?php echo e((string) $invite['max_players']); ?></p>
                    <p class="muted">Fila: <?php echo e((string) $invite['waitlist_count']); ?></p>
                    <div class="actions-row">
                        <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Abrir convite</a>
                        <form method="post" action="<?php echo e(url('delete_invite.php')); ?>" class="inline-form" onsubmit="return confirm('Tem certeza que deseja excluir este convite?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo e($returnPath); ?>">
                            <button type="submit" class="btn-danger">Excluir convite</button>
                        </form>
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
        <div class="empty-state card-soft">Voce nao esta em nenhuma fila de espera.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($waitlistGames as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="pill-count">Fila</span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted">Inicio: <?php echo e((string) $invite['starts_at_label']); ?></p>
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
        <h2>Historico</h2>
        <span class="pill-count"><?php echo e((string) count($historyGames)); ?></span>
    </div>
    <?php if ($historyGames === []): ?>
        <div class="empty-state card-soft">Sem historico de jogos no momento.</div>
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
