@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">Program Location</h1>
        <p class="page-subtitle">GSM gateway inventory grouped by program location.</p>
    </div>
    <div class="toolbar">
        <div class="search-box">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="programLocationSearch" type="search" value="{{ $search }}" placeholder="Search Sites" aria-label="Search Sites" autocomplete="off">
        </div>
    </div>
</div>

<div class="loc-map-shell" id="locMapShell">
    <div class="loc-map-stage">
        <div id="programLocationMap" class="loc-map" role="application" aria-label="Program Location map"></div>
        <script type="application/json" id="programLocationSites">{!! json_encode($sites) !!}</script>
        <div class="loc-map-legend" aria-label="Map legend">
            <div class="loc-map-legend-item"><span class="loc-map-swatch is-on" aria-hidden="true"></span> Gateway Assigned</div>
            <div class="loc-map-legend-item"><span class="loc-map-swatch is-off" aria-hidden="true"></span> No Gateway Assigned</div>
        </div>
    </div>
    <aside class="loc-config-panel" id="locConfigPanel" hidden>
        <div class="loc-config-head">
            <h2 class="loc-config-title" id="locConfigTitle">GSM Gateway Configuration</h2>
            <button type="button" class="loc-config-close" id="locConfigClose" aria-label="Close">×</button>
        </div>
        <div class="loc-config-details">
            <div class="loc-config-name" id="locConfigName"></div>
            <div class="loc-config-address" id="locConfigAddress"></div>
            <div class="loc-popup-status" id="locConfigStatus"></div>
        </div>
        <form id="locConfigForm">
            <input type="hidden" name="slug" id="locConfigSlug">
            <div class="loc-config-options">
                <label class="loc-config-option">
                    <input type="radio" name="assigned" value="0">
                    <span>No Gateway Assigned</span>
                </label>
                <label class="loc-config-option">
                    <input type="radio" name="assigned" value="1">
                    <span>Assign GSM Gateway</span>
                </label>
            </div>
            <div class="loc-config-actions">
                <button type="submit" class="btn primary">Save</button>
                <button type="button" class="btn secondary" id="locConfigCancel">Cancel</button>
            </div>
        </form>
    </aside>
</div>
@endsection

@push('modals')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(function () {
    const root = document.getElementById('programLocationMap');
    const search = document.getElementById('programLocationSearch');
    const payload = document.getElementById('programLocationSites');
    if (!root) return;

    const sites = JSON.parse(payload ? payload.textContent : '[]');
    const canConfigure = @json($canConfigure ?? false);
    const statusUrlTemplate = @json($statusUpdateUrlTemplate ?? '');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const shell = document.getElementById('locMapShell');
    const panel = document.getElementById('locConfigPanel');
    const form = document.getElementById('locConfigForm');
    const slugEl = document.getElementById('locConfigSlug');
    const nameEl = document.getElementById('locConfigName');
    const addressEl = document.getElementById('locConfigAddress');
    const statusEl = document.getElementById('locConfigStatus');
    const MANILA_CENTER = [14.575, 121.055];
    const MANILA_ZOOM = 13;
    const labelDir = {
        alcar: 'left',
        ctn: 'top',
        estancia: 'right',
        scs: 'right',
        cg3: 'bottom',
        skyrise: 'left',
        pdc: 'right'
    };

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
        });
    }

    function pinHtml(site) {
        return '<span class="loc-pin-dot"></span><span class="loc-pin-label loc-pin-label-' +
            (labelDir[site.slug] || 'top') + '">' + escapeHtml(site.name) + '</span>';
    }

    function popupHtml(site) {
        const assigned = !!site.available;
        const statusClass = assigned ? 'is-on' : 'is-off';
        const statusText = assigned ? 'Gateway Assigned' : 'No Gateway Assigned';
        const pencil = canConfigure
            ? '<button type="button" class="loc-popup-edit" data-slug="' + escapeHtml(site.slug) + '" aria-label="Configure location">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button>'
            : '';

        return '<div class="loc-popup"><strong>' + escapeHtml(site.name) + '</strong>' +
            '<div class="loc-popup-address">' + escapeHtml(site.address) + '</div>' +
            '<div class="loc-popup-status-row">' +
            '<div class="loc-popup-status ' + statusClass + '">' +
            '<span class="loc-map-swatch ' + statusClass + '" aria-hidden="true"></span> ' + statusText +
            '</div>' + pencil + '</div></div>';
    }

    function matchesQuery(site, query) {
        if (!query) return true;
        return (site.name + ' ' + (site.address || '') + ' ' + site.slug).toLowerCase().indexOf(query) !== -1;
    }

    if (typeof L === 'undefined') {
        root.textContent = 'Map library failed to load.';
        return;
    }

    const map = L.map(root, { zoomControl: true, scrollWheelZoom: true, dragging: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    const markers = [];
    sites.forEach(function (site) {
        const marker = L.marker([site.lat, site.lng], {
            icon: markerIcon(site),
            title: site.name
        }).addTo(map);
        marker.bindPopup(popupHtml(site), { maxWidth: 320, autoPan: true, autoPanPadding: [28, 28] });
        marker._site = site;
        markers.push(marker);
    });

    function markerIcon(site) {
        return L.divIcon({
            className: 'loc-pin loc-pin-' + site.slug + (site.available ? ' is-on' : ' is-off'),
            html: pinHtml(site),
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        });
    }

    function refreshMarker(marker) {
        marker.setIcon(markerIcon(marker._site));
        marker.setPopupContent(popupHtml(marker._site));
    }

    function closeConfig() {
        panel?.setAttribute('hidden', 'hidden');
        shell?.classList.remove('is-configuring');
        setTimeout(function () { map.invalidateSize(); }, 40);
    }

    function openConfig(site) {
        if (!canConfigure || !panel || !form) return;
        slugEl.value = site.slug;
        if (nameEl) nameEl.textContent = site.name;
        if (addressEl) addressEl.textContent = site.address || '';
        if (statusEl) {
            const assignedOn = !!site.available;
            statusEl.className = 'loc-popup-status ' + (assignedOn ? 'is-on' : 'is-off');
            statusEl.innerHTML = '<span class="loc-map-swatch ' + (assignedOn ? 'is-on' : 'is-off') + '" aria-hidden="true"></span> ' +
                (assignedOn ? 'Gateway Assigned' : 'No Gateway Assigned');
        }
        const assigned = site.available ? '1' : '0';
        form.querySelectorAll('input[name="assigned"]').forEach(function (input) {
            input.checked = input.value === assigned;
        });
        panel.removeAttribute('hidden');
        shell?.classList.add('is-configuring');
        map.closePopup();
        setTimeout(function () { map.invalidateSize(); }, 40);
    }

    map.on('popupopen', function (event) {
        const button = event.popup.getElement()?.querySelector('.loc-popup-edit');
        button?.addEventListener('click', function (clickEvent) {
            clickEvent.preventDefault();
            clickEvent.stopPropagation();
            const marker = event.popup._source;
            if (marker?._site) openConfig(marker._site);
        });
    });

    document.getElementById('locConfigClose')?.addEventListener('click', closeConfig);
    document.getElementById('locConfigCancel')?.addEventListener('click', closeConfig);

    form?.addEventListener('submit', function (event) {
        event.preventDefault();
        const slug = slugEl.value;
        const assigned = form.querySelector('input[name="assigned"]:checked')?.value === '1';
        const marker = markers.find(function (item) { return item._site.slug === slug; });
        if (!marker || !statusUrlTemplate) return;
        const url = statusUrlTemplate.replace('__SLUG__', encodeURIComponent(slug));
        fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ assigned: assigned })
        }).then(function (response) {
            if (!response.ok) throw new Error('Unable to save location status.');
            return response.json();
        }).then(function (payload) {
            marker._site.available = !!(payload && Object.prototype.hasOwnProperty.call(payload, 'assigned') ? payload.assigned : assigned);
            refreshMarker(marker);
            closeConfig();
        }).catch(function () {});
    });

    function showMetroManila(animate) {
        map.setView(MANILA_CENTER, MANILA_ZOOM, { animate: !!animate });
    }

    function applySearch() {
        const query = (search?.value || '').trim().toLowerCase();
        const matched = [];
        markers.forEach(function (marker) {
            const hit = matchesQuery(marker._site, query);
            const el = marker.getElement();
            if (el) el.classList.toggle('is-dimmed', !!query && !hit);
            if (hit) matched.push(marker);
        });
        if (!query) {
            map.closePopup();
            showMetroManila(false);
            return;
        }
        const exact = matched.filter(function (marker) {
            const site = marker._site;
            return String(site.name).toLowerCase() === query || String(site.slug).toLowerCase() === query;
        });
        const focus = exact[0] || (matched.length === 1 ? matched[0] : null);
        if (focus) {
            map.setView(focus.getLatLng(), 16, { animate: true });
            focus.openPopup();
        }
    }

    showMetroManila(false);
    setTimeout(function () {
        map.invalidateSize();
        showMetroManila(false);
        if (search?.value) applySearch();
    }, 80);
    ['input', 'change', 'keyup', 'search'].forEach(function (eventName) {
        search?.addEventListener(eventName, applySearch);
    });
})();
</script>
@endpush
