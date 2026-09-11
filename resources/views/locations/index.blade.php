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

<div class="loc-map-shell">
    <div id="programLocationMap" class="loc-map" role="application" aria-label="Program Location map"></div>
    <script type="application/json" id="programLocationSites">{!! json_encode($sites) !!}</script>
    <div class="loc-map-legend" aria-label="Map legend">
        <div class="loc-map-legend-item"><span class="loc-map-swatch is-on" aria-hidden="true"></span> Gateway Assigned</div>
        <div class="loc-map-legend-item"><span class="loc-map-swatch is-off" aria-hidden="true"></span> No Gateway Assigned</div>
    </div>
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

        return '<div class="loc-popup"><strong>' + escapeHtml(site.name) + '</strong>' +
            '<div class="loc-popup-address">' + escapeHtml(site.address) + '</div>' +
            '<div class="loc-popup-status ' + statusClass + '">' +
            '<span class="loc-map-swatch ' + statusClass + '" aria-hidden="true"></span> ' + statusText +
            '</div></div>';
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
            icon: L.divIcon({
                className: 'loc-pin loc-pin-' + site.slug + (site.available ? ' is-on' : ' is-off'),
                html: pinHtml(site),
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            }),
            title: site.name
        }).addTo(map);
        marker.bindPopup(popupHtml(site), { maxWidth: 320, autoPan: true, autoPanPadding: [28, 28] });
        marker._site = site;
        markers.push(marker);
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
