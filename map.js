/*
 * Render the small locator map on a business page.
 *
 * Coordinates and the tile URL are read from data attributes on the map element
 * rather than written into a script block, so that the Content-Security-Policy
 * can keep script-src at 'self' with no inline script and no nonce.
 */
(function () {
    'use strict';

    var element = document.getElementById('map');

    if (!element || typeof L === 'undefined') {
        return;
    }

    var latitude = parseFloat(element.getAttribute('data-latitude'));
    var longitude = parseFloat(element.getAttribute('data-longitude'));

    if (isNaN(latitude) || isNaN(longitude)) {
        return;
    }

    var tiles = element.getAttribute('data-tiles');

    if (!tiles) {
        return;
    }

    var map = L.map(element, {
        center: [latitude, longitude],
        /*
         * Zoom 11 covers roughly 40km across at Virginia's latitude: enough to
         * place the business among its neighbouring towns, without dropping to
         * the street it sits on or pulling back to the whole commonwealth.
         */
        zoom: 11,
        /*
         * A locator map, not a browsable one: the point is to show where this
         * business is, and scroll-wheel zoom would hijack the page scroll.
         * Zooming is still available from the controls and by double-click.
         */
        scrollWheelZoom: false
    });

    L.tileLayer(tiles, {
        attribution: element.getAttribute('data-attribution') || '',
        tileSize: 512,
        zoomOffset: -1,
        maxZoom: 18
    }).addTo(map);

    L.marker([latitude, longitude]).addTo(map);
})();
