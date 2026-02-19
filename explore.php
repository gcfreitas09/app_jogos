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
if (!App\Services\InviteService::isValidRadius($defaultRadius)) {
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
<section class="hero">
    <div class="hero-content">
        <h1>Explore jogos perto de você</h1>
        <p>Filtre por esporte, horário, vagas e raio de distância para entrar no jogo certo.</p>
    </div>
</section>

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
        <div class="empty-state card-soft">Nenhum jogo encontrado com os filtros atuais.</div>
    <?php else: ?>
        <div class="invite-grid">
            <?php foreach ($upcomingInvites as $invite): ?>
                <article class="invite-card">
                    <div class="invite-top">
                        <span class="badge sport"><?php echo e((string) $invite['sport']); ?></span>
                        <span class="badge status <?php echo e((string) $invite['status_derived']); ?>"><?php echo e((string) $invite['status_label']); ?></span>
                    </div>
                    <h3><?php echo e((string) $invite['location_name']); ?></h3>
                    <p class="muted"><?php echo e((string) $invite['address']); ?></p>
                    <p class="muted">Início: <?php echo e((string) $invite['starts_at_label']); ?></p>
                    <p class="muted">Distância: <?php echo e((string) $invite['distance_label']); ?></p>
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

    if (!locationButton) {
        return;
    }

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
});
</script>
<?php
require __DIR__ . '/templates/footer.php';

