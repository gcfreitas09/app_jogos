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

$errors = [];
$sport = '';
$sportCustom = '';
$level = 'intermediario';
$startsAt = (new DateTimeImmutable('+1 day'))->format('Y-m-d\TH:i');
$locationName = '';
$address = '';
$lat = '';
$lng = '';
$googleMapsUrl = '';
$maxPlayers = 4;
$privacy = 'public';
$description = '';
$price = '';
$rulesText = '';

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Token CSRF inválido.';
    } else {
        $sport = trim((string) ($_POST['sport'] ?? ''));
        $sportCustom = trim((string) ($_POST['sport_custom'] ?? ''));
        $level = trim((string) ($_POST['level'] ?? 'intermediario'));
        $startsAt = trim((string) ($_POST['starts_at'] ?? ''));
        $locationName = trim((string) ($_POST['location_name'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $lat = trim((string) ($_POST['lat'] ?? ''));
        $lng = trim((string) ($_POST['lng'] ?? ''));
        $googleMapsUrl = trim((string) ($_POST['google_maps_url'] ?? ''));
        $maxPlayers = (int) ($_POST['max_players'] ?? 0);
        $privacy = trim((string) ($_POST['privacy'] ?? 'public'));
        $description = trim((string) ($_POST['description'] ?? ''));
        $price = trim((string) ($_POST['price'] ?? ''));
        $rulesText = trim((string) ($_POST['rules_text'] ?? ''));

        $result = $inviteService->createInvite(
            (int) $currentUser['id'],
            [
                'sport' => $sport,
                'sport_custom' => $sportCustom,
                'level' => $level,
                'starts_at' => $startsAt,
                'location_name' => $locationName,
                'address' => $address,
                'lat' => $lat,
                'lng' => $lng,
                'max_players' => $maxPlayers,
                'privacy' => $privacy,
                'description' => $description,
                'price' => $price,
                'rules_text' => $rulesText,
            ]
        );

        if ($result['success']) {
            set_flash('success', 'Convite publicado com sucesso.');
            redirect('invite.php?id=' . (string) $result['invite_id']);
        }

        $errors = $result['errors'];
    }
}

$title = 'Criar Convite';
require __DIR__ . '/templates/header.php';
?>
<section class="card card-soft">
    <h1>Novo Convite</h1>
    <p class="muted">Digite o nome do local e selecione a sugestão para preencher o endereço automaticamente.</p>

    <?php if ($errors !== []): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e((string) $error); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?php echo e(url('create_invite.php')); ?>" class="grid grid-2" id="create-invite-form">
        <?php echo csrf_field(); ?>

        <div>
            <label for="sport">Esporte</label>
            <select id="sport" name="sport" required>
                <option value="">Selecione</option>
                <?php foreach (App\Services\InviteService::allowedSports() as $allowedSport): ?>
                    <option value="<?php echo e($allowedSport); ?>" <?php echo $sport === $allowedSport ? 'selected' : ''; ?>>
                        <?php echo e($allowedSport); ?>
                    </option>
                <?php endforeach; ?>
                <option value="outros" <?php echo $sport === 'outros' ? 'selected' : ''; ?>>Outros</option>
            </select>
            <div id="sport-custom-wrap" hidden style="margin-top: 10px;">
                <label for="sport_custom">Qual esporte?</label>
                <input
                    id="sport_custom"
                    type="text"
                    name="sport_custom"
                    maxlength="60"
                    value="<?php echo e($sportCustom); ?>"
                    placeholder="Digite o nome do esporte"
                >
            </div>
        </div>

        <div>
            <label for="level">Nível</label>
            <select id="level" name="level" required>
                <?php foreach (App\Services\InviteService::allowedLevels() as $allowedLevel): ?>
                    <option value="<?php echo e($allowedLevel); ?>" <?php echo $level === $allowedLevel ? 'selected' : ''; ?>>
                        <?php
                        echo e(match ($allowedLevel) {
                            'iniciante' => 'Iniciante',
                            'intermediario' => 'Intermediário',
                            'avancado' => 'Avançado',
                            default => ucfirst($allowedLevel),
                        });
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="starts_at">Data e hora</label>
            <input id="starts_at" type="datetime-local" name="starts_at" required value="<?php echo e($startsAt); ?>">
        </div>

        <div style="grid-column: 1 / -1;">
            <label for="location_name">Nome do local</label>
            <div class="location-input-row">
                <div class="autocomplete-wrap">
                    <input
                        id="location_name"
                        type="text"
                        name="location_name"
                        required
                        value="<?php echo e($locationName); ?>"
                        placeholder="Ex: Praia de Belas - Quadra 2"
                        autocomplete="off"
                    >
                    <div id="location-suggestions" class="autocomplete-list" hidden></div>
                </div>
                <button type="button" id="open-location-in-maps-btn" class="btn btn-outline location-maps-btn" disabled>
                    Buscar no Maps
                </button>
            </div>
        </div>

        <div style="grid-column: 1 / -1;">
            <label for="google_maps_url">Link da localização no Google Maps</label>
            <input
                id="google_maps_url"
                type="text"
                name="google_maps_url"
                value="<?php echo e($googleMapsUrl); ?>"
            >
            <p class="muted" style="margin-top: 8px;">
                Ao colar o link, exibimos o mapa abaixo e sincronizamos endereço/local automaticamente.
            </p>
        </div>

        <div style="grid-column: 1 / -1;" id="map-preview-shell" hidden>
            <div class="map-preview-frame-wrap">
                <div class="map-preview-head">
                    <strong>Pré-visualização do mapa</strong>
                    <span class="muted" id="map-preview-caption"></span>
                </div>
                <iframe
                    id="map-preview-frame"
                    title="Mapa da localização selecionada"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
                <div class="map-nav-actions">
                    <button type="button" id="route-picker-btn" class="btn" disabled>Ir para o local</button>
                </div>
            </div>
        </div>

        <div id="route-modal" class="route-modal" hidden>
            <div class="route-modal-backdrop" data-close-route-modal></div>
            <div class="route-modal-card" role="dialog" aria-modal="true" aria-labelledby="route-modal-title">
                <button type="button" id="route-modal-close" class="route-modal-close" data-close-route-modal aria-label="Fechar">x</button>
                <h3 id="route-modal-title">Escolha o aplicativo de navegação</h3>
                <p class="muted">Selecione como deseja ir para o local deste convite.</p>
                <div class="route-modal-actions">
                    <a id="go-google-btn" href="#" target="_blank" rel="noopener noreferrer" class="route-option route-option-google">
                        <strong>Google Maps</strong>
                        <span>Abrir rota no Maps</span>
                    </a>
                    <a id="go-waze-btn" href="#" target="_blank" rel="noopener noreferrer" class="route-option route-option-waze">
                        <strong>Waze</strong>
                        <span>Abrir rota no Waze</span>
                    </a>
                </div>
            </div>
        </div>

        <input type="hidden" id="address" name="address" value="<?php echo e($address); ?>">
        <input type="hidden" id="lat" name="lat" value="<?php echo e($lat); ?>">
        <input type="hidden" id="lng" name="lng" value="<?php echo e($lng); ?>">

        <div>
            <label for="max_players">Número máximo de jogadores</label>
            <input id="max_players" type="number" name="max_players" min="2" max="40" required value="<?php echo e((string) $maxPlayers); ?>">
        </div>

        <div>
            <label for="privacy">Privacidade</label>
            <select id="privacy" name="privacy" required>
                <?php foreach (App\Services\InviteService::allowedPrivacy() as $privacyOption): ?>
                    <option value="<?php echo e($privacyOption); ?>" <?php echo $privacy === $privacyOption ? 'selected' : ''; ?>>
                        <?php echo e($privacyOption === 'public' ? 'Público' : 'Privado'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="price_display">Preço (opcional)</label>
            <input id="price_display" type="text" inputmode="numeric" value="" placeholder="R$ 0,00" autocomplete="off">
            <input id="price" type="hidden" name="price" value="<?php echo e($price); ?>">
        </div>

        <div style="grid-column: 1 / -1;">
            <label for="description">Descrição (opcional)</label>
            <textarea id="description" name="description" rows="3"><?php echo e($description); ?></textarea>
        </div>

        <div style="grid-column: 1 / -1;">
            <label for="rules_text">Regras (opcional)</label>
            <textarea id="rules_text" name="rules_text" rows="3"><?php echo e($rulesText); ?></textarea>
        </div>

        <div style="grid-column: 1 / -1;">
            <button type="submit">Publicar convite</button>
        </div>
    </form>
</section>

<style>
.location-input-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: stretch;
}
.location-input-row .autocomplete-wrap {
    min-width: 0;
}
.location-maps-btn {
    width: auto;
    min-width: 160px;
    align-self: end;
}
.location-maps-btn[disabled] {
    background: #b4c5d1;
    color: #f4f8fb;
    border-color: #b4c5d1;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}
.autocomplete-wrap {
    position: relative;
}
.autocomplete-list {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 30;
    background: #fff;
    border: 1px solid #c9d8df;
    border-radius: 12px;
    box-shadow: 0 12px 25px rgba(16, 33, 45, 0.15);
    max-height: 250px;
    overflow-y: auto;
}
.autocomplete-item {
    border: none;
    border-bottom: 1px solid #ecf1f4;
    background: #fff;
    width: 100%;
    text-align: left;
    padding: 10px 12px;
    cursor: pointer;
    color: #183043;
}
.autocomplete-item:last-child {
    border-bottom: none;
}
.autocomplete-item:hover {
    background: #f1f8f7;
}
.autocomplete-secondary {
    display: block;
    font-size: 12px;
    color: #678090;
    margin-top: 4px;
}
.map-preview-frame-wrap {
    border: 1px solid #c9d8df;
    border-radius: 16px;
    overflow: hidden;
    background: linear-gradient(180deg, #fafdff 0%, #f2f7fa 100%);
    box-shadow: 0 10px 24px rgba(18, 41, 57, 0.12);
}
.map-preview-head {
    padding: 10px 12px;
    border-bottom: 1px solid #d9e5ec;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
#map-preview-frame {
    width: 100%;
    min-height: 320px;
    border: none;
    display: block;
}
#map-preview-shell {
    margin-bottom: 12px;
}
.map-nav-actions {
    border-top: 1px solid #d9e5ec;
    padding: 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.map-nav-actions .btn {
    width: auto;
}
#route-picker-btn[disabled] {
    background: #b4c5d1;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}
.route-modal {
    position: fixed;
    inset: 0;
    z-index: 120;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.route-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(10, 25, 37, 0.58);
    backdrop-filter: blur(2px);
}
.route-modal-card {
    position: relative;
    z-index: 1;
    width: min(640px, 100%);
    background: linear-gradient(165deg, #ffffff 0%, #f7fbfe 100%);
    border: 1px solid #c8dae5;
    border-radius: 22px;
    box-shadow: 0 30px 70px rgba(10, 30, 46, 0.28);
    padding: 24px;
}
.route-modal-card h3 {
    margin: 0 0 8px;
    font-size: clamp(23px, 3vw, 30px);
}
.route-modal-close {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 40px;
    height: 40px;
    border-radius: 999px;
    padding: 0;
    border: 1px solid #cad9e3;
    background: #f3f8fc;
    color: #20435c;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}
.route-modal-actions {
    margin-top: 16px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
}
.route-option {
    text-decoration: none;
    border-radius: 16px;
    border: 1px solid #c7d8e3;
    padding: 16px;
    background: #fff;
    color: #113044;
    display: block;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.route-option strong {
    display: block;
    font-size: 18px;
    margin-bottom: 4px;
}
.route-option span {
    color: #496176;
    font-weight: 500;
}
.route-option:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(16, 44, 66, 0.15);
}
.route-option-google:hover {
    border-color: #287be5;
}
.route-option-waze:hover {
    border-color: #00afec;
}
body.route-modal-open {
    overflow: hidden;
}
.route-modal[hidden] {
    display: none !important;
}
@media (max-width: 900px) {
    #map-preview-frame {
        min-height: 260px;
    }
}
@media (max-width: 680px) {
    .location-input-row {
        grid-template-columns: 1fr;
    }
    .location-maps-btn {
        width: 100%;
        min-width: 0;
    }
    .map-nav-actions .btn {
        width: 100%;
    }
    .route-modal {
        padding: 12px;
    }
    .route-modal-card {
        padding: 16px;
        border-radius: 16px;
    }
    .route-modal-actions {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 480px) {
    #map-preview-frame {
        min-height: 220px;
    }
    .route-option {
        padding: 12px;
    }
    .route-option strong {
        font-size: 17px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('create-invite-form');
    var sportSelect = document.getElementById('sport');
    var sportCustomWrap = document.getElementById('sport-custom-wrap');
    var sportCustomInput = document.getElementById('sport_custom');
    var locationInput = document.getElementById('location_name');
    var addressInput = document.getElementById('address');
    var latInput = document.getElementById('lat');
    var lngInput = document.getElementById('lng');
    var googleMapsUrlInput = document.getElementById('google_maps_url');
    var selectedAddress = document.getElementById('selected-address');
    var suggestions = document.getElementById('location-suggestions');
    var mapPreviewShell = document.getElementById('map-preview-shell');
    var mapPreviewFrame = document.getElementById('map-preview-frame');
    var mapPreviewCaption = document.getElementById('map-preview-caption');
    var openLocationInMapsBtn = document.getElementById('open-location-in-maps-btn');
    var routePickerBtn = document.getElementById('route-picker-btn');
    var routeModal = document.getElementById('route-modal');
    var goGoogleBtn = document.getElementById('go-google-btn');
    var goWazeBtn = document.getElementById('go-waze-btn');
    var priceDisplayInput = document.getElementById('price_display');
    var priceInput = document.getElementById('price');
    var mapsResolverEndpoint = <?php echo json_encode(url('api/v1/resolve_maps_url.php')); ?>;
    var debounceTimer = null;
    var mapsSyncVersion = 0;
    var currentItems = [];

    if (routeModal) {
        routeModal.hidden = true;
        routeModal.setAttribute('hidden', 'hidden');
        routeModal.setAttribute('aria-hidden', 'true');
    }
    document.body.classList.remove('route-modal-open');

    function hideSuggestions() {
        suggestions.hidden = true;
        suggestions.innerHTML = '';
    }

    function toggleSportCustomField() {
        if (!sportSelect || !sportCustomWrap || !sportCustomInput) {
            return;
        }

        var isOther = sportSelect.value === 'outros';
        sportCustomWrap.hidden = !isOther;
        sportCustomInput.required = isOther;
        sportCustomInput.disabled = !isOther;
        if (!isOther) {
            sportCustomInput.value = '';
            sportCustomInput.setCustomValidity('');
        }
    }

    function buildLocationSearchMapsUrl() {
        var query = locationInput ? locationInput.value.trim() : '';
        if (query === '' && addressInput) {
            query = addressInput.value.trim();
        }
        if (query === '') {
            return '';
        }

        return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query);
    }

    function updateLocationMapsButtonState() {
        if (!openLocationInMapsBtn) {
            return;
        }

        var href = buildLocationSearchMapsUrl();
        openLocationInMapsBtn.disabled = href === '';
        openLocationInMapsBtn.dataset.href = href;
    }

    function closeRoutePicker() {
        if (routeModal) {
            routeModal.hidden = true;
            routeModal.setAttribute('hidden', 'hidden');
            routeModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('route-modal-open');
        }
        if (routePickerBtn && !routePickerBtn.disabled) {
            try {
                routePickerBtn.focus({ preventScroll: true });
            } catch (error) {
                routePickerBtn.focus();
            }
        }
    }

    function openRoutePicker() {
        if (routeModal) {
            routeModal.hidden = false;
            routeModal.removeAttribute('hidden');
            routeModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('route-modal-open');
        }
    }

    function setRoutePickerEnabled(enabled) {
        if (routePickerBtn) {
            routePickerBtn.disabled = !enabled;
        }
        if (!enabled) {
            closeRoutePicker();
        }
    }

    function hideMapPreview() {
        if (!mapPreviewShell || !mapPreviewFrame) {
            return;
        }
        mapPreviewShell.hidden = true;
        mapPreviewFrame.removeAttribute('src');
        if (mapPreviewCaption) {
            mapPreviewCaption.textContent = '';
        }
        if (goGoogleBtn) {
            goGoogleBtn.setAttribute('href', '#');
        }
        if (goWazeBtn) {
            goWazeBtn.setAttribute('href', '#');
        }
        setRoutePickerEnabled(false);
    }

    function setNavigationLinks(destinationText, coords) {
        var hasCoords = !!coords && typeof coords.lat === 'number' && typeof coords.lng === 'number';
        var destination = String(destinationText || '').trim();
        if (!destination && hasCoords) {
            destination = String(coords.lat) + ',' + String(coords.lng);
        }

        if (!destination && !hasCoords) {
            if (goGoogleBtn) {
                goGoogleBtn.setAttribute('href', '#');
            }
            if (goWazeBtn) {
                goWazeBtn.setAttribute('href', '#');
            }
            setRoutePickerEnabled(false);
            return;
        }

        if (goGoogleBtn) {
            var googleHref = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(destination);
            goGoogleBtn.setAttribute('href', googleHref);
        }

        if (goWazeBtn) {
            var wazeHref = '';
            if (hasCoords) {
                wazeHref = 'https://www.waze.com/ul?ll='
                    + encodeURIComponent(coords.lat + ',' + coords.lng)
                    + '&navigate=yes';
            } else {
                wazeHref = 'https://www.waze.com/ul?q='
                    + encodeURIComponent(destination)
                    + '&navigate=yes';
            }
            goWazeBtn.setAttribute('href', wazeHref);
        }

        setRoutePickerEnabled(true);
    }

    function showMapPreview(src, caption, destinationText, coords) {
        if (!mapPreviewShell || !mapPreviewFrame) {
            return;
        }
        mapPreviewFrame.src = src;
        mapPreviewShell.hidden = false;
        if (mapPreviewCaption) {
            mapPreviewCaption.textContent = caption || '';
        }
        setNavigationLinks(destinationText || caption || '', coords || null);
    }

    function normalizePotentialUrl(raw) {
        var text = String(raw || '').trim();
        if (text === '') {
            return '';
        }

        if (!/^https?:\/\//i.test(text) && /^[a-z0-9.-]+\.[a-z]{2,}(\/|$)/i.test(text)) {
            return 'https://' + text;
        }

        return text;
    }

    function isGoogleShortHost(host) {
        var normalized = String(host || '').toLowerCase();
        return normalized === 'maps.app.goo.gl'
            || normalized.endsWith('.maps.app.goo.gl')
            || normalized === 'goo.gl'
            || normalized.endsWith('.goo.gl');
    }

    async function resolveGoogleMapsUrl(raw) {
        var normalized = normalizePotentialUrl(raw);
        if (normalized === '') {
            return '';
        }

        try {
            var parsedUrl = new URL(normalized);
            if (!isGoogleShortHost(parsedUrl.host)) {
                return normalized;
            }
        } catch (error) {
            return normalized;
        }

        if (typeof mapsResolverEndpoint !== 'string' || mapsResolverEndpoint === '') {
            return normalized;
        }

        try {
            var response = await fetch(
                mapsResolverEndpoint + '?url=' + encodeURIComponent(normalized),
                {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                }
            );

            if (!response.ok) {
                return normalized;
            }

            var payload = await response.json();
            if (payload && payload.success && typeof payload.final_url === 'string' && payload.final_url.trim() !== '') {
                return payload.final_url.trim();
            }
        } catch (error) {
            return normalized;
        }

        return normalized;
    }

    function normalizeMoneyValue(raw) {
        var text = String(raw || '').trim();
        if (text === '') {
            return '';
        }

        text = text.replace(/[^\d,.\-]/g, '');
        if (text === '' || text === '-' || text === ',' || text === '.') {
            return '';
        }

        var hasComma = text.indexOf(',') !== -1;
        var hasDot = text.indexOf('.') !== -1;
        if (hasComma && hasDot) {
            if (text.lastIndexOf(',') > text.lastIndexOf('.')) {
                text = text.replace(/\./g, '').replace(',', '.');
            } else {
                text = text.replace(/,/g, '');
            }
        } else if (hasComma) {
            text = text.replace(',', '.');
        }

        var number = Number(text);
        if (!Number.isFinite(number)) {
            return '';
        }

        if (number < 0) {
            return '';
        }

        return number.toFixed(2);
    }

    function formatBrl(numberValue) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(numberValue);
    }

    function applyPriceMaskFromDigits(digitsRaw) {
        var digits = String(digitsRaw || '').replace(/\D/g, '');
        if (digits === '') {
            return null;
        }

        digits = digits.replace(/^0+/, '');
        if (digits === '') {
            digits = '0';
        }

        if (digits.length > 12) {
            digits = digits.slice(0, 12);
        }

        var amount = Number(digits) / 100;
        if (!Number.isFinite(amount)) {
            return null;
        }

        return {
            amount: amount,
            formatted: formatBrl(amount)
        };
    }

    function syncPriceDisplayFromHidden() {
        if (!priceDisplayInput || !priceInput) {
            return;
        }

        var normalized = normalizeMoneyValue(priceInput.value);
        if (normalized === '') {
            priceDisplayInput.value = '';
            priceInput.value = '';
            return;
        }

        priceInput.value = normalized;
        priceDisplayInput.value = formatBrl(Number(normalized));
    }

    function parseCoordinates(raw) {
        var match = String(raw || '').match(/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/);
        if (!match) {
            return null;
        }

        var lat = parseFloat(match[1]);
        var lng = parseFloat(match[2]);

        if (Number.isNaN(lat) || Number.isNaN(lng)) {
            return null;
        }
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            return null;
        }

        return { lat: lat, lng: lng };
    }

    function parseGoogleDataCoordinates(raw) {
        var text = String(raw || '');
        if (text === '') {
            return null;
        }

        var pair = text.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/);
        if (!pair) {
            pair = text.match(/!4d(-?\d+(?:\.\d+)?)!3d(-?\d+(?:\.\d+)?)/);
            if (!pair) {
                return null;
            }

            return parseCoordinates(pair[2] + ',' + pair[1]);
        }

        return parseCoordinates(pair[1] + ',' + pair[2]);
    }

    function parseGoogleMapsInput(raw) {
        var text = String(raw || '').trim();
        if (text === '') {
            return null;
        }

        var coords = parseGoogleDataCoordinates(text);
        if (!coords) {
            coords = parseCoordinates(text);
        }
        var queryText = '';
        var normalized = normalizePotentialUrl(text);

        try {
            var url = new URL(normalized);
            if (!coords) {
                coords = parseGoogleDataCoordinates(url.pathname + url.search + url.hash);
            }
            if (!coords) {
                coords = parseCoordinates(url.pathname);
            }

            var params = ['q', 'query', 'll', 'center'];
            for (var i = 0; i < params.length; i++) {
                var value = url.searchParams.get(params[i]);
                if (!value) {
                    continue;
                }

                if (!coords) {
                    coords = parseCoordinates(value);
                }
                if (!queryText) {
                    queryText = value;
                }
            }

            if (!queryText && url.pathname.indexOf('/place/') !== -1) {
                var placePath = decodeURIComponent(url.pathname.split('/place/')[1] || '');
                var placeName = placePath.split('/')[0].replace(/\+/g, ' ').trim();
                if (placeName !== '') {
                    queryText = placeName;
                }
            }
        } catch (error) {
            if (!coords) {
                return null;
            }
        }

        if (!coords && queryText === '') {
            return null;
        }

        if (coords) {
            return {
                coords: coords,
                queryText: queryText,
                embedSrc: 'https://maps.google.com/maps?q='
                    + encodeURIComponent(coords.lat + ',' + coords.lng)
                    + '&z=16&output=embed',
                caption: coords.lat.toFixed(6) + ', ' + coords.lng.toFixed(6)
            };
        }

        return {
            coords: null,
            queryText: queryText,
            embedSrc: 'https://maps.google.com/maps?q=' + encodeURIComponent(queryText) + '&z=16&output=embed',
            caption: queryText
        };
    }

    function setAddressText(text) {
        if (selectedAddress) {
            selectedAddress.textContent = text !== ''
                ? 'Endereço selecionado: ' + text
                : 'Nenhum endereço selecionado.';
        }
        updateLocationMapsButtonState();
    }

    function selectItem(item) {
        var shortName = item.name ? item.name : (item.display_name || '').split(',')[0];
        locationInput.value = shortName || locationInput.value;
        addressInput.value = item.display_name || shortName || locationInput.value;
        latInput.value = item.lat || '';
        lngInput.value = item.lon || '';
        setAddressText(addressInput.value);
        if (item.lat && item.lon) {
            showMapPreview(
                'https://maps.google.com/maps?q=' + encodeURIComponent(item.lat + ',' + item.lon) + '&z=16&output=embed',
                addressInput.value,
                addressInput.value,
                { lat: parseFloat(item.lat), lng: parseFloat(item.lon) }
            );
        }
        hideSuggestions();
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

    async function syncFromGoogleMapsLink() {
        var rawValue = googleMapsUrlInput ? googleMapsUrlInput.value : '';
        var syncVersion = mapsSyncVersion + 1;
        mapsSyncVersion = syncVersion;

        var resolvedValue = await resolveGoogleMapsUrl(rawValue);
        if (syncVersion !== mapsSyncVersion) {
            return;
        }

        var parsed = parseGoogleMapsInput(resolvedValue || rawValue);
        if (!parsed) {
            hideMapPreview();
            return;
        }

        showMapPreview(
            parsed.embedSrc,
            parsed.caption,
            parsed.queryText || parsed.caption || '',
            parsed.coords || null
        );

        if (parsed.coords) {
            latInput.value = parsed.coords.lat.toFixed(7);
            lngInput.value = parsed.coords.lng.toFixed(7);

            try {
                var resolvedAddress = await reverseGeocode(latInput.value, lngInput.value);
                addressInput.value = resolvedAddress;
                if (locationInput.value.trim() === '' || locationInput.value === parsed.queryText) {
                    locationInput.value = resolvedAddress.split(',')[0].trim();
                }
                setAddressText(addressInput.value);
                if (mapPreviewCaption) {
                    mapPreviewCaption.textContent = resolvedAddress;
                }
                showMapPreview(
                    parsed.embedSrc,
                    resolvedAddress,
                    resolvedAddress,
                    parsed.coords
                );
            } catch (error) {
                if (addressInput.value.trim() === '' && parsed.queryText) {
                    addressInput.value = parsed.queryText;
                    setAddressText(addressInput.value);
                }
                showMapPreview(
                    parsed.embedSrc,
                    parsed.caption,
                    addressInput.value || parsed.queryText || (parsed.coords.lat + ',' + parsed.coords.lng),
                    parsed.coords
                );
            }
        } else if (parsed.queryText) {
            if (locationInput.value.trim() === '') {
                locationInput.value = parsed.queryText;
            }
            if (addressInput.value.trim() === '') {
                addressInput.value = parsed.queryText;
                setAddressText(addressInput.value);
            }
            latInput.value = '';
            lngInput.value = '';
            showMapPreview(
                parsed.embedSrc,
                parsed.queryText,
                addressInput.value || parsed.queryText,
                null
            );
        }
    }

    function renderSuggestions(items) {
        currentItems = items;
        if (!items.length) {
            hideSuggestions();
            return;
        }

        suggestions.innerHTML = '';
        items.forEach(function (item, index) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'autocomplete-item';
            button.dataset.index = String(index);

            var main = document.createElement('span');
            main.textContent = item.name ? item.name : (item.display_name || '');
            button.appendChild(main);

            var secondary = document.createElement('span');
            secondary.className = 'autocomplete-secondary';
            secondary.textContent = item.display_name || '';
            button.appendChild(secondary);

            suggestions.appendChild(button);
        });

        suggestions.hidden = false;
    }

    async function fetchPlaces(query) {
        var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=6&q='
            + encodeURIComponent(query);

        var response = await fetch(url, {
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) {
            throw new Error('search_failed');
        }

        var data = await response.json();
        return Array.isArray(data) ? data : [];
    }

    if (priceDisplayInput && priceInput) {
        syncPriceDisplayFromHidden();

        priceDisplayInput.addEventListener('input', function () {
            var masked = applyPriceMaskFromDigits(priceDisplayInput.value);
            if (!masked) {
                priceDisplayInput.value = '';
                priceInput.value = '';
                return;
            }

            priceDisplayInput.value = masked.formatted;
            priceInput.value = masked.amount.toFixed(2);
        });

        priceDisplayInput.addEventListener('blur', function () {
            syncPriceDisplayFromHidden();
        });
    }

    if (openLocationInMapsBtn) {
        openLocationInMapsBtn.addEventListener('click', function (event) {
            event.preventDefault();
            var href = openLocationInMapsBtn.dataset.href || buildLocationSearchMapsUrl();
            if (!href) {
                return;
            }
            window.open(href, '_blank', 'noopener,noreferrer');
        });
    }

    if (sportSelect) {
        sportSelect.addEventListener('change', function () {
            toggleSportCustomField();
        });
    }

    if (sportCustomInput) {
        sportCustomInput.addEventListener('input', function () {
            sportCustomInput.setCustomValidity('');
        });
    }

    locationInput.addEventListener('input', function () {
        var query = locationInput.value.trim();
        addressInput.value = query;
        if (!googleMapsUrlInput || googleMapsUrlInput.value.trim() === '') {
            latInput.value = '';
            lngInput.value = '';
        }
        setAddressText(addressInput.value);
        updateLocationMapsButtonState();

        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }

        if (query.length < 3) {
            hideSuggestions();
            return;
        }

        debounceTimer = window.setTimeout(async function () {
            try {
                var places = await fetchPlaces(query);
                renderSuggestions(places);
            } catch (error) {
                hideSuggestions();
            }
        }, 350);
    });

    suggestions.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        var button = target.closest('.autocomplete-item');
        if (!(button instanceof HTMLElement)) {
            return;
        }

        var index = Number(button.dataset.index || '-1');
        if (index < 0 || index >= currentItems.length) {
            return;
        }

        selectItem(currentItems[index]);
    });

    if (routePickerBtn) {
        routePickerBtn.addEventListener('click', function () {
            if (routePickerBtn.disabled || !routeModal) {
                return;
            }

            openRoutePicker();
        });
    }

    if (routeModal) {
        routeModal.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (target.closest('[data-close-route-modal]')) {
                event.preventDefault();
                event.stopPropagation();
                closeRoutePicker();
                return;
            }

            if (target.closest('a')) {
                closeRoutePicker();
            }
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof Node)) {
            return;
        }

        if (!suggestions.contains(target) && target !== locationInput) {
            hideSuggestions();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && routeModal && !routeModal.hidden) {
            closeRoutePicker();
        }
    });

    window.addEventListener('pageshow', function () {
        closeRoutePicker();
    });

    if (googleMapsUrlInput) {
        googleMapsUrlInput.addEventListener('change', function () {
            syncFromGoogleMapsLink();
        });
        googleMapsUrlInput.addEventListener('paste', function () {
            window.setTimeout(syncFromGoogleMapsLink, 0);
        });
    }

    form.addEventListener('submit', function () {
        if (sportSelect && sportCustomInput && sportSelect.value === 'outros') {
            if (sportCustomInput.value.trim() === '') {
                sportCustomInput.setCustomValidity('Informe o esporte em "Outros".');
                sportCustomInput.reportValidity();
                return;
            }
        }

        if (addressInput.value.trim() === '') {
            addressInput.value = locationInput.value.trim();
            setAddressText(addressInput.value);
        }

        if (priceDisplayInput && priceInput) {
            var normalizedPrice = normalizeMoneyValue(priceInput.value || priceDisplayInput.value);
            priceInput.value = normalizedPrice;
            if (normalizedPrice !== '') {
                priceDisplayInput.value = formatBrl(Number(normalizedPrice));
            } else {
                priceDisplayInput.value = '';
            }
        }
    });

    setRoutePickerEnabled(false);
    toggleSportCustomField();
    updateLocationMapsButtonState();
    syncPriceDisplayFromHidden();
    syncFromGoogleMapsLink();
});
</script>
<?php
require __DIR__ . '/templates/footer.php';
