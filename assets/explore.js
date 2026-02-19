document.addEventListener('DOMContentLoaded', function () {
    var locationButton = document.getElementById('use-location-btn');
    var latInput = document.getElementById('lat');
    var lngInput = document.getElementById('lng');
    var locationLabelInput = document.getElementById('location_label');
    var locationLabelNode = document.getElementById('location-active-label');
    var filterForm = document.getElementById('explore-filter-form');

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

                    if (latInput) {
                        latInput.value = lat;
                    }
                    if (lngInput) {
                        lngInput.value = lng;
                    }

                    try {
                        var label = await reverseGeocode(lat, lng);
                        if (locationLabelInput) {
                            locationLabelInput.value = label;
                        }
                    } catch (error) {
                        if (locationLabelInput) {
                            locationLabelInput.value = 'Localizacao detectada';
                        }
                    }

                    if (filterForm) {
                        filterForm.submit();
                    }
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

    if (!window.exploreConfig || !window.exploreConfig.showMap) {
        return;
    }

    var mapElement = document.getElementById('explore-map');
    if (!mapElement || typeof L === 'undefined') {
        return;
    }

    var markersNode = document.getElementById('explore-map-markers');
    var userLocationNode = document.getElementById('explore-map-user-location');
    var markers = [];
    var userLocation = null;
    var markerByInviteId = new Map();
    var cardNodes = Array.prototype.slice.call(document.querySelectorAll('.game-card[data-invite-id]'));

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function markerStyle(isActive) {
        return {
            radius: isActive ? 11 : 8,
            color: isActive ? '#d9600e' : '#0b7f6f',
            weight: isActive ? 2.5 : 2,
            fillColor: isActive ? '#f97316' : '#16b19d',
            fillOpacity: isActive ? 0.86 : 0.65
        };
    }

    function updateCardActive(inviteId) {
        cardNodes.forEach(function (card) {
            card.classList.toggle('is-map-active', card.dataset.inviteId === inviteId);
        });
    }

    function focusInvite(inviteId, options) {
        options = options || {};
        var id = String(inviteId || '');
        if (id === '') {
            return;
        }

        updateCardActive(id);

        markerByInviteId.forEach(function (marker) {
            marker.setStyle(markerStyle(false));
        });

        var marker = markerByInviteId.get(id);
        if (marker) {
            marker.setStyle(markerStyle(true));
            if (marker.bringToFront) {
                marker.bringToFront();
            }
            if (options.openPopup !== false) {
                marker.openPopup();
            }
            if (options.pan !== false) {
                map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 13), { duration: 0.35 });
            }
        }

        if (options.scrollCard) {
            var card = document.querySelector('.game-card[data-invite-id=\"' + id + '\"]');
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    if (markersNode && markersNode.textContent) {
        try {
            markers = JSON.parse(markersNode.textContent);
        } catch (error) {
            markers = [];
        }
    }
    if (userLocationNode && userLocationNode.textContent) {
        try {
            userLocation = JSON.parse(userLocationNode.textContent);
        } catch (error) {
            userLocation = null;
        }
    }

    var map = L.map(mapElement, { zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    var bounds = [];
    markers.forEach(function (item) {
        if (typeof item.lat !== 'number' || typeof item.lng !== 'number') {
            return;
        }

        var marker = L.circleMarker([item.lat, item.lng], markerStyle(false)).addTo(map);
        var popup = '<strong>' + escapeHtml(item.location || '') + '</strong>'
            + '<br>' + escapeHtml(item.sport || '')
            + '<br>' + escapeHtml(item.starts_at || '')
            + '<br>Vagas: ' + escapeHtml(item.players || 0) + '/' + escapeHtml(item.max_players || 0);
        marker.bindPopup(popup);

        var markerId = String(item.id || '');
        if (markerId !== '') {
            markerByInviteId.set(markerId, marker);
            marker.on('click', function () {
                focusInvite(markerId, { pan: false, openPopup: true, scrollCard: true });
            });
        }

        bounds.push([item.lat, item.lng]);
    });

    if (userLocation && typeof userLocation.lat === 'number' && typeof userLocation.lng === 'number') {
        L.circleMarker([userLocation.lat, userLocation.lng], {
            radius: 8,
            color: '#1b6fa9',
            weight: 2,
            fillColor: '#4ca5de',
            fillOpacity: 0.5
        }).addTo(map).bindPopup('Voce esta aqui');
        bounds.push([userLocation.lat, userLocation.lng]);
    }

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [26, 26], maxZoom: 14 });
    } else {
        map.setView([-30.0346, -51.2177], 12);
    }

    cardNodes.forEach(function (card) {
        card.addEventListener('click', function (event) {
            if (event.target.closest('a,button,input,form,select,label,textarea')) {
                return;
            }
            focusInvite(card.dataset.inviteId, { pan: true, openPopup: true });
        });
        card.addEventListener('mouseenter', function () {
            focusInvite(card.dataset.inviteId, { pan: false, openPopup: false });
        });
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-focus-invite]')).forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var inviteId = button.getAttribute('data-focus-invite') || '';
            focusInvite(inviteId, { pan: true, openPopup: true, scrollCard: true });
            if (window.innerWidth < 1060) {
                mapElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    });

    for (var i = 0; i < cardNodes.length; i++) {
        var cardId = cardNodes[i].dataset.inviteId;
        if (markerByInviteId.has(cardId)) {
            focusInvite(cardId, { pan: false, openPopup: false });
            break;
        }
    }
});
