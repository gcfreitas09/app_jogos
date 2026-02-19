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

$communityInvites = $inviteService->listInvites(
    (int) $currentUser['id'],
    null,
    'all',
    false,
    null,
    null,
    null
);
$communityUpcoming = array_values(array_filter(
    $communityInvites,
    static fn (array $invite): bool => !(bool) ($invite['is_past'] ?? false)
));

$latestCreated = $communityUpcoming;
usort(
    $latestCreated,
    static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''))
);
$latestCreated = array_slice($latestCreated, 0, 4);

$creatorStats = [];
foreach ($communityUpcoming as $invite) {
    $creatorName = trim((string) ($invite['creator_name'] ?? ''));
    if ($creatorName === '') {
        continue;
    }

    if (!isset($creatorStats[$creatorName])) {
        $creatorStats[$creatorName] = 0;
    }
    $creatorStats[$creatorName]++;
}
arsort($creatorStats);
$topCreators = array_slice($creatorStats, 0, 5, true);

$calendarTagByInviteId = [];
foreach ($joinedGames as $invite) {
    $calendarTagByInviteId[(int) $invite['id']] = 'Inscrito';
}
foreach ($createdGames as $invite) {
    $calendarTagByInviteId[(int) $invite['id']] = 'Criado por voce';
}
foreach ($waitlistGames as $invite) {
    $calendarTagByInviteId[(int) $invite['id']] = 'Fila de espera';
}
foreach ($historyGames as $invite) {
    $inviteId = (int) $invite['id'];
    if (!isset($calendarTagByInviteId[$inviteId])) {
        $calendarTagByInviteId[$inviteId] = 'Historico';
    }
}

$calendarGames = [];
$seenCalendarIds = [];
foreach (array_merge($joinedGames, $createdGames, $waitlistGames, $historyGames) as $invite) {
    $inviteId = (int) ($invite['id'] ?? 0);
    if ($inviteId <= 0 || isset($seenCalendarIds[$inviteId])) {
        continue;
    }

    $seenCalendarIds[$inviteId] = true;
    $calendarGames[] = $invite;
}

usort(
    $calendarGames,
    static fn (array $a, array $b): int => strcmp((string) ($a['starts_at'] ?? ''), (string) ($b['starts_at'] ?? ''))
);

$selectedMonth = trim((string) ($_GET['month'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = (new DateTimeImmutable('now'))->format('Y-m');
}

$calendarStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $selectedMonth . '-01 00:00:00');
if ($calendarStart === false) {
    $calendarStart = (new DateTimeImmutable('now'))->modify('first day of this month')->setTime(0, 0, 0);
}

$calendarMonth = $calendarStart->format('Y-m');
$prevMonth = $calendarStart->modify('-1 month')->format('Y-m');
$nextMonth = $calendarStart->modify('+1 month')->format('Y-m');
$daysInMonth = (int) $calendarStart->format('t');
$leadingDays = (int) $calendarStart->format('N') - 1;
$todayKey = (new DateTimeImmutable('now'))->format('Y-m-d');

$monthNames = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Marco',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro',
];
$calendarMonthLabel = ($monthNames[(int) $calendarStart->format('n')] ?? $calendarStart->format('m'))
    . ' '
    . $calendarStart->format('Y');

$selectedDay = trim((string) ($_GET['day'] ?? ''));
if ($selectedDay !== '') {
    $parsedDay = DateTimeImmutable::createFromFormat('Y-m-d', $selectedDay);
    if ($parsedDay === false || $parsedDay->format('Y-m-d') !== $selectedDay) {
        $selectedDay = '';
    }
}
if ($selectedDay !== '' && substr($selectedDay, 0, 7) !== $calendarMonth) {
    $selectedDay = '';
}

$calendarCounts = [];
$calendarGamesByDay = [];
foreach ($calendarGames as $invite) {
    $startsAtRaw = (string) ($invite['starts_at'] ?? '');
    if ($startsAtRaw === '') {
        continue;
    }

    $dayKey = substr($startsAtRaw, 0, 10);
    if (substr($dayKey, 0, 7) !== $calendarMonth) {
        continue;
    }

    if (!isset($calendarCounts[$dayKey])) {
        $calendarCounts[$dayKey] = 0;
    }
    $calendarCounts[$dayKey]++;

    if (!isset($calendarGamesByDay[$dayKey])) {
        $calendarGamesByDay[$dayKey] = [];
    }
    $calendarGamesByDay[$dayKey][] = $invite;
}

if ($selectedDay === '' && $calendarCounts !== []) {
    if (substr($todayKey, 0, 7) === $calendarMonth && isset($calendarCounts[$todayKey])) {
        $selectedDay = $todayKey;
    } else {
        $orderedDays = array_keys($calendarCounts);
        sort($orderedDays);
        $selectedDay = $orderedDays[0] ?? '';
    }
}

$selectedDayGames = $selectedDay !== '' ? ($calendarGamesByDay[$selectedDay] ?? []) : [];
$selectedDayLabel = '';
if ($selectedDay !== '') {
    $selectedDayDate = DateTimeImmutable::createFromFormat('Y-m-d', $selectedDay);
    if ($selectedDayDate !== false) {
        $selectedDayLabel = $selectedDayDate->format('d/m/Y');
    }
}

$buildCalendarUrl = static function (?string $month, ?string $day): string {
    $query = [];
    if ($month !== null && $month !== '') {
        $query['month'] = $month;
    }
    if ($day !== null && $day !== '') {
        $query['day'] = $day;
    }

    $queryString = http_build_query($query);
    return url('my_games.php' . ($queryString !== '' ? '?' . $queryString : ''));
};

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
<link rel="stylesheet" href="<?php echo e(url('assets/my_games.css')); ?>">

<section class="hero">
    <div class="hero-content">
        <h1>Meus Jogos</h1>
        <p>Acompanhe convites em que voc&ecirc; participa, cria e aguarda na fila de espera.</p>
    </div>
    <a class="btn btn-lg" href="<?php echo e(url('create_invite.php')); ?>">Criar Convite</a>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Radar da comunidade</h2>
        <span class="pill-count"><?php echo e((string) count($communityUpcoming)); ?> jogos ativos</span>
    </div>
    <div class="community-grid">
        <article class="card card-soft community-card">
            <div class="section-head community-head">
                <h3>Ultimos criados</h3>
                <span class="pill-count"><?php echo e((string) count($latestCreated)); ?></span>
            </div>
            <?php if ($latestCreated === []): ?>
                <p class="muted">Sem novidades no momento.</p>
            <?php else: ?>
                <div class="community-list">
                    <?php foreach ($latestCreated as $invite): ?>
                        <a class="community-item" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">
                            <strong><?php echo e((string) $invite['sport']); ?> - <?php echo e((string) $invite['location_name']); ?></strong>
                            <small>Inicio: <?php echo e((string) $invite['starts_at_label']); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="card card-soft community-card">
            <div class="section-head community-head">
                <h3>Top criadores</h3>
                <span class="pill-count"><?php echo e((string) count($topCreators)); ?></span>
            </div>
            <?php if ($topCreators === []): ?>
                <p class="muted">Sem ranking disponivel.</p>
            <?php else: ?>
                <div class="community-list">
                    <?php foreach ($topCreators as $creatorName => $createdCount): ?>
                        <div class="community-creator-row">
                            <strong><?php echo e((string) $creatorName); ?></strong>
                            <small><?php echo e((string) $createdCount); ?> jogo(s) criado(s)</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </div>
</section>

<section class="section-block">
    <article class="card card-soft my-calendar-card">
        <div class="section-head">
            <h2>Calendario dos meus jogos</h2>
            <span class="pill-count"><?php echo e((string) count($calendarGames)); ?> no total</span>
        </div>

        <div class="my-calendar-layout">
            <div class="my-calendar-panel">
                <div class="my-calendar-head">
                    <a href="<?php echo e($buildCalendarUrl($prevMonth, null)); ?>" class="btn btn-outline my-calendar-nav" aria-label="Mes anterior">&#8249;</a>
                    <strong><?php echo e($calendarMonthLabel); ?></strong>
                    <a href="<?php echo e($buildCalendarUrl($nextMonth, null)); ?>" class="btn btn-outline my-calendar-nav" aria-label="Proximo mes">&#8250;</a>
                </div>

                <div class="my-calendar-grid">
                    <?php foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'] as $weekday): ?>
                        <span class="my-calendar-weekday"><?php echo e($weekday); ?></span>
                    <?php endforeach; ?>

                    <?php for ($blank = 0; $blank < $leadingDays; $blank++): ?>
                        <span class="my-calendar-day my-calendar-day-empty"></span>
                    <?php endfor; ?>

                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                        <?php
                        $dayKey = $calendarMonth . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                        $dayCount = (int) ($calendarCounts[$dayKey] ?? 0);
                        $dayClasses = ['my-calendar-day'];
                        if ($dayCount > 0) {
                            $dayClasses[] = 'has-games';
                        }
                        if ($selectedDay === $dayKey) {
                            $dayClasses[] = 'is-selected';
                        }
                        if ($todayKey === $dayKey) {
                            $dayClasses[] = 'is-today';
                        }
                        $dayTitle = $dayCount > 0 ? $dayCount . ' jogo(s)' : 'Sem jogos';
                        ?>
                        <a href="<?php echo e($buildCalendarUrl($calendarMonth, $dayKey)); ?>" class="<?php echo e(implode(' ', $dayClasses)); ?>" title="<?php echo e($dayTitle); ?>">
                            <span><?php echo e((string) $day); ?></span>
                            <?php if ($dayCount > 0): ?>
                                <small><?php echo e((string) $dayCount); ?></small>
                            <?php endif; ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <div class="my-calendar-actions">
                    <?php if ($selectedDay !== ''): ?>
                        <a class="btn btn-outline my-calendar-clear" href="<?php echo e($buildCalendarUrl($calendarMonth, null)); ?>">Limpar dia</a>
                    <?php else: ?>
                        <span class="muted">Clique em um dia para ver detalhes.</span>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="my-calendar-info">
                <h3><?php echo $selectedDayLabel !== '' ? 'Jogos em ' . e($selectedDayLabel) : 'Jogos do mes'; ?></h3>
                <?php if ($selectedDayGames === []): ?>
                    <p class="muted">Nenhum jogo encontrado para este dia.</p>
                <?php else: ?>
                    <div class="my-calendar-list">
                        <?php foreach ($selectedDayGames as $invite): ?>
                            <?php $inviteId = (int) ($invite['id'] ?? 0); ?>
                            <a class="my-calendar-item" href="<?php echo e(url('invite.php?id=' . (string) $inviteId)); ?>">
                                <div class="my-calendar-item-top">
                                    <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                                    <span class="my-calendar-tag"><?php echo e((string) ($calendarTagByInviteId[$inviteId] ?? 'Jogo')); ?></span>
                                </div>
                                <strong><?php echo e((string) $invite['location_name']); ?></strong>
                                <small>Inicio: <?php echo e((string) $invite['starts_at_label']); ?></small>
                                <small>Jogadores: <?php echo e((string) $invite['players_count']); ?> / <?php echo e((string) $invite['max_players']); ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </article>
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
<?php
require __DIR__ . '/templates/footer.php';
