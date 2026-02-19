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

$errors = [];
$sport = '';
$sportCustom = '';
$level = 'intermediario';
$startsAt = (new DateTimeImmutable('+1 day'))->format('Y-m-d\TH:i');
$locationName = '';
$address = '';
$lat = '';
$lng = '';
$maxPlayers = 4;
$privacy = 'public';
$description = '';
$price = '';
$rulesText = '';

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Token CSRF invalido.';
    } else {
        $sport = trim((string) ($_POST['sport'] ?? ''));
        $sportCustom = trim((string) ($_POST['sport_custom'] ?? ''));
        $level = trim((string) ($_POST['level'] ?? 'intermediario'));
        $startsAt = trim((string) ($_POST['starts_at'] ?? ''));
        $locationName = trim((string) ($_POST['location_name'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $lat = trim((string) ($_POST['lat'] ?? ''));
        $lng = trim((string) ($_POST['lng'] ?? ''));
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
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>

<section class="card card-soft">
    <h1>Novo Convite</h1>
    <p class="muted">Digite o local e selecione uma sugestao para preencher endereco e coordenadas automaticamente.</p>

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
            <label for="level">Nivel</label>
            <select id="level" name="level" required>
                <?php foreach (App\Services\InviteService::allowedLevels() as $allowedLevel): ?>
                    <option value="<?php echo e($allowedLevel); ?>" <?php echo $level === $allowedLevel ? 'selected' : ''; ?>>
                        <?php
                        echo e(match ($allowedLevel) {
                            'iniciante' => 'Iniciante',
                            'intermediario' => 'Intermediario',
                            'avancado' => 'Avancado',
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
            <label for="location_name">Local</label>
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
            <p class="muted" style="margin-top: 8px;">
                Digite ao menos 3 caracteres e escolha um item da lista.
            </p>
            <p id="location-validation" class="location-validation" hidden></p>
        </div>

        <input type="hidden" id="address" name="address" value="<?php echo e($address); ?>">
        <input type="hidden" id="lat" name="lat" value="<?php echo e($lat); ?>">
        <input type="hidden" id="lng" name="lng" value="<?php echo e($lng); ?>">

        <div style="grid-column: 1 / -1;" id="location-map-shell" <?php echo ($lat !== '' && $lng !== '') ? '' : 'hidden'; ?>>
            <label>Mapa do local</label>
            <div id="location-map"></div>
            <p class="muted" id="location-selected-text" style="margin-top: 8px;">
                <?php
                if ($address !== '') {
                    echo e($address);
                } else {
                    echo 'Nenhum local selecionado.';
                }
                ?>
            </p>
        </div>

        <div>
            <label for="max_players">Numero maximo de jogadores</label>
            <input id="max_players" type="number" name="max_players" min="2" max="40" required value="<?php echo e((string) $maxPlayers); ?>">
        </div>

        <div>
            <label for="privacy">Privacidade</label>
            <select id="privacy" name="privacy" required>
                <?php foreach (App\Services\InviteService::allowedPrivacy() as $privacyOption): ?>
                    <option value="<?php echo e($privacyOption); ?>" <?php echo $privacy === $privacyOption ? 'selected' : ''; ?>>
                        <?php echo e($privacyOption === 'public' ? 'Publico' : 'Privado'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="price_display">Preco (opcional)</label>
            <input id="price_display" type="text" inputmode="numeric" value="" placeholder="R$ 0,00" autocomplete="off">
            <input id="price" type="hidden" name="price" value="<?php echo e($price); ?>">
        </div>

        <div style="grid-column: 1 / -1;">
            <label for="description">Descricao (opcional)</label>
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
.autocomplete-wrap {
    position: relative;
}
.autocomplete-list {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 40;
    background: #f8fdff;
    border: 1px solid #b8ccda;
    border-radius: 12px;
    box-shadow: 0 12px 28px rgba(14, 48, 68, 0.16);
    max-height: 280px;
    overflow-y: auto;
}
.autocomplete-item {
    width: 100%;
    text-align: left;
    border: 0;
    border-bottom: 1px solid #d7e5ee;
    background: transparent;
    padding: 10px 12px;
    cursor: pointer;
    color: #123047;
}
.autocomplete-item:last-child {
    border-bottom: 0;
}
.autocomplete-item:hover {
    background: #e9f4fb;
}
.autocomplete-item-title {
    display: block;
    font-weight: 700;
    font-size: 0.96rem;
}
.autocomplete-item-subtitle {
    display: block;
    margin-top: 2px;
    font-size: 0.88rem;
    color: #4e6679;
}
.autocomplete-item--meta {
    cursor: default;
    font-weight: 600;
}
.location-validation {
    margin-top: 8px;
    color: #b42318;
    font-weight: 700;
    font-size: 0.92rem;
}
#location-map-shell {
    padding-top: 4px;
}
#location-map {
    width: 100%;
    min-height: 220px;
    border-radius: 14px;
    border: 1px solid #c4d8e6;
    overflow: hidden;
}
@media (max-width: 640px) {
    .autocomplete-item {
        padding: 12px;
    }
    #location-map {
        min-height: 200px;
    }
}
html[data-theme='dark'] .autocomplete-list {
    background: #12273b;
    border-color: #31506a;
}
html[data-theme='dark'] .autocomplete-item {
    color: #e5f1f8;
    border-bottom-color: #234259;
}
html[data-theme='dark'] .autocomplete-item-subtitle {
    color: #9db5c8;
}
html[data-theme='dark'] .autocomplete-item:hover {
    background: #1a3850;
}
html[data-theme='dark'] #location-map {
    border-color: #34546e;
}
</style>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('create-invite-form');
    var sportSelect = document.getElementById('sport');
    var sportCustomWrap = document.getElementById('sport-custom-wrap');
    var sportCustomInput = document.getElementById('sport_custom');
    var locationInput = document.getElementById('location_name');
    var suggestions = document.getElementById('location-suggestions');
    var addressInput = document.getElementById('address');
    var latInput = document.getElementById('lat');
    var lngInput = document.getElementById('lng');
    var locationValidation = document.getElementById('location-validation');
    var mapShell = document.getElementById('location-map-shell');
    var mapNode = document.getElementById('location-map');
    var locationSelectedText = document.getElementById('location-selected-text');
    var priceDisplayInput = document.getElementById('price_display');
    var priceInput = document.getElementById('price');
    var geocodeEndpoint = <?php echo json_encode(url('api/geocode.php')); ?>;

    var debounceTimer = null;
    var requestVersion = 0;
    var currentItems = [];
    var selectedSnapshot = '';
    var map = null;
    var marker = null;

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

    function hideSuggestions() {
        suggestions.hidden = true;
        suggestions.innerHTML = '';
        currentItems = [];
    }

    function setValidationMessage(message) {
        if (!locationValidation) {
            return;
        }
        if (message === '') {
            locationValidation.hidden = true;
            locationValidation.textContent = '';
            return;
        }
        locationValidation.hidden = false;
        locationValidation.textContent = message;
    }

    function setLocationText(message) {
        if (!locationSelectedText) {
            return;
        }
        locationSelectedText.textContent = message;
    }

    function snapshotOfSelection() {
        return [addressInput.value.trim(), latInput.value.trim(), lngInput.value.trim()].join('|');
    }

    function toNumber(rawValue) {
        var value = String(rawValue || '').trim().replace(',', '.');
        if (value === '') {
            return null;
        }
        var numeric = Number(value);
        return Number.isFinite(numeric) ? numeric : null;
    }

    function ensureMap(lat, lng, label) {
        if (!mapNode || typeof window.L === 'undefined') {
            return;
        }

        if (mapShell) {
            mapShell.hidden = false;
        }

        if (!map) {
            map = L.map(mapNode, { zoomControl: true });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);
        }

        var point = [lat, lng];
        if (!marker) {
            marker = L.marker(point).addTo(map);
        } else {
            marker.setLatLng(point);
        }

        map.setView(point, 16);
        if (label !== '') {
            marker.bindPopup(label).openPopup();
        }

        window.setTimeout(function () {
            map.invalidateSize();
        }, 120);
    }

    function clearMap() {
        if (mapShell) {
            mapShell.hidden = true;
        }
    }

    function invalidateLocationSelection() {
        addressInput.value = '';
        latInput.value = '';
        lngInput.value = '';
        selectedSnapshot = '';
        clearMap();
        setLocationText('Nenhum local selecionado.');
    }

    function buildItemRow(item, index) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'autocomplete-item';
        button.dataset.index = String(index);

        var title = document.createElement('span');
        title.className = 'autocomplete-item-title';
        title.textContent = (item.display_name || '').split(',')[0] || item.display_name || '';
        button.appendChild(title);

        var subtitle = document.createElement('span');
        subtitle.className = 'autocomplete-item-subtitle';
        subtitle.textContent = item.display_name || '';
        button.appendChild(subtitle);

        return button;
    }

    function renderMetaRow(text) {
        suggestions.hidden = false;
        suggestions.innerHTML = '';

        var row = document.createElement('div');
        row.className = 'autocomplete-item autocomplete-item--meta';
        row.textContent = text;
        suggestions.appendChild(row);
    }

    function renderSuggestions(items) {
        currentItems = items;
        suggestions.innerHTML = '';

        if (items.length === 0) {
            renderMetaRow('Nenhum local encontrado');
            return;
        }

        items.forEach(function (item, index) {
            suggestions.appendChild(buildItemRow(item, index));
        });
        suggestions.hidden = false;
    }

    async function fetchSuggestions(query, localVersion) {
        var response = await fetch(
            geocodeEndpoint + '?q=' + encodeURIComponent(query),
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }
        );

        if (!response.ok) {
            throw new Error('fetch_failed');
        }

        var payload = await response.json();
        if (localVersion !== requestVersion) {
            return null;
        }

        if (!payload || payload.success !== true || !Array.isArray(payload.items)) {
            return [];
        }

        return payload.items;
    }

    function applySuggestion(item) {
        var displayName = String(item.display_name || '').trim();
        var itemLat = String(item.lat || '').trim();
        var itemLon = String(item.lon || '').trim();
        if (displayName === '' || itemLat === '' || itemLon === '') {
            return;
        }

        var primary = displayName.split(',')[0].trim();
        locationInput.value = primary !== '' ? primary : displayName;
        addressInput.value = displayName;
        latInput.value = itemLat;
        lngInput.value = itemLon;
        selectedSnapshot = snapshotOfSelection();

        hideSuggestions();
        setValidationMessage('');
        setLocationText(displayName);

        var parsedLat = toNumber(itemLat);
        var parsedLng = toNumber(itemLon);
        if (parsedLat !== null && parsedLng !== null) {
            ensureMap(parsedLat, parsedLng, displayName);
        }
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
        if (!Number.isFinite(number) || number < 0) {
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

    if (sportSelect) {
        sportSelect.addEventListener('change', toggleSportCustomField);
    }
    if (sportCustomInput) {
        sportCustomInput.addEventListener('input', function () {
            sportCustomInput.setCustomValidity('');
        });
    }

    locationInput.addEventListener('input', function () {
        setValidationMessage('');

        var query = locationInput.value.trim();
        if (snapshotOfSelection() !== selectedSnapshot) {
            invalidateLocationSelection();
        }

        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }

        if (query.length < 3) {
            hideSuggestions();
            return;
        }

        renderMetaRow('Carregando...');
        var localVersion = requestVersion + 1;
        requestVersion = localVersion;
        debounceTimer = window.setTimeout(async function () {
            try {
                var items = await fetchSuggestions(query, localVersion);
                if (items === null || localVersion !== requestVersion) {
                    return;
                }
                renderSuggestions(items);
            } catch (error) {
                renderMetaRow('Erro ao buscar locais');
            }
        }, 500);
    });

    suggestions.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        var itemButton = target.closest('.autocomplete-item[data-index]');
        if (!(itemButton instanceof HTMLElement)) {
            return;
        }

        var index = Number(itemButton.dataset.index || '-1');
        if (index < 0 || index >= currentItems.length) {
            return;
        }

        applySuggestion(currentItems[index]);
    });

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof Node)) {
            return;
        }

        if (!suggestions.contains(target) && target !== locationInput) {
            hideSuggestions();
        }
    });

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

        priceDisplayInput.addEventListener('blur', syncPriceDisplayFromHidden);
    }

    form.addEventListener('submit', function (event) {
        if (sportSelect && sportCustomInput && sportSelect.value === 'outros') {
            if (sportCustomInput.value.trim() === '') {
                sportCustomInput.setCustomValidity('Informe o esporte em "Outros".');
                sportCustomInput.reportValidity();
                event.preventDefault();
                return;
            }
        }

        if (latInput.value.trim() === '' || lngInput.value.trim() === '') {
            setValidationMessage('Selecione um local da lista.');
            locationInput.focus();
            event.preventDefault();
            return;
        }

        if (addressInput.value.trim() === '') {
            addressInput.value = locationInput.value.trim();
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

    toggleSportCustomField();
    syncPriceDisplayFromHidden();

    var initialLat = toNumber(latInput.value);
    var initialLng = toNumber(lngInput.value);
    if (initialLat !== null && initialLng !== null) {
        ensureMap(initialLat, initialLng, addressInput.value.trim());
        setLocationText(addressInput.value.trim() !== '' ? addressInput.value.trim() : 'Local selecionado.');
        selectedSnapshot = snapshotOfSelection();
    } else {
        clearMap();
        setLocationText('Nenhum local selecionado.');
    }
});
</script>
<?php
require __DIR__ . '/templates/footer.php';
