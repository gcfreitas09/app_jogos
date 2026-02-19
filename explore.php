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

$profile = $profileService->getProfile((int) $currentUser['id']);
$defaultRadius = $profile !== null ? (int) $profile['default_radius_km'] : 5;
if (!in_array($defaultRadius, App\Services\InviteService::allowedRadii(), true)) {
    $defaultRadius = 5;
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
$selectedRadius = (int) ($_GET['radius_km'] ?? $defaultRadius);
if (!in_array($selectedRadius, App\Services\InviteService::allowedRadii(), true)) {
    $selectedRadius = $defaultRadius;
}

$rawLat = trim((string) ($_GET['lat'] ?? ''));
$rawLng = trim((string) ($_GET['lng'] ?? ''));
$locationLabel = trim((string) ($_GET['location_label'] ?? ''));
if (strlen($locationLabel) > 190) {
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

$invites = $inviteService->listInvites(
    (int) $currentUser['id'],
    $selectedSport !== '' ? $selectedSport : null,
    $selectedPeriod,
    $onlyWithSlots,
    $userLat,
    $userLng,
    ($userLat !== null && $userLng !== null) ? $selectedRadius : null
);

$upcomingInvites = array_values(array_filter(
    $invites,
    static fn (array $invite): bool => !$invite['is_past']
));

$pastInvites = array_values(array_filter(
    $invites,
    static fn (array $invite): bool => $invite['is_past']
));

$myGames = $inviteService->getMyGames((int) $currentUser['id']);
$myUpcoming = [];
$seenMyIds = [];
foreach (array_merge($myGames['joined'], $myGames['created']) as $myGame) {
    $gameId = (int) $myGame['id'];
    if (isset($seenMyIds[$gameId]) || $myGame['is_past']) {
        continue;
    }
    $seenMyIds[$gameId] = true;
    $myUpcoming[] = $myGame;
    if (count($myUpcoming) >= 4) {
        break;
    }
}

$hotInvites = $upcomingInvites;
usort(
    $hotInvites,
    static function (array $a, array $b): int {
        $ratioA = $a['max_players'] > 0 ? $a['players_count'] / $a['max_players'] : 0;
        $ratioB = $b['max_players'] > 0 ? $b['players_count'] / $b['max_players'] : 0;

        return $ratioB <=> $ratioA;
    }
);
$hotInvites = array_slice($hotInvites, 0, 3);

$mapInvites = [];
foreach ($upcomingInvites as $invite) {
    if (!isset($invite['lat'], $invite['lng']) || $invite['lat'] === null || $invite['lng'] === null) {
        continue;
    }

    $mapInvites[] = [
        'id' => (int) $invite['id'],
        'lat' => (float) $invite['lat'],
        'lng' => (float) $invite['lng'],
        'title' => (string) $invite['location_name'],
        'sport' => (string) $invite['sport'],
        'starts_at_label' => (string) $invite['starts_at_label'],
        'url' => url('invite.php?id=' . (string) $invite['id']),
    ];
}

$mapInvitesJson = json_encode(
    $mapInvites,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
if (!is_string($mapInvitesJson)) {
    $mapInvitesJson = '[]';
}

$listQuery = array_filter(
    [
        'sport' => $selectedSport,
        'period' => $selectedPeriod !== 'all' ? $selectedPeriod : '',
        'only_with_slots' => $onlyWithSlots ? '1' : '',
        'radius_km' => (string) $selectedRadius,
        'lat' => $userLat !== null ? (string) $userLat : '',
        'lng' => $userLng !== null ? (string) $userLng : '',
        'location_label' => $locationLabel,
    ],
    static fn (string $value): bool => $value !== ''
);

$returnPath = 'explore.php';
if ($listQuery !== []) {
    $returnPath .= '?' . http_build_query($listQuery);
}

$title = 'Explorar';
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
        grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
        gap: 14px;
        align-items: start;
    }
    .explore-map-rail {
        position: sticky;
        top: 84px;
        align-self: start;
    }
    .explore-content-rail {
        min-width: 0;
    }
    .explore-map-card {
        margin: 0;
    }
    .explore-map-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }
    .explore-map-tools {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .explore-map-google-link {
        width: auto;
        padding: 6px 10px;
        font-size: 13px;
        white-space: nowrap;
    }
    .explore-map-canvas {
        width: 100%;
        height: 360px;
        border: 1px solid #d2e2ea;
        border-radius: 14px;
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
    @media (max-width: 1100px) {
        .explore-layout {
            grid-template-columns: minmax(250px, 300px) minmax(0, 1fr);
        }
        .explore-map-canvas {
            height: 430px;
        }
    }
    @media (max-width: 920px) {
        .explore-layout {
            grid-template-columns: 1fr;
        }
        .explore-map-rail {
            position: static;
        }
        .explore-map-card {
            margin-bottom: 14px;
        }
        .explore-map-canvas {
            height: 320px;
        }
    }
    @media (max-width: 760px) {
        .explore-map-canvas {
            height: 280px;
        }
    }
</style>
<section class="hero">
    <div class="hero-content">
        <h1>Explore jogos perto de você</h1>
        <p>Filtre por esporte, horário, vagas e raio de distância para entrar no jogo certo.</p>
    </div>
</section>

<div class="explore-layout">
    <aside class="explore-map-rail">
        <section class="card card-soft explore-map-card">
            <div class="explore-map-head">
                <h2>Mapa de jogos</h2>
                <div class="explore-map-tools">
                    <span class="pill-count"><?php echo e((string) count($mapInvites)); ?> pin(s)</span>
                    <a
                        id="explore-google-map-link"
                        class="btn btn-outline explore-map-google-link"
                        href="https://www.google.com/maps"
                        target="_blank"
                        rel="noopener noreferrer"
                    >Google Maps</a>
                </div>
            </div>
            <div
                id="explore-map"
                class="explore-map-canvas"
                data-user-lat="<?php echo e($userLat !== null ? (string) $userLat : ''); ?>"
                data-user-lng="<?php echo e($userLng !== null ? (string) $userLng : ''); ?>"
            ></div>
            <p class="muted explore-map-note">Clique no pin para destacar o convite correspondente.</p>
            <script type="application/json" id="explore-map-data"><?php echo $mapInvitesJson; ?></script>
        </section>
    </aside>

    <div class="explore-content-rail">
<section class="card card-soft">
    <form method="get" action="<?php echo e(url('explore.php')); ?>" class="filter-grid" id="explore-filter-form">
        <div>
            <label for="sport">Esporte</label>
            <select id="sport" name="sport">
                <option value="">Todos</option>
                <?php foreach (App\Services\InviteService::allowedSports() as $sport): ?>
                    <option value="<?php echo e($sport); ?>" <?php echo $selectedSport === $sport ? 'selected' : ''; ?>>
                        <?php echo e($sport); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="period">Data</label>
            <select id="period" name="period">
                <option value="all" <?php echo $selectedPeriod === 'all' ? 'selected' : ''; ?>>Todos</option>
                <option value="today" <?php echo $selectedPeriod === 'today' ? 'selected' : ''; ?>>Hoje</option>
                <option value="week" <?php echo $selectedPeriod === 'week' ? 'selected' : ''; ?>>Próximos 7 dias</option>
                <option value="month" <?php echo $selectedPeriod === 'month' ? 'selected' : ''; ?>>Próximo mês</option>
            </select>
        </div>

        <div>
            <label for="radius_km">Raio</label>
            <select id="radius_km" name="radius_km">
                <?php foreach (App\Services\InviteService::allowedRadii() as $radius): ?>
                    <option value="<?php echo e((string) $radius); ?>" <?php echo $selectedRadius === $radius ? 'selected' : ''; ?>>
                        <?php echo e((string) $radius); ?> km
                    </option>
                <?php endforeach; ?>
            </select>
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
            <button type="button" class="btn btn-outline" id="use-location-btn">Usar minha localização</button>
        </div>
    </form>
    <?php if ($userLat !== null && $userLng !== null): ?>
        <p class="muted" style="margin-top: 10px;" id="location-active-line">
            Localização ativa: <span id="location-active-label"><?php echo e($locationLabel !== '' ? $locationLabel : 'Obtendo endereço...'); ?></span>
            - <a href="<?php echo e(url('explore.php?' . http_build_query(array_filter([
                'sport' => $selectedSport,
                'period' => $selectedPeriod !== 'all' ? $selectedPeriod : '',
                'only_with_slots' => $onlyWithSlots ? '1' : '',
                'radius_km' => (string) $selectedRadius,
            ], static fn (string $value): bool => $value !== '')))); ?>">Limpar localização</a>
        </p>
    <?php endif; ?>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Próximos de você</h2>
        <span class="pill-count"><?php echo e((string) count($upcomingInvites)); ?> resultado(s)</span>
    </div>
    <?php if ($upcomingInvites === []): ?>
        <div class="empty-state card-soft">
            <?php if ($userLat !== null && $userLng !== null): ?>
                Nenhum jogo encontrado dentro de <?php echo e((string) $selectedRadius); ?> km da sua localização.
            <?php else: ?>
                Nenhum jogo encontrado com os filtros atuais.
            <?php endif; ?>
        </div>
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
                    <p class="muted">Início: <?php echo e((string) $invite['starts_at_label']); ?></p>
                    <?php if ($userLat !== null && $userLng !== null && $invite['distance_km'] !== null): ?>
                        <p class="muted">Distância: <?php echo e((string) $invite['distance_label']); ?> de você</p>
                    <?php elseif ($userLat !== null && $userLng !== null): ?>
                        <p class="muted">Distância: local sem coordenadas</p>
                    <?php else: ?>
                        <p class="muted">Distância: <?php echo e((string) $invite['distance_label']); ?></p>
                    <?php endif; ?>
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
                            <span class="hint">Convite criado por você</span>
                        <?php else: ?>
                            <span class="hint">Indisponível</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section-block">
    <div class="section-head">
        <h2>Seus próximos jogos</h2>
        <a href="<?php echo e(url('my_games.php')); ?>" class="btn btn-outline">Ver todos</a>
    </div>
    <?php if ($myUpcoming === []): ?>
        <div class="empty-state card-soft">Você ainda não tem jogos futuros em sua agenda.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($myUpcoming as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="badge status <?php echo e((string) $invite['status_derived']); ?>"><?php echo e((string) $invite['status_label']); ?></span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted">Início: <?php echo e((string) $invite['starts_at_label']); ?></p>
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
        <h2>Em alta</h2>
        <span class="pill-count">Quase completos</span>
    </div>
    <?php if ($hotInvites === []): ?>
        <div class="empty-state card-soft">Sem jogos em destaque no momento.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($hotInvites as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="pill-count"><?php echo e((string) $invite['players_count']); ?>/<?php echo e((string) $invite['max_players']); ?></span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted">Início: <?php echo e((string) $invite['starts_at_label']); ?></p>
                    <div class="actions-row">
                        <a class="btn btn-outline" href="<?php echo e(url('invite.php?id=' . (string) $invite['id'])); ?>">Detalhes</a>
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
    var locationButton = document.getElementById('use-location-btn');
    var latInput = document.getElementById('lat');
    var lngInput = document.getElementById('lng');
    var locationLabelInput = document.getElementById('location_label');
    var locationLabelNode = document.getElementById('location-active-label');

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
                locationLabelNode.textContent = 'Localização detectada';
            }
        }
    }

    fillLabelIfMissing();

    if (locationButton) {
        locationButton.addEventListener('click', function () {
            if (!navigator.geolocation) {
                alert('Geolocalização não suportada neste navegador.');
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
                        locationLabelInput.value = 'Localização detectada';
                    }

                    document.getElementById('explore-filter-form').submit();
                },
                function () {
                    locationButton.disabled = false;
                    locationButton.textContent = 'Usar minha localização';
                    alert('Não foi possível capturar sua localização.');
                },
                {
                    enableHighAccuracy: true,
                    timeout: 8000,
                    maximumAge: 60000
                }
            );
        });
    }

    var mapElement = document.getElementById('explore-map');
    var mapDataNode = document.getElementById('explore-map-data');
    var googleMapLink = document.getElementById('explore-google-map-link');
    if (!mapElement || !mapDataNode || typeof window.L === 'undefined') {
        return;
    }

    function parseCoordinate(rawValue) {
        if (rawValue === null || rawValue === undefined) {
            return null;
        }

        var text = String(rawValue).trim();
        if (text === '') {
            return null;
        }

        text = text.replace(',', '.');
        var numberValue = Number(text);
        if (!Number.isFinite(numberValue)) {
            return null;
        }

        return numberValue;
    }

    var mapRows = [];
    try {
        mapRows = JSON.parse(mapDataNode.textContent || '[]');
    } catch (error) {
        mapRows = [];
    }

    if (!Array.isArray(mapRows) || mapRows.length === 0) {
        mapElement.innerHTML = '<div class="empty-state" style="margin: 10px;">Nenhum convite com localização nos filtros atuais.</div>';
        return;
    }

    var userLatMap = parseCoordinate(mapElement.dataset.userLat || null);
    var userLngMap = parseCoordinate(mapElement.dataset.userLng || null);

    function setGoogleMapsLink(destinationLat, destinationLng) {
        if (!googleMapLink) {
            return;
        }
        if (destinationLat === null || destinationLng === null) {
            googleMapLink.setAttribute('href', 'https://www.google.com/maps');
            return;
        }

        var destination = String(destinationLat) + ',' + String(destinationLng);
        if (userLatMap !== null && userLngMap !== null) {
            var origin = String(userLatMap) + ',' + String(userLngMap);
            googleMapLink.setAttribute(
                'href',
                'https://www.google.com/maps/dir/?api=1&origin='
                    + encodeURIComponent(origin)
                    + '&destination='
                    + encodeURIComponent(destination)
            );
            return;
        }

        googleMapLink.setAttribute(
            'href',
            'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(destination)
        );
    }

    var map = L.map(mapElement, {
        zoomControl: true,
        scrollWheelZoom: false
    });

    L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
        attribution: '&copy; Google Maps'
    }).addTo(map);

    var bounds = L.latLngBounds();

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function focusInviteCard(inviteId) {
        var cards = document.querySelectorAll('.invite-card[data-invite-id]');
        cards.forEach(function (card) {
            card.classList.remove('is-map-selected');
        });

        var selectedCard = document.getElementById('invite-card-' + String(inviteId));
        if (!selectedCard) {
            return;
        }

        selectedCard.classList.add('is-map-selected');
        selectedCard.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }

    mapRows.forEach(function (row) {
        var lat = parseCoordinate(row.lat);
        var lng = parseCoordinate(row.lng);
        if (lat === null || lng === null) {
            return;
        }

        var marker = L.marker([lat, lng]).addTo(map);
        bounds.extend([lat, lng]);
        marker.bindPopup(
            '<strong>' + escapeHtml(row.title) + '</strong>'
            + '<br>' + escapeHtml(row.sport) + ' - ' + escapeHtml(row.starts_at_label)
            + '<br><a href="' + escapeHtml(row.url) + '">Abrir convite</a>'
        );
        marker.on('click', function () {
            focusInviteCard(row.id);
            setGoogleMapsLink(lat, lng);
        });
    });

    if (mapRows.length > 0) {
        var firstLat = parseCoordinate(mapRows[0].lat);
        var firstLng = parseCoordinate(mapRows[0].lng);
        setGoogleMapsLink(firstLat, firstLng);
    }

    if (userLatMap !== null && userLngMap !== null) {
        L.circleMarker([userLatMap, userLngMap], {
            radius: 8,
            color: '#0b7f6f',
            weight: 2,
            fillColor: '#33d1bc',
            fillOpacity: 0.9
        }).addTo(map).bindPopup('Sua localização');
        bounds.extend([userLatMap, userLngMap]);
    }

    if (bounds.isValid()) {
        map.fitBounds(bounds.pad(0.16));
    } else {
        map.setView([-30.03, -51.23], 11);
    }
});
</script>
<?php
require __DIR__ . '/templates/footer.php';

