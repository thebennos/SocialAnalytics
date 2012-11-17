(function ($) {
    var SA, _SA;
    SA = (typeof window.SA !== 'undefined' && window.SA !== null) ? window.SA : {};

    _SA = {
        map: new OpenLayers.Map('mapdiv'),
        bounds: new OpenLayers.Bounds(),
        lyrMarkers: new OpenLayers.Layer.Markers('Markers'),
        currentPopup: null,
        viewHeight: $(window).height(),
        graphLibs: [],

        // Called once when page loads
        init: function () {
            var center,
                snRoot = window.location.pathname.replace(/(\/index\.php)?\/social$/, '/'),
                $dial = $('<div id="sa-dialog"></div>').dialog({
                    autoOpen: false,
                    modal: true,
                    width: 700
                }).css('max-height', SA.viewHeight);

            // FIXME: Ugly fix to figure out if we have map data or not.
            if (sa_following_coords !== undefined && sa_followers_coords !== undefined) {
                this.map.addLayer(new OpenLayers.Layer.OSM());
                this.map.addLayer(this.lyrMarkers);

                // FIXME: sa_follow{ing,ers}_coords is generated with PHP and globally defined in the markup
                if (sa_following_coords !== undefined) {
                    this.addMarkers(sa_following_coords, 'marker.png');
                }
                if (sa_followers_coords !== undefined) {
                    this.addMarkers(sa_followers_coords, 'marker-blue.png');
                }

                center = this.bounds.getCenterLonLat();
                this.map.setCenter(center, this.map.getZoomForExtent(this.bounds) - 1);
            }

            // JS Switcher
            $('#social_js_switcher').change(function () {
                $('.social_graph').html('');

                // We've never loaded this lib.
                if (SA.graphLibs[$(this).val()] === undefined) {
                    var jsFilename = $(this).val();

                    $.getScript(snRoot + 'plugins/SocialAnalytics/js/lib/' + jsFilename, function () {
                        $.getScript(snRoot + 'plugins/SocialAnalytics/js/tbl2js/' + jsFilename, function () { SA.graphLibs[jsFilename](); });
                    });
                } else { // Lib's already loaded, just call it
                    SA.graphLibs[$(this).val()]();
                }
            });
            $('#social_js_switcher').trigger('change'); // Create the graphs for the 1st time (with default selected lib)

            // Show/hide custom date form
            $('.social_nav_top .cust a').click(function (e) {
                e.preventDefault();
                e.stopPropagation();
                $('.social_date_picker_top').fadeToggle();
            });

            $('.social_nav_bottom .cust a').click(function (e) {
                e.preventDefault();
                e.stopPropagation();
                $('.social_date_picker_bottom').fadeToggle();
            });

            // Bind datepickers
            $('#social_start_date_top, #social_end_date_top, #social_start_date_bottom, #social_end_date_bottom').datepicker({
                showOn: 'button',
                buttonImage: snRoot + 'plugins/SocialAnalytics/images/calendar.png',
                buttonImageOnly: true,
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                maxDate: new Date()
            });

            function callback(elm) {
                return function (data) {
                    var date    = new Date(data.created_at), // TODO: handle cases when this fails
                        caption = elm.closest('table').children('caption').text(),
                        html    = '<div class="entry-title">\
    <div class="author">\
     <span class="vcard author">\
      <a href="' + data.user.statusnet_profile_url + '" class="url" title="' + data.user.screen_name + '">\
       <img width="48" height="48" src="' + data.user.profile_image_url + '" class="avatar photo" alt="' + data.user.screen_name + '">\
        <span class="fn">' + data.user.screen_name + '</span>\
      </a>\
     </span>\
    </div>\
    <p class="entry-content">' + data.statusnet_html + '</p>\
    </div>\
    <div class="entry-content">on \
    <a rel="bookmark" class="timestamp" href="' + snRoot + 'notice/' + data.id + '">\
     <abbr class="published" title="' + data.created_at  + '">' + date.toISOString().split('T')[0] + '</abbr>\
    </a>\
     <span class="source">from <span class="device">\
      <a href="' + snRoot + 'notice/' + data.id + '" rel="external">' + data.source + '</a>\
     </span>\
    </span> ';
                    // If it's a repeat or a reply, show in context link
                    if (data.retweeted_status !== undefined || data.in_reply_to_user_id !== null) {
                        html += '<a class="response" href="' + snRoot + 'conversation/' +
                            data.statusnet_conversation_id + '#notice-' + data.id + '">in context</a>';
                    }

                    html += '</div>';

                    elm.html(html)
                        .addClass('ajaxed notice');

                    // If we have all the notices, place them in the dialog
                    if (elm.siblings('li').not('.ajaxed').length === 0) {
                        $dial.html(elm.closest('ul'));

                        // Reposition
                        $dial.dialog('option', 'position', $dial.dialog('option', 'position'));
                    }
                };
            }

            // Wrap <td> numbers in a link that will show <td> details when clicked on.
            $('.social_table td').each(function () {
                var $this   = $(this),
                    caption = $this.closest('table').children('caption').text(),
                    content = $this.children('ul'),
                    $link   = $('<a href="#"></a>'),
                    $num    = $this.children('span');

                if ($num.text() === '0') {
                    return;
                }

                $link.click(function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $this = $(this);

                    // If we already fetched the data, just show it
                    if ($this.hasClass('ajaxed')) {
                        $dial.html(content)
                            .dialog('option', 'title', caption)
                            .dialog('open');
                    } else {
                        $this.addClass('ajaxed'); // Mark as fetched

                        // Loading indicator
                        $dial.html('<div class="sa-processing"></div>')
                            .dialog('option', 'title', caption)
                            .dialog('open');

                        $this.siblings('ul').children('li').each(function () {
                            var $this = $(this);

                            // Notice
                            if ($this.hasClass('sa-notice')) {
                                $this.removeClass('sa-notice');

                                // Fetch notice data
                                $.ajax({
                                    url: snRoot + 'api/statuses/show.json?id=' + $this.attr('class'),
                                    dataType: 'json',
                                    success: callback($this),
                                    error: function (xhr, txt, err) {
                                        // Fall back to non-rich data
                                        $this.addClass('ajaxed');
                                        $dial.html(content)
                                            .dialog('option', 'title', caption)
                                            .dialog('open');
                                    }
                                });
                            } else { // Profile
                                $dial.html(content)
                                    .dialog('option', 'title', caption)
                                    .dialog('open');
                            }
                        });
                    }
                });

                $num.wrap($link);
            });

            // Show/hide data tables
            $('.toggleTable').click(function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Change 'Show' to 'Hide' or vice-versa in 'Show [table name] table' link
                var $this = $(this),
                    txt   = $this.text();

                $this.text(txt.match(/^Show/) ? txt.replace(/^Show/, 'Hide') : txt.replace(/^Hide/, 'Show'));

                // Toggle data table visibility
                $this.next('table').fadeToggle();
            });
        },

        // Calls SA.addMarker() for each item in array
        addMarkers: function (arr, icon) {
            var i, len;
            for (i = 0, len = arr.length; i < len; i += 1) {
                this.addMarker(arr[i].lon, arr[i].lat, arr[i].nickname, icon);
            }
        },

        // Adds a marker to the map
        addMarker: function (lon, lat, nickname, icon_filename) {
            var lonLat, size, icon, marker;

            lonLat = new OpenLayers.LonLat(lon, lat)
                .transform(
                    new OpenLayers.Projection('EPSG:4326'), // transform from WGS 1984
                    this.map.getProjectionObject()          // to Spherical Mercator Projection
                );

            this.bounds.extend(lonLat);

            size = new OpenLayers.Size(21, 25);
            icon = new OpenLayers.Icon('http://www.openlayers.org/dev/img/' + icon_filename,
                                size,
                                new OpenLayers.Pixel(-(size.w / 2), -size.h));

            marker = new OpenLayers.Marker(lonLat, icon.clone());
            marker.events.register('mousedown', this.newPopup(lonLat, nickname), this.markerClick);
            this.lyrMarkers.addMarker(marker);
        },

        // Creates a popup that will show up when clicking on the marker
        newPopup: function (lonLat, content) {
            var popup      = new OpenLayers.Feature(this.lyrMarkers, lonLat),
                popupClass = OpenLayers.Class(OpenLayers.Popup.FramedCloud, {
                    'autoSize': true,
                    'minSize': new OpenLayers.Size(300, 50),
                    'maxSize': new OpenLayers.Size(500, 300),
                    'keepInMap': true
                });

            popup.closeBox = true;
            popup.popupClass = popupClass;
            popup.data.popupContentHTML = content;
            popup.data.overflow = 'auto';

            return popup;
        },

        // Triggered when clicking on a map marker
        markerClick: function (evt) {
            if (SA.currentPopup !== null && SA.currentPopup.visible()) {
                SA.currentPopup.hide();
            }
            if (this.popup === null) {
                this.popup = this.createPopup(this.closeBox);
                SA.map.addPopup(this.popup);
                this.popup.show();
            } else {
                this.popup.toggle();
            }
            SA.currentPopup = this.popup;
            OpenLayers.Event.stop(evt);
        }
    };

    window.SA = $.extend(true, SA, _SA);
    return window.SA;
}(jQuery)).init();
