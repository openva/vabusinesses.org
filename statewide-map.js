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

                for (var i = 0; i < data.p.length; i += 2) {
                    var marker = L.circleMarker([data.p[i] / 1000, data.p[i + 1] / 1000], {
                        radius: 4,
                        weight: 1,
                        color: '#1a4f7a',
                        fillColor: '#2f74ad',
                        fillOpacity: 0.7
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
                         * different from a single shopfront.
                         */
                        if (here.length > 1) {
                            marker.setRadius(Math.min(12, 4 + Math.log(here.length) * 2));
                        }

                        marker.on('click', function (identifiers) {
                            return function () {
                                describe(this, identifiers);
                            };
                        }(here));
                    }

                    markers.push(marker);
                }

                pointLayer = L.layerGroup(markers).addTo(map);

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

    function describe(marker, identifiers) {
        var total = identifiers.length;

        marker.bindPopup('<p class="meta">Loading&hellip;</p>').openPopup();

        var wanted = identifiers.slice(0, NAMES_TO_FETCH);

        Promise.all(wanted.map(function (identifier) {
            return fetch('/api/business/' + encodeURIComponent(identifier))
                .then(function (response) {
                    return response.ok ? response.json() : null;
                })
                .then(function (business) {
                    return {
                        id: identifier,
                        name: (business && business.Name) ? business.Name : identifier
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
