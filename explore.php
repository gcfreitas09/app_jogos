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

$allowedSports = App\Services\InviteService::allowedSports();
$selectedSportRaw = trim((string) ($_GET['sport'] ?? ''));
$selectedSportOther = trim((string) ($_GET['sport_other'] ?? ''));
if (function_exists('mb_substr')) {
    $selectedSportOther = mb_substr($selectedSportOther, 0, 60);
} else {
    $selectedSportOther = substr($selectedSportOther, 0, 60);
}
$selectedSport = '';
$selectedSportForQuery = null;
if ($selectedSportRaw === 'outros') {
    $selectedSport = 'outros';
} elseif ($selectedSportRaw !== '' && in_array($selectedSportRaw, $allowedSports, true)) {
    $selectedSport = $selectedSportRaw;
    $selectedSportForQuery = $selectedSportRaw;
}

$selectedPeriod = trim((string) ($_GET['period'] ?? 'all'));
if (!in_array($selectedPeriod, App\Services\InviteService::allowedPeriods(), true)) {
    $selectedPeriod = 'all';
}

$onlyWithSlots = ((string) ($_GET['only_with_slots'] ?? '0')) === '1';
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
    $selectedSportForQuery,
    $selectedPeriod,
    $onlyWithSlots,
    $userLat,
    $userLng,
    $radiusFilter
);

$sportOtherNeedle = '';
if ($selectedSport === 'outros' && $selectedSportOther !== '') {
    $sportOtherNeedle = function_exists('mb_strtolower')
        ? mb_strtolower($selectedSportOther)
        : strtolower($selectedSportOther);
}

$invites = array_values(array_filter(
    $invites,
    static function (array $invite) use ($searchTerm, $sportOtherNeedle): bool {
        if ($sportOtherNeedle !== '') {
            $inviteSport = (string) ($invite['sport'] ?? '');
            $inviteSport = function_exists('mb_strtolower') ? mb_strtolower($inviteSport) : strtolower($inviteSport);
            if (strpos($inviteSport, $sportOtherNeedle) === false) {
                return false;
            }
        }

        if ($searchTerm === '') {
            return true;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($searchTerm) : strtolower($searchTerm);
        $haystack = implode(' ', [
            (string) ($invite['location_name'] ?? ''),
            (string) ($invite['address'] ?? ''),
            (string) ($invite['sport'] ?? ''),
            (string) ($invite['description'] ?? ''),
        ]);
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);

        return strpos($haystack, $needle) !== false;
    }
));

$upcomingInvites = array_values(array_filter(
    $invites,
    static fn (array $invite): bool => !(bool) ($invite['is_past'] ?? false)
));
$pastInvites = array_values(array_filter(
    $invites,
    static fn (array $invite): bool => (bool) ($invite['is_past'] ?? false)
));

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
    'sport_other' => $selectedSport === 'outros' ? $selectedSportOther : '',
    'period' => $selectedPeriod !== 'all' ? $selectedPeriod : '',
    'only_with_slots' => $onlyWithSlots ? '1' : '',
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

$returnPath = 'explore.php';
$currentQuery = $buildQuery();
if ($currentQuery !== '') {
    $returnPath .= '?' . $currentQuery;
}

$mapMarkers = [];
foreach ($upcomingInvites as $invite) {
    if (($invite['lat'] ?? null) === null || ($invite['lng'] ?? null) === null) {
        continue;
    }

    $mapMarkers[] = [
        'id' => (int) $invite['id'],
        'lat' => (float) $invite['lat'],
        'lng' => (float) $invite['lng'],
        'sport' => (string) $invite['sport'],
        'location' => (string) $invite['location_name'],
        'starts_at' => (string) $invite['starts_at_label'],
        'url' => url('invite.php?id=' . (string) $invite['id']),
    ];
}

$mapMarkersJson = json_encode(
    $mapMarkers,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($mapMarkersJson === false) {
    $mapMarkersJson = '[]';
}
$userLocationJson = json_encode(
    $userLat !== null && $userLng !== null ? ['lat' => $userLat, 'lng' => $userLng] : null,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($userLocationJson === false) {
    $userLocationJson = 'null';
}

$title = 'Explorar';
$pageClass = 'page-explore';
require __DIR__ . '/templates/header.php';
?>
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>
<style>
    .explore-layout {
        display: grid;
        grid-template-columns: minmax(280px, 330px) minmax(0, 1fr);
        gap: 14px;
        align-items: start;
    }
    .explore-map-rail {
        position: sticky;
        top: 84px;
    }
    .explore-map-canvas {
        width: 100%;
        height: 420px;
        border-radius: 14px;
        border: 1px solid #cddce5;
        overflow: hidden;
    }
    .explore-map-note {
        margin-top: 10px;
    }
    .invite-card.is-map-selected {
        border-color: #f28c3d;
        box-shadow: 0 0 0 2px rgba(242, 140, 61, 0.25), 0 8px 22px rgba(34, 62, 83, 0.08);
    }
    html[data-theme='dark'] .explore-map-canvas {
        border-color: #35536a;
    }
    @media (max-width: 1020px) {
        .explore-layout {
            grid-template-columns: 1fr;
        }
        .explore-map-rail {
            position: static;
        }
        .explore-map-canvas {
            height: 320px;
        }
    }
    @media (max-width: 560px) {
        .explore-map-canvas {
            height: 250px;
            border-radius: 12px;
        }
    }
</style>

<section class="hero">
    <div class="hero-content">
        <h1>Explore jogos perto de voce</h1>
        <p>Filtre por esporte, horario, vagas e raio de distancia para entrar no jogo certo.</p>
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
</section>

<div class="explore-layout">
    <aside class="explore-map-rail">
        <section class="card card-soft">
            <div class="section-head">
                <h2>Mapa de jogos</h2>
                <span class="pill-count"><?php echo e((string) count($mapMarkers)); ?> pin(s)</span>
            </div>
            <div id="explore-map" class="explore-map-canvas"></div>
            <p class="muted explore-map-note">Mapa fixado no explorar. Clique no pin para destacar o convite.</p>
            <script type="application/json" id="explore-map-data"><?php echo $mapMarkersJson; ?></script>
            <script type="application/json" id="explore-map-user"><?php echo $userLocationJson; ?></script>
        </section>
    </aside>

    <div class="explore-main-rail">
        <section class="card card-soft">
            <form method="get" action="<?php echo e(url('explore.php')); ?>" class="filter-grid" id="explore-filter-form">
                <div>
                    <label for="sport">Esporte</label>
                    <select id="sport" name="sport">
                        <option value="">Todos</option>
                        <?php foreach ($allowedSports as $sport): ?>
                            <option value="<?php echo e($sport); ?>" <?php echo $selectedSport === $sport ? 'selected' : ''; ?>>
                                <?php echo e($sport); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="outros" <?php echo $selectedSport === 'outros' ? 'selected' : ''; ?>>Outros</option>
                    </select>
                    <div id="sport-other-wrap" style="margin-top: 10px;" <?php echo $selectedSport === 'outros' ? '' : 'hidden'; ?>>
                        <label for="sport_other">Qual esporte?</label>
                        <input
                            id="sport_other"
                            type="text"
                            name="sport_other"
                            maxlength="60"
                            value="<?php echo e($selectedSportOther); ?>"
                            placeholder="Digite o esporte"
                            <?php echo $selectedSport === 'outros' ? 'required' : ''; ?>
                        >
                    </div>
                </div>

                <div>
                    <label for="period">Data</label>
                    <select id="period" name="period">
                        <option value="all" <?php echo $selectedPeriod === 'all' ? 'selected' : ''; ?>>Todos</option>
                        <option value="today" <?php echo $selectedPeriod === 'today' ? 'selected' : ''; ?>>Hoje</option>
                        <option value="week" <?php echo $selectedPeriod === 'week' ? 'selected' : ''; ?>>Proximos 7 dias</option>
                        <option value="month" <?php echo $selectedPeriod === 'month' ? 'selected' : ''; ?>>Proximo mes</option>
                    </select>
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

                <div>
                    <label for="radius_km">Raio de busca (km)</label>
                    <input
                        id="radius_km"
                        name="radius_km"
                        type="number"
                        min="<?php echo e((string) App\Services\InviteService::MIN_RADIUS_KM); ?>"
                        max="<?php echo e((string) App\Services\InviteService::MAX_RADIUS_KM); ?>"
                        step="1"
                        list="radius_km_options"
                        value="<?php echo e((string) $selectedRadius); ?>"
                    >
                    <datalist id="radius_km_options">
                        <?php foreach (App\Services\InviteService::allowedRadii() as $radius): ?>
                            <option value="<?php echo e((string) $radius); ?>"><?php echo e((string) $radius); ?> km</option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="filter-check">
                    <label for="only_with_slots">Somente com vagas</label>
                    <input id="only_with_slots" name="only_with_slots" type="checkbox" value="1" <?php echo $onlyWithSlots ? 'checked' : ''; ?>>
                </div>

                <input type="hidden" id="lat" name="lat" value="<?php echo e($userLat !== null ? (string) $userLat : ''); ?>">
                <input type="hidden" id="lng" name="lng" value="<?php echo e($userLng !== null ? (string) $userLng : ''); ?>">
                <input type="hidden" id="location_label" name="location_label" value="<?php echo e($locationLabel); ?>">

                <div class="filter-actions" style="grid-column: 1 / -1;">
                    <button type="submit">Aplicar filtros</button>
                    <button type="button" class="btn btn-outline" id="use-location-btn">Usar minha localizacao</button>
                </div>
            </form>
            <?php if ($userLat !== null && $userLng !== null): ?>
                <p class="muted" style="margin-top: 10px;" id="location-active-line">
                    Localizacao ativa: <span id="location-active-label"><?php echo e($locationLabel !== '' ? $locationLabel : 'Obtendo endereco...'); ?></span>
                    - <a href="<?php echo e(url('explore.php?' . http_build_query(array_filter([
                        'sport' => $selectedSport,
                        'sport_other' => $selectedSport === 'outros' ? $selectedSportOther : '',
                        'period' => $selectedPeriod !== 'all' ? $selectedPeriod : '',
                        'only_with_slots' => $onlyWithSlots ? '1' : '',
                        'radius_km' => (string) $selectedRadius,
                    ], static fn (string $value): bool => $value !== '')))); ?>">Limpar localizacao</a>
                </p>
            <?php endif; ?>
        </section>
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

        <section class="section-block">
            <div class="section-head">
                <h2>Proximos de voce</h2>
                <span class="pill-count"><?php echo e((string) count($upcomingInvites)); ?> resultado(s)</span>
            </div>
            <?php if ($upcomingInvites === []): ?>
                <div class="empty-state card-soft">Nenhum jogo encontrado com os filtros atuais.</div>
            <?php else: ?>
                <div class="invite-grid">
                    <?php foreach ($upcomingInvites as $invite): ?>
                        <article class="invite-card" id="invite-card-<?php echo e((string) $invite['id']); ?>" data-invite-id="<?php echo e((string) $invite['id']); ?>">
                            <div class="invite-top">
                                <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                                <span class="badge status <?php echo e((string) $invite['status_derived']); ?>"><?php echo e((string) $invite['status_label']); ?></span>
                            </div>
                            <h3><?php echo e((string) $invite['location_name']); ?></h3>
                            <p class="muted"><?php echo e((string) $invite['address']); ?></p>
                            <p class="muted">Inicio: <?php echo e((string) $invite['starts_at_label']); ?></p>
                            <p class="muted">Distancia: <?php echo e((string) $invite['distance_label']); ?></p>
                            <div class="slots-row">
                                <span>Vagas</span>
                                <strong><?php echo e((string) $invite['players_count']); ?> / <?php echo e((string) $invite['max_players']); ?></strong>
                            </div>
                            <p class="muted" style="margin-top: 6px;">Fila: <?php echo e((string) $invite['waitlist_count']); ?></p>
                            <div class="actions-row">
                                <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Ver detalhes</a>
                                <?php if ($invite['can_join']): ?>
                                    <form method="post" action="<?php echo e(url('join_invite.php')); ?>" class="inline-form">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>">
                                        <input type="hidden" name="redirect_to" value="<?php echo e($returnPath); ?>">
                                        <button type="submit"><?php echo e((string) $invite['join_label']); ?></button>
                                    </form>
                                <?php elseif ($invite['can_leave']): ?>
                                    <form method="post" action="<?php echo e(url('leave_invite.php')); ?>" class="inline-form">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="invite_id" value="<?php echo e((string) $invite['id']); ?>">
                                        <input type="hidden" name="redirect_to" value="<?php echo e($returnPath); ?>">
                                        <button type="submit" class="btn-danger">
                                            <?php echo $invite['user_membership_role'] === 'waitlist' ? 'Sair da fila' : 'Sair'; ?>
                                        </button>
                                    </form>
                                <?php elseif ($invite['is_creator']): ?>
                                    <span class="hint">Convite criado por voce</span>
                                <?php else: ?>
                                    <span class="hint">Indisponivel</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="section-block">
            <div class="section-head">
                <h2>Seus proximos jogos</h2>
                <a href="<?php echo e(url('my_games.php')); ?>" class="btn btn-outline">Ver todos</a>
            </div>
            <?php if ($myUpcoming === []): ?>
                <div class="empty-state card-soft">Voce ainda nao tem jogos futuros em sua agenda.</div>
            <?php else: ?>
                <div class="invite-grid">
                    <?php foreach ($myUpcoming as $invite): ?>
                        <article class="invite-card">
                            <div class="invite-top">
                                <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                                <span class="badge status <?php echo e((string) $invite['status_derived']); ?>"><?php echo e((string) $invite['status_label']); ?></span>
                            </div>
                            <h3><?php echo e((string) $invite['location_name']); ?></h3>
                            <p class="muted">Inicio: <?php echo e((string) $invite['starts_at_label']); ?></p>
                            <div class="slots-row">
                                <span>Vagas</span>
                                <strong><?php echo e((string) $invite['players_count']); ?> / <?php echo e((string) $invite['max_players']); ?></strong>
                            </div>
                            <div class="actions-row">
                                <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Abrir convite</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

        <section class="section-block">
            <div class="section-head">
                <h2>Passados</h2>
                <span class="pill-count"><?php echo e((string) count($pastInvites)); ?></span>
            </div>
            <?php if ($pastInvites === []): ?>
                <div class="empty-state card-soft">Nenhum jogo passado para mostrar.</div>
            <?php else: ?>
                <div class="invite-grid past-grid">
                    <?php foreach ($pastInvites as $invite): ?>
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
    </div>
</div>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var sportSelect = document.getElementById('sport');
    var sportOtherWrap = document.getElementById('sport-other-wrap');
    var sportOtherInput = document.getElementById('sport_other');
    var locationButton = document.getElementById('use-location-btn');
    var latInput = document.getElementById('lat');
    var lngInput = document.getElementById('lng');
    var locationLabelInput = document.getElementById('location_label');
    var locationLabelNode = document.getElementById('location-active-label');

    function syncSportOtherVisibility() {
        if (!sportSelect || !sportOtherWrap || !sportOtherInput) {
            return;
        }

        var isOther = sportSelect.value === 'outros';
        sportOtherWrap.hidden = !isOther;
        sportOtherInput.required = isOther;

        if (!isOther) {
            sportOtherInput.value = '';
        }
    }

    if (sportSelect) {
        syncSportOtherVisibility();
        sportSelect.addEventListener('change', syncSportOtherVisibility);
    }

    async function reverseGeocode(lat, lng) {
        var reverseUrl = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='
            + encodeURIComponent(lat)
            + '&lon='
            + encodeURIComponent(lng);

        var response = await fetch(reverseUrl, {
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) {
            throw new Error('reverse_failed');
        }

        var data = await response.json();
        if (!data || !data.display_name) {
            throw new Error('reverse_empty');
        }

        return data.display_name;
    }

    async function fillLabelIfMissing() {
        if (!latInput || !lngInput || !locationLabelInput) {
            return;
        }

        if (!latInput.value || !lngInput.value || locationLabelInput.value) {
            return;
        }

        try {
            var label = await reverseGeocode(latInput.value, lngInput.value);
            locationLabelInput.value = label;
            if (locationLabelNode) {
                locationLabelNode.textContent = label;
            }
        } catch (error) {
            if (locationLabelNode) {
                locationLabelNode.textContent = 'Localizacao detectada';
            }
        }
    }

    fillLabelIfMissing();

    if (locationButton) {
        locationButton.addEventListener('click', function () {
            if (!navigator.geolocation) {
                alert('Geolocalizacao nao suportada neste navegador.');
                return;
            }

            locationButton.disabled = true;
            locationButton.textContent = 'Capturando...';

            navigator.geolocation.getCurrentPosition(async function (position) {
                    var lat = position.coords.latitude.toFixed(7);
                    var lng = position.coords.longitude.toFixed(7);

                    latInput.value = lat;
                    lngInput.value = lng;

                    try {
                        var label = await reverseGeocode(lat, lng);
                        locationLabelInput.value = label;
                    } catch (error) {
                        locationLabelInput.value = 'Localizacao detectada';
                    }

                    document.getElementById('explore-filter-form').submit();
                },
                function () {
                    locationButton.disabled = false;
                    locationButton.textContent = 'Usar minha localizacao';
                    alert('Nao foi possivel capturar sua localizacao.');
                },
                {
                    enableHighAccuracy: true,
                    timeout: 8000,
                    maximumAge: 60000
                }
            );
        });
    }

    var mapNode = document.getElementById('explore-map');
    var mapDataNode = document.getElementById('explore-map-data');
    var mapUserNode = document.getElementById('explore-map-user');
    if (!mapNode || !mapDataNode || typeof window.L === 'undefined') {
        return;
    }

    var mapMarkers = [];
    try {
        mapMarkers = JSON.parse(mapDataNode.textContent || '[]');
    } catch (error) {
        mapMarkers = [];
    }
    if (!Array.isArray(mapMarkers)) {
        mapMarkers = [];
    }

    var userPosition = null;
    if (mapUserNode) {
        try {
            userPosition = JSON.parse(mapUserNode.textContent || 'null');
        } catch (error) {
            userPosition = null;
        }
    }

    function parseCoordinate(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        var numeric = Number(String(value).replace(',', '.'));
        return Number.isFinite(numeric) ? numeric : null;
    }

    function focusCard(inviteId) {
        var cards = document.querySelectorAll('.invite-card[data-invite-id]');
        cards.forEach(function (card) {
            card.classList.remove('is-map-selected');
        });

        var selectedCard = document.getElementById('invite-card-' + String(inviteId));
        if (!selectedCard) {
            return;
        }

        selectedCard.classList.add('is-map-selected');
        selectedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (mapMarkers.length === 0) {
        mapNode.innerHTML = '<div class="empty-state" style="margin:8px;">Nenhum convite com coordenadas para mostrar no mapa.</div>';
        return;
    }

    var map = L.map(mapNode, {
        zoomControl: true,
        scrollWheelZoom: false
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    var bounds = L.latLngBounds();

    mapMarkers.forEach(function (row) {
        var lat = parseCoordinate(row.lat);
        var lng = parseCoordinate(row.lng);
        if (lat === null || lng === null) {
            return;
        }

        var marker = L.marker([lat, lng]).addTo(map);
        marker.bindPopup(
            '<strong>' + String(row.location || '') + '</strong><br>'
            + String(row.sport || '') + ' - ' + String(row.starts_at || '')
        );
        marker.on('click', function () {
            focusCard(row.id);
        });

        bounds.extend([lat, lng]);
    });

    var userLat = userPosition ? parseCoordinate(userPosition.lat) : null;
    var userLng = userPosition ? parseCoordinate(userPosition.lng) : null;
    if (userLat !== null && userLng !== null) {
        L.circleMarker([userLat, userLng], {
            radius: 8,
            color: '#0b7f6f',
            weight: 2,
            fillColor: '#2cd5ba',
            fillOpacity: 0.9
        }).addTo(map).bindPopup('Sua localizacao');
        bounds.extend([userLat, userLng]);
    }

    if (bounds.isValid()) {
        map.fitBounds(bounds.pad(0.18));
    } else {
        map.setView([-30.03, -51.23], 11);
    }
});
</script>
<script>window.exploreConfig = { showMap: <?php echo $showMap ? 'true' : 'false'; ?> };</script>
<?php if ($showMap): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><?php endif; ?>
<script src="<?php echo e(url('assets/explore.js')); ?>"></script>
<?php
require __DIR__ . '/templates/footer.php';
