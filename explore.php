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
$defaultRadius = $profile !== null ? (int) ($profile['default_radius_km'] ?? 5) : 5;
if (!App\Services\InviteService::isValidRadius($defaultRadius)) {
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
if (!App\Services\InviteService::isValidRadius($selectedRadius)) {
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
            $inviteSport = function_exists('mb_strtolower')
                ? mb_strtolower($inviteSport)
                : strtolower($inviteSport);
            if (strpos($inviteSport, $sportOtherNeedle) === false) {
                return false;
            }
        }

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
    if ((bool) ($myGame['is_past'] ?? false)) {
        continue;
    }

    $gameId = (int) ($myGame['id'] ?? 0);
    if ($gameId <= 0 || isset($seenMyGameIds[$gameId])) {
        continue;
    }

    $seenMyGameIds[$gameId] = true;
    $myUpcoming[] = $myGame;
    if (count($myUpcoming) >= 4) {
        break;
    }
}

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

$clearLocationQuery = $buildQuery([
    'lat' => '',
    'lng' => '',
    'location_label' => '',
]);
$clearLocationUrl = url('explore.php' . ($clearLocationQuery !== '' ? '?' . $clearLocationQuery : ''));

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
            <p class="muted explore-map-note">Mapa OpenStreetMap fixado no explorar. Clique no pin para destacar o convite.</p>
            <script type="application/json" id="explore-map-data"><?php echo $mapMarkersJson; ?></script>
            <script type="application/json" id="explore-map-user"><?php echo $userLocationJson; ?></script>
        </section>
    </aside>

    <div class="explore-main-rail">
        <section class="card card-soft">
            <form method="get" action="<?php echo e(url('explore.php')); ?>" class="filter-grid" id="explore-filter-form">
                <div>
                    <label for="search">Buscar por local ou nome</label>
                    <input
                        id="search"
                        type="text"
                        name="search"
                        value="<?php echo e($searchTerm); ?>"
                        maxlength="120"
                        placeholder="Ex: marista, praia, quadra 2"
                    >
                </div>

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
                    - <a href="<?php echo e($clearLocationUrl); ?>">Limpar localizacao</a>
                </p>
            <?php endif; ?>
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
    var reverseGeocodeEndpoint = <?php echo json_encode(url('api/reverse_geocode.php')); ?>;

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
        var response = await fetch(
            reverseGeocodeEndpoint
                + '?lat=' + encodeURIComponent(String(lat))
                + '&lng=' + encodeURIComponent(String(lng)),
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }
        );
        if (!response.ok) {
            throw new Error('reverse_fetch_failed');
        }

        var payload = await response.json();
        if (!payload || payload.success !== true || typeof payload.address !== 'string' || payload.address.trim() === '') {
            throw new Error('reverse_invalid_payload');
        }

        return payload.address.trim();
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

            navigator.geolocation.getCurrentPosition(
                async function (position) {
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
    if (!mapNode || !mapDataNode) {
        return;
    }

    if (typeof window.L === 'undefined') {
        mapNode.innerHTML = '<div class="empty-state" style="margin:8px;">Nao foi possivel carregar o mapa.</div>';
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

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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
    var hasBounds = false;
    var markerByInviteId = new Map();

    mapMarkers.forEach(function (row) {
        var lat = parseCoordinate(row.lat);
        var lng = parseCoordinate(row.lng);
        if (lat === null || lng === null) {
            return;
        }

        var marker = L.marker([lat, lng]).addTo(map);
        marker.bindPopup(
            '<strong>' + escapeHtml(row.location || '') + '</strong><br>'
            + escapeHtml(row.sport || '') + ' - ' + escapeHtml(row.starts_at || '')
        );
        marker.on('click', function () {
            focusCard(row.id);
        });

        markerByInviteId.set(String(row.id), marker);
        bounds.extend([lat, lng]);
        hasBounds = true;
    });

    var userLat = userPosition ? parseCoordinate(userPosition.lat) : null;
    var userLng = userPosition ? parseCoordinate(userPosition.lng) : null;
    if (userLat !== null && userLng !== null) {
        L.circleMarker([userLat, userLng], {
            radius: 8,
            color: '#0b7f6f',
            weight: 2,
            fillColor: '#2cd5ba',
            fillOpacity: 0.95
        }).addTo(map).bindPopup('Sua localizacao');
        bounds.extend([userLat, userLng]);
        hasBounds = true;
    }

    if (hasBounds) {
        map.fitBounds(bounds, { padding: [24, 24], maxZoom: 15 });
    } else {
        map.setView([-30.0346, -51.2177], 11);
    }

    var inviteCards = document.querySelectorAll('.invite-card[data-invite-id]');
    inviteCards.forEach(function (card) {
        card.addEventListener('click', function (event) {
            if (event.target.closest('a,button,input,form,select,label,textarea')) {
                return;
            }
            var inviteId = String(card.dataset.inviteId || '');
            if (inviteId === '') {
                return;
            }
            var marker = markerByInviteId.get(inviteId);
            if (!marker) {
                return;
            }

            focusCard(inviteId);
            marker.openPopup();
            map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 14), { duration: 0.35 });
        });
    });

    window.setTimeout(function () {
        map.invalidateSize();
    }, 120);
});
</script>
<?php
require __DIR__ . '/templates/footer.php';
