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

$profile = $profileService->getProfile((int) $currentUser['id']);
$defaultRadius = $profile !== null ? (int) $profile['default_radius_km'] : 5;
if (!in_array($defaultRadius, App\Services\InviteService::allowedRadii(), true)) {
    $defaultRadius = 5;
}

$searchTerm = trim((string) ($_GET['search'] ?? ''));
if (function_exists('mb_substr')) {
    $searchTerm = mb_substr($searchTerm, 0, 120);
} else {
    $searchTerm = substr($searchTerm, 0, 120);
}

$selectedSport = trim((string) ($_GET['sport'] ?? ''));
if ($selectedSport !== '' && !in_array($selectedSport, App\Services\InviteService::allowedSports(), true)) {
    $selectedSport = '';
}

$selectedPeriod = trim((string) ($_GET['period'] ?? 'all'));
if (!in_array($selectedPeriod, App\Services\InviteService::allowedPeriods(), true)) {
    $selectedPeriod = 'all';
}

$onlyWithSlots = ((string) ($_GET['only_with_slots'] ?? '0')) === '1';
$showMap = ((string) ($_GET['show_map'] ?? '1')) === '1';

$selectedRadius = (int) ($_GET['radius_km'] ?? $defaultRadius);
if (!in_array($selectedRadius, App\Services\InviteService::allowedRadii(), true)) {
    $selectedRadius = $defaultRadius;
}

$rawLat = trim((string) ($_GET['lat'] ?? ''));
$rawLng = trim((string) ($_GET['lng'] ?? ''));
$locationLabel = trim((string) ($_GET['location_label'] ?? ''));
if (function_exists('mb_substr')) {
    $locationLabel = mb_substr($locationLabel, 0, 190);
} else {
    $locationLabel = substr($locationLabel, 0, 190);
}

$userLat = null;
$userLng = null;
if ($rawLat !== '' && $rawLng !== '') {
    $parsedLat = str_replace(',', '.', $rawLat);
    $parsedLng = str_replace(',', '.', $rawLng);
    if (is_numeric($parsedLat) && is_numeric($parsedLng)) {
        $latValue = (float) $parsedLat;
        $lngValue = (float) $parsedLng;
        if ($latValue >= -90 && $latValue <= 90 && $lngValue >= -180 && $lngValue <= 180) {
            $userLat = $latValue;
            $userLng = $lngValue;
        }
    }
}

$radiusFilter = ($userLat !== null && $userLng !== null) ? $selectedRadius : null;

$invites = $inviteService->listInvites(
    (int) $currentUser['id'],
    $selectedSport !== '' ? $selectedSport : null,
    $selectedPeriod,
    $onlyWithSlots,
    $userLat,
    $userLng,
    $radiusFilter
);

$invites = array_values(array_filter(
    $invites,
    static function (array $invite) use ($searchTerm): bool {
        if ($searchTerm === '') {
            return true;
        }

        $needle = function_exists('mb_strtolower')
            ? mb_strtolower($searchTerm)
            : strtolower($searchTerm);

        $haystack = implode(' ', [
            (string) ($invite['location_name'] ?? ''),
            (string) ($invite['address'] ?? ''),
            (string) ($invite['sport'] ?? ''),
            (string) ($invite['description'] ?? ''),
        ]);

        $haystack = function_exists('mb_strtolower')
            ? mb_strtolower($haystack)
            : strtolower($haystack);

        return strpos($haystack, $needle) !== false;
    }
));

$upcomingInvites = array_values(array_filter(
    $invites,
    static fn (array $invite): bool => !$invite['is_past']
));
$pastInvitesCount = max(0, count($invites) - count($upcomingInvites));
$nearByCount = 0;
foreach ($upcomingInvites as $invite) {
    $distance = $invite['distance_km'] ?? null;
    if ((is_float($distance) || is_int($distance)) && (float) $distance <= 2.0) {
        $nearByCount++;
    }
}

$myGames = $inviteService->getMyGames((int) $currentUser['id']);
$myUpcoming = [];
$seenMyGameIds = [];
foreach (array_merge($myGames['joined'], $myGames['created']) as $myGame) {
    if ((bool) $myGame['is_past']) {
        continue;
    }
    $gameId = (int) $myGame['id'];
    if (isset($seenMyGameIds[$gameId])) {
        continue;
    }
    $seenMyGameIds[$gameId] = true;
    $myUpcoming[] = $myGame;
    if (count($myUpcoming) >= 4) {
        break;
    }
}

$nearFullInvites = array_values(array_filter(
    $upcomingInvites,
    static fn (array $invite): bool => !(bool) $invite['is_full'] && (int) $invite['slots_left'] <= 2
));
usort($nearFullInvites, static fn (array $a, array $b): int => ((int) $a['slots_left'] <=> (int) $b['slots_left']) ?: strcmp((string) $a['starts_at'], (string) $b['starts_at']));
$nearFullInvites = array_slice($nearFullInvites, 0, 4);

$queryState = [
    'search' => $searchTerm,
    'sport' => $selectedSport,
    'period' => $selectedPeriod !== 'all' ? $selectedPeriod : '',
    'only_with_slots' => $onlyWithSlots ? '1' : '',
    'show_map' => $showMap ? '1' : '',
    'radius_km' => (string) $selectedRadius,
    'lat' => $userLat !== null ? (string) $userLat : '',
    'lng' => $userLng !== null ? (string) $userLng : '',
    'location_label' => $locationLabel,
];
$buildQuery = static function (array $overrides = [], array $remove = []) use ($queryState): string {
    $query = $queryState;
    foreach ($remove as $key) {
        unset($query[$key]);
    }
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = (string) $value;
        }
    }
    return http_build_query(array_filter($query, static fn (string $value): bool => $value !== ''));
};
$buildUrl = static fn (array $overrides = [], array $remove = []): string => url('explore.php' . (($query = $buildQuery($overrides, $remove)) !== '' ? '?' . $query : ''));
$clearFiltersUrl = $buildUrl(['search' => '', 'sport' => '', 'period' => '', 'only_with_slots' => '']);

$returnPath = 'explore.php';
$currentQuery = $buildQuery();
if ($currentQuery !== '') {
    $returnPath .= '?' . $currentQuery;
}

$mapMarkers = [];
foreach ($upcomingInvites as $invite) {
    if ($invite['lat'] === null || $invite['lng'] === null) {
        continue;
    }
    $mapMarkers[] = [
        'id' => (int) $invite['id'],
        'lat' => (float) $invite['lat'],
        'lng' => (float) $invite['lng'],
        'sport' => (string) $invite['sport'],
        'location' => (string) $invite['location_name'],
        'starts_at' => (string) $invite['starts_at_label'],
        'players' => (int) $invite['players_count'],
        'max_players' => (int) $invite['max_players'],
    ];
}
$mapMarkersJson = json_encode($mapMarkers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($mapMarkersJson === false) {
    $mapMarkersJson = '[]';
}
$userLocationJson = json_encode($userLat !== null && $userLng !== null ? ['lat' => $userLat, 'lng' => $userLng] : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($userLocationJson === false) {
    $userLocationJson = 'null';
}

$title = 'Explorar';
$pageClass = 'page-explore';
require __DIR__ . '/templates/header.php';
?>
<link rel="stylesheet" href="<?php echo e(url('assets/explore.css')); ?>">
<?php if ($showMap): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<?php endif; ?>

<section class="explore-shell">
    <div class="mobile-hero card">
        <p class="mobile-overline">Descoberta local de partidas</p>
        <h1>Explore jogos perto de voce</h1>
        <p>Mapa fixo na lateral, cards no centro e painel inteligente na direita.</p>
    </div>

    <div class="explore-layout <?php echo $showMap ? 'layout-with-map' : 'layout-no-map'; ?>">
        <?php if ($showMap): ?>
            <aside class="layout-left">
                <section class="card side-card map-card">
                    <div class="side-card-head">
                        <h3>Mapa de jogos</h3>
                        <span class="pill-count"><?php echo e((string) count($mapMarkers)); ?> pins</span>
                    </div>
                    <?php if ($mapMarkers === []): ?>
                        <p class="muted">Nao ha jogos com coordenadas para exibir no mapa.</p>
                    <?php else: ?>
                        <div id="explore-map"></div>
                        <p class="map-hint">Clique no pin para destacar o card.</p>
                        <script id="explore-map-markers" type="application/json"><?php echo $mapMarkersJson; ?></script>
                        <script id="explore-map-user-location" type="application/json"><?php echo $userLocationJson; ?></script>
                    <?php endif; ?>
                </section>
            </aside>
        <?php endif; ?>

        <div class="layout-main">
            <section class="card mobile-filters">
                <form method="get" action="<?php echo e(url('explore.php')); ?>" id="explore-filter-form" class="mobile-filter-form">
                    <div class="field-full"><label for="search">Buscar local ou nome</label><input id="search" name="search" type="text" value="<?php echo e($searchTerm); ?>" placeholder="Ex: marista, praia, quadra 2"></div>
                    <div><label for="sport">Esporte</label><select id="sport" name="sport"><option value="">Todos</option><?php foreach (App\Services\InviteService::allowedSports() as $sport): ?><option value="<?php echo e($sport); ?>" <?php echo $selectedSport === $sport ? 'selected' : ''; ?>><?php echo e($sport); ?></option><?php endforeach; ?></select></div>
                    <div><label for="period">Periodo</label><select id="period" name="period"><option value="all" <?php echo $selectedPeriod === 'all' ? 'selected' : ''; ?>>Todos</option><option value="today" <?php echo $selectedPeriod === 'today' ? 'selected' : ''; ?>>Hoje</option><option value="week" <?php echo $selectedPeriod === 'week' ? 'selected' : ''; ?>>7 dias</option><option value="month" <?php echo $selectedPeriod === 'month' ? 'selected' : ''; ?>>30 dias</option></select></div>
                    <div><label for="radius_km">Raio</label><select id="radius_km" name="radius_km"><?php foreach (App\Services\InviteService::allowedRadii() as $radius): ?><option value="<?php echo e((string) $radius); ?>" <?php echo $selectedRadius === $radius ? 'selected' : ''; ?>><?php echo e((string) $radius); ?> km</option><?php endforeach; ?></select></div>
                    <label class="toggle-field"><input id="only_with_slots" name="only_with_slots" type="checkbox" value="1" <?php echo $onlyWithSlots ? 'checked' : ''; ?>><span>Somente com vagas</span></label>
                    <input type="hidden" id="lat" name="lat" value="<?php echo e($userLat !== null ? (string) $userLat : ''); ?>">
                    <input type="hidden" id="lng" name="lng" value="<?php echo e($userLng !== null ? (string) $userLng : ''); ?>">
                    <input type="hidden" id="location_label" name="location_label" value="<?php echo e($locationLabel); ?>">
                    <div class="field-full action-grid"><button type="submit">Aplicar filtros</button><button type="button" class="btn btn-outline" id="use-location-btn">Usar minha localizacao</button></div>
                </form>
                <div class="quick-chips">
                    <a class="quick-chip <?php echo $selectedPeriod === 'today' ? 'is-active' : ''; ?>" href="<?php echo e($buildUrl(['period' => 'today'])); ?>">Hoje</a>
                    <a class="quick-chip <?php echo $selectedPeriod === 'week' ? 'is-active' : ''; ?>" href="<?php echo e($buildUrl(['period' => 'week'])); ?>">Semana</a>
                    <a class="quick-chip <?php echo $onlyWithSlots ? 'is-active' : ''; ?>" href="<?php echo e($buildUrl(['only_with_slots' => '1'])); ?>">Com vagas</a>
                    <a class="quick-chip" href="<?php echo e(url('my_games.php')); ?>">Meus jogos</a>
                    <a class="quick-chip" href="<?php echo e($clearFiltersUrl); ?>">Limpar</a>
                </div>
                <?php if ($userLat !== null && $userLng !== null): ?><p class="location-inline" id="location-active-line">Localizacao ativa: <span id="location-active-label"><?php echo e($locationLabel !== '' ? $locationLabel : 'Detectando endereco...'); ?></span> <a href="<?php echo e($buildUrl(['lat' => '', 'lng' => '', 'location_label' => ''])); ?>">Limpar</a></p><?php endif; ?>
            </section>

            <section class="card mobile-summary">
                <div><small>Jogos no feed</small><strong><?php echo e((string) count($upcomingInvites)); ?></strong></div>
                <div><small>Muito perto</small><strong><?php echo e((string) $nearByCount); ?></strong></div>
                <div><small>Passados</small><strong><?php echo e((string) $pastInvitesCount); ?></strong></div>
            </section>

            <section class="mobile-feed" aria-label="Lista de jogos">
                <?php if ($upcomingInvites === []): ?>
                    <div class="card empty-state">Nenhum jogo encontrado.<div class="actions-row"><a class="btn" href="<?php echo e(url('create_invite.php')); ?>">Criar convite</a><a class="btn btn-outline" href="<?php echo e($clearFiltersUrl); ?>">Limpar filtros</a></div></div>
                <?php else: ?>
                    <?php foreach ($upcomingInvites as $invite): ?>
                        <article class="card game-card" data-invite-id="<?php echo e((string) $invite['id']); ?>">
                            <div class="invite-top"><span class="badge sport"><?php echo e((string) $invite['sport']); ?></span><span class="badge status <?php echo e((string) $invite['status_derived']); ?>"><?php echo e((string) $invite['status_label']); ?></span></div>
                            <h2 class="game-title"><?php echo e((string) $invite['location_name']); ?></h2>
                            <p class="game-line">Inicio: <?php echo e((string) $invite['starts_at_label']); ?></p>
                            <div class="chip-row">
                                <span class="meta-chip"><?php echo e((string) $invite['distance_label']); ?></span>
                            </div>
                            <div class="slots-row"><span>Jogadores</span><strong><?php echo e((string) $invite['players_count']); ?> / <?php echo e((string) $invite['max_players']); ?></strong></div>
                            <div class="actions-row">
                                <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Detalhes</a>
                                <?php if ($showMap && $invite['lat'] !== null && $invite['lng'] !== null): ?><button type="button" class="btn btn-outline btn-map-focus" data-focus-invite="<?php echo e((string) $invite['id']); ?>">Ver no mapa</button><?php endif; ?>
                                <?php if ($invite['can_join']): ?>
                                    <form method="post" action="<?php echo e(url('join_invite.php')); ?>" class="inline-form"><?php echo csrf_field(); ?><input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>"><input type="hidden" name="redirect_to" value="<?php echo e($returnPath); ?>"><button type="submit" class="<?php echo (string) $invite['join_mode'] === 'waitlist' ? 'btn-outline' : ''; ?>"><?php echo e((string) $invite['join_label']); ?></button></form>
                                <?php elseif ($invite['can_leave']): ?>
                                    <form method="post" action="<?php echo e(url('leave_invite.php')); ?>" class="inline-form"><?php echo csrf_field(); ?><input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>"><input type="hidden" name="redirect_to" value="<?php echo e($returnPath); ?>"><button type="submit" class="btn-danger"><?php echo (string) $invite['user_membership_role'] === 'waitlist' ? 'Sair da fila' : 'Sair'; ?></button></form>
                                <?php elseif ((bool) $invite['is_creator']): ?><span class="hint">Voce criou este convite.</span><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>

        <aside class="layout-right">
            <section class="card side-card"><div class="side-card-head"><h3>Seus proximos jogos</h3><span class="pill-count"><?php echo e((string) count($myUpcoming)); ?></span></div><?php if ($myUpcoming === []): ?><p class="side-empty">Sem jogos futuros.</p><?php else: ?><div class="side-list"><?php foreach ($myUpcoming as $invite): ?><a class="side-item" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>"><strong><?php echo e((string) $invite['location_name']); ?></strong><small><?php echo e((string) $invite['starts_at_label']); ?></small></a><?php endforeach; ?></div><?php endif; ?></section>
            <section class="card side-card"><div class="side-card-head"><h3>Quase lotando</h3><span class="pill-count"><?php echo e((string) count($nearFullInvites)); ?></span></div><?php if ($nearFullInvites === []): ?><p class="side-empty">Nenhum em alerta.</p><?php else: ?><div class="side-list"><?php foreach ($nearFullInvites as $invite): ?><a class="side-item" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>"><strong><?php echo e((string) $invite['location_name']); ?></strong><small><?php echo e((string) $invite['players_count']); ?>/<?php echo e((string) $invite['max_players']); ?> jogadores</small></a><?php endforeach; ?></div><?php endif; ?></section>
        </aside>
    </div>
</section>

<script>window.exploreConfig = { showMap: <?php echo $showMap ? 'true' : 'false'; ?> };</script>
<?php if ($showMap): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><?php endif; ?>
<script src="<?php echo e(url('assets/explore.js')); ?>"></script>
<?php
require __DIR__ . '/templates/footer.php';
