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
$returnPathQuery = http_build_query(array_filter([
    'month' => $calendarMonth,
    'day' => $selectedDay,
], static fn (string $value): bool => $value !== ''));
if ($returnPathQuery !== '') {
    $returnPath .= '?' . $returnPathQuery;
}

$title = 'Meus Jogos';
require __DIR__ . '/templates/header.php';
?>
<section class="hero">
    <div class="hero-content">
        <h1>Meus Jogos</h1>
        <p>Acompanhe convites em que voc&ecirc; participa, cria e aguarda na fila de espera.</p>
    </div>
    <a class="btn btn-lg" href="<?php echo e(url('create_invite.php')); ?>">Criar Convite</a>
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
