/*
 * The statewide map: a choropleth of Virginia's localities, shaded by how many
 * businesses are registered in each, which loads that locality's individual
 * businesses when one is clicked.
 *
 * Nearly a million businesses are registered and not expired. Sending them all
 * to the browser is not practical, so the map works in three stages: a summary
 * of 133 localities, then one locality's points on demand, then a single
 * business's details from the API when its marker is clicked.
 */
(function () {
    'use strict';

    var element = document.getElementById('statewide-map');

    if (!element || typeof L === 'undefined') {
        return;
    }

    var tiles = element.getAttribute('data-tiles');
    var preview = element.hasAttribute('data-preview');

    var map = L.map(element, {
        center: [37.6, -78.9],
        zoom: preview ? 6 : 7,
        scrollWheelZoom: !preview,
        zoomControl: !preview,
        dragging: !preview,
        doubleClickZoom: !preview
    });

    if (tiles) {
        L.tileLayer(tiles, {
            attribution: element.getAttribute('data-attribution') || '',
            tileSize: 512,
            zoomOffset: -1,
            maxZoom: 18
        }).addTo(map);
    }

    var counts = {};
    var maximum = 1;

    /*
     * Shading. A linear scale would put every locality but the largest few at
     * the pale end, so the square root is used to spread the middle out.
     */
    function shade(count) {
        if (!count) {
            return '#f0f0f0';
        }

        var t = Math.sqrt(count) / Math.sqrt(maximum);

        var shades = ['#e3eef7', '#bcd7ea', '#8fbadc', '#5b98c9', '#2f74ad', '#1a4f7a'];

        return shades[Math.min(shades.length - 1, Math.floor(t * shades.length))];
    }

    function style(feature) {
        return {
            fillColor: shade(counts[feature.properties.COUNTYFP] || 0),
            fillOpacity: 0.75,
            color: '#ffffff',
            weight: 1
        };
    }

    var pointLayer = null;
    var activeFips = null;

    /*
     * Load and draw one locality's businesses. Points arrive as a flat array of
     * integers scaled by 1000, which is why they are read two at a time.
     */
    function showLocality(fips, name) {
        if (preview) {
            return;
        }

        fetch('/data/map/' + fips + '.json')
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (data) {
                if (!data || !data.p) {
                    return;
                }

                if (pointLayer) {
                    map.removeLayer(pointLayer);
                }

                var markers = [];

                /*
                 * A drawn marker is 8px across, well under the 24px that WCAG
                 * asks of a target and a fraction of a fingertip. Each one
                 * therefore gets an invisible companion beneath it, big enough
                 * to hit, which forwards its clicks to the visible marker.
                 */
                var HIT_RADIUS = 14;

                for (var i = 0; i < data.p.length; i += 2) {
                    var position = [data.p[i] / 1000, data.p[i + 1] / 1000];

                    var marker = L.circleMarker(position, {
                        radius: 4,
                        weight: 1,
                        color: '#1a4f7a',
                        fillColor: '#2f74ad',
                        fillOpacity: 0.7
                    });

                    var target = L.circleMarker(position, {
                        radius: HIT_RADIUS,
                        stroke: false,
                        fillOpacity: 0,
                        /*
                         * Drawn, so it receives pointer events, but invisible.
                         */
                        interactive: true
                    });

                    /*
                     * Each marker is a location, and the matching entry in "i"
                     * lists every business registered there.
                     */
                    var here = data.i ? data.i[i / 2] : null;

                    if (here && here.length) {
                        /*
                         * A location with several businesses is drawn larger, so
                         * that a building full of registrations is visibly
                         * different from a single shopfront. The hit area grows
                         * with it, never shrinking below HIT_RADIUS.
                         */
                        if (here.length > 1) {
                            var radius = Math.min(12, 4 + Math.log(here.length) * 2);

                            marker.setRadius(radius);
                            target.setRadius(Math.max(HIT_RADIUS, radius + 6));
                        }

                        var open = function (identifiers, visible) {
                            return function () {
                                describe(visible, identifiers);
                            };
                        }(here, marker);

                        marker.on('click', open);
                        target.on('click', open);
                    }

                    /*
                     * The hit area is added first so that it sits beneath the
                     * marker it belongs to.
                     */
                    markers.push(target);
                    markers.push(marker);
                }

                pointLayer = L.layerGroup(markers).addTo(map);
                activeFips = fips;

                var status = document.getElementById('map-status');

                if (status) {
                    status.textContent = data.n.toLocaleString() + ' businesses in ' + name;
                }
            })
            .catch(function () {
                var status = document.getElementById('map-status');

                if (status) {
                    status.textContent = 'Could not load businesses for ' + name + '.';
                }
            });
    }

    /*
     * List the businesses at a location, and link to each. The map data holds
     * only their identifiers; the names come from the API, one request per
     * business, which is why the list is capped -- a location with 265
     * registrations should not fire 265 requests on a single click.
     */
    var NAMES_TO_FETCH = 25;

    /*
     * Where a business record is fetched from. The static API is a tree of JSON
     * files, one per entity, sharded by the first four characters of the
     * identifier: entity/0000/00000828.json. Fetching those directly means the
     * 25 requests a popup makes never touch this server.
     *
     * Falls back to this site's own API when no static base is configured, which
     * is what local development uses.
     */
    var staticBase = element.getAttribute('data-static-api') || '';

    function recordUrl(identifier) {
        if (staticBase === '') {
            return '/api/business/' + encodeURIComponent(identifier);
        }

        return staticBase + '/entity/' + encodeURIComponent(identifier.slice(0, 4))
            + '/' + encodeURIComponent(identifier) + '.json';
    }

    function describe(marker, identifiers) {
        var total = identifiers.length;

        marker.bindPopup('<p class="meta">Loading&hellip;</p>').openPopup();

        var wanted = identifiers.slice(0, NAMES_TO_FETCH);

        Promise.all(wanted.map(function (identifier) {
            /*
             * Falling back to this site's own API when the static one cannot be
             * reached. The static tree lives in a bucket that may not be public
             * yet, and is unreachable from a development machine in any case;
             * without this the popup would list bare identifiers instead of
             * names.
             */
            var fromApi = function () {
                return fetch('/api/business/' + encodeURIComponent(identifier))
                    .then(function (response) {
                        return response.ok ? response.json() : null;
                    })
                    .catch(function () {
                        return null;
                    });
            };

            /*
             * Falling back to this site's own API when the static one cannot be
             * reached. The static tree lives in a bucket that may not be public
             * yet, and its hostname does not resolve from a development machine,
             * so the request can fail either with a non-ok response or by
             * rejecting outright -- both are handled here. Without this the
             * popup would list bare identifiers instead of names.
             */
            return fetch(recordUrl(identifier))
                .then(function (response) {
                    if (response.ok) {
                        return response.json();
                    }

                    return staticBase === '' ? null : fromApi();
                })
                .catch(function () {
                    return staticBase === '' ? null : fromApi();
                })
                .then(function (business) {
                    /*
                     * The static files use lowercase field names; this site's
                     * own API uses the SCC's capitalisation. Accept either.
                     */
                    var name = business && (business.name || business.Name);

                    return {
                        id: identifier,
                        name: name || identifier
                    };
                })
                .catch(function () {
                    return { id: identifier, name: identifier };
                });
        })).then(function (businesses) {
            var wrapper = document.createElement('div');
            wrapper.className = 'map-popup';

            var heading = document.createElement('p');
            heading.className = 'map-popup-count';
            heading.textContent = total === 1
                ? '1 business here'
                : total.toLocaleString() + ' businesses here';
            wrapper.appendChild(heading);

            var list = document.createElement('ul');

            businesses.forEach(function (business) {
                var item = document.createElement('li');
                var link = document.createElement('a');

                link.href = '/business/' + encodeURIComponent(business.id);
                link.textContent = business.name;

                item.appendChild(link);
                list.appendChild(item);
            });

            wrapper.appendChild(list);

            if (total > wanted.length) {
                var more = document.createElement('p');
                more.className = 'meta';
                more.textContent = 'and ' + (total - wanted.length).toLocaleString() + ' more';
                wrapper.appendChild(more);
            }

            marker.setPopupContent(wrapper);
        });
    }

    Promise.all([
        fetch('/data/map/summary.json').then(function (r) { return r.ok ? r.json() : null; }),
        fetch('/municipalities.geojson').then(function (r) { return r.ok ? r.json() : null; })
    ]).then(function (results) {
        var summary = results[0];
        var boundaries = results[1];

        if (!summary || !boundaries) {
            return;
        }

        summary.places.forEach(function (place) {
            counts[place.fips] = place.count;
            maximum = Math.max(maximum, place.count);
        });

        L.geoJSON(boundaries, {
            style: style,
            onEachFeature: function (feature, layer) {
                var fips = feature.properties.COUNTYFP;
                var name = feature.properties.NAMELSAD;
                var count = counts[fips] || 0;

                layer.bindTooltip(name + ': ' + count.toLocaleString() + ' businesses');

                if (!preview) {
                    layer.on('click', function () {
                        /*
                         * A click that misses a marker falls through to the
                         * locality beneath it. Refitting the bounds every time
                         * would throw away the zoom the reader had reached, so
                         * a locality that is already showing is left alone.
                         */
                        if (activeFips === fips) {
                            return;
                        }

                        map.fitBounds(layer.getBounds());
                        showLocality(fips, name);
                    });
                }
            }
        }).addTo(map);

        var status = document.getElementById('map-status');

        if (status && summary.total) {
            status.textContent = summary.total.toLocaleString()
                + ' businesses mapped. Select a locality to see them.';
        }
    });
})();
