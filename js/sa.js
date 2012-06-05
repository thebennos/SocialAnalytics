var SA = {
    map: new OpenLayers.Map("mapdiv"),
    bounds: new OpenLayers.Bounds(),
    lyrMarkers: new OpenLayers.Layer.Markers("Markers"),
    currentPopup: null,
    viewHeight: $(window).height(),
    graphLibs: [],

    // Called once when page loads
    init: function() {
        // FIXME: Ugly fix to figure out if we have map data or not.
        if(typeof sa_following_coords != 'undefined' && typeof sa_followers_coords != 'undefined') {
            this.map.addLayer(new OpenLayers.Layer.OSM());
            this.map.addLayer(this.lyrMarkers);

            // FIXME: sa_follow{ing,ers}_coords is generated with PHP and globally defined in the markup
            if(typeof sa_following_coords != 'undefined') {
                this.addMarkers(sa_following_coords, 'marker.png');
            }
            if(typeof sa_followers_coords != 'undefined') {
                this.addMarkers(sa_followers_coords, 'marker-blue.png');
            }

            center = this.bounds.getCenterLonLat();
            this.map.setCenter(center, this.map.getZoomForExtent(this.bounds) - 1);
        }

        // JS Switcher
        $('#social_js_switcher').change(function() {
            $('.social_graph').html('');
            
            // We've never loaded this lib.
            if(typeof SA.graphLibs[$(this).val()] == 'undefined') {
                var jsFilename = $(this).val();
                var snRoot = window.location.pathname.replace(/\/social$/, '/');
                $.getScript(snRoot + 'plugins/SocialAnalytics/js/lib/' + jsFilename, function(){
                    $.getScript(snRoot + 'plugins/SocialAnalytics/js/tbl2js/' + jsFilename, function() {SA.graphLibs[jsFilename]();});
                });
            } // Lib's already loaded, just call it
            else {
                SA.graphLibs[$(this).val()]();
            }
        });
        $('#social_js_switcher').trigger('change'); // Create the graphs for the 1st time (with default selected lib)

        // Show/hide custom date form
        $('.social_nav_top .cust a').click(function(e) {
            e.preventDefault();
            e.stopPropagation;
            $('.social_date_picker_top').fadeToggle();
        });

        $('.social_nav_bottom .cust a').click(function(e) {
            e.preventDefault();
            e.stopPropagation;
            $('.social_date_picker_bottom').fadeToggle();
        });
        
        // Bind datepickers
        $('#social_start_date_top, #social_end_date_top, #social_start_date_bottom, #social_end_date_bottom').datepicker({
            showOn: "button",
            buttonImage: "/plugins/SocialAnalytics/images/calendar.png",  // FIXME: This won't work on instances installed in a subdir
            buttonImageOnly: true,
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            maxDate: new Date()
        });

        // Wrap <td> numbers in a link that will show <td> details when clicked on.
        $('.social_table td').each(function(){
            var caption = $(this).parents('table').children('caption').text();

            var diag = $(this).children('ul').dialog({autoOpen: false, title: caption, open: SA.dialogResize});
            diag.parent().css('max-height', SA.viewHeight + 'px');

            var num = $(this).text();

            if(num == '0') {
                return;
            }

            $(this).empty();
            $('<a href="#">' + num + '</a>').click(function(e){
                e.preventDefault();
                e.stopPropagation();

                diag.dialog('open');
            })
            .appendTo(this);
        });

        // Show/hide data tables
        $('.toggleTable').click(function(e){
            e.preventDefault();
            e.stopPropagation();
            
            // Change 'Show' to 'Hide' or vice-versa in 'Show [table name] table' link
            var txt = $(this).text();
            $(this).text( txt.match(/^Show/) ? txt.replace(/^Show/, 'Hide') : txt.replace(/^Hide/, 'Show') );

            // Toggle data table visibility
            $(this).next('table').fadeToggle();
        });        
    },

    // Calls SA.addMarker() for each item in array
    addMarkers: function(arr, icon) {
        for(var i=0; i<arr.length; i++) {
            this.addMarker(arr[i].lon, arr[i].lat, arr[i].nickname, icon);
        }
    },

    // Adds a marker to the map
    addMarker: function(lon, lat, nickname, icon_filename) {
        var lonLat = new OpenLayers.LonLat(lon, lat)
            .transform(
                new OpenLayers.Projection("EPSG:4326"), // transform from WGS 1984
                this.map.getProjectionObject()          // to Spherical Mercator Projection
            );

        this.bounds.extend(lonLat);

        var size = new OpenLayers.Size(21,25);
        var icon = new OpenLayers.Icon("http://www.openlayers.org/dev/img/" + icon_filename,
                            size,
                            new OpenLayers.Pixel(-(size.w/2), -size.h));

        var marker = new OpenLayers.Marker(lonLat, icon.clone());
        marker.events.register('mousedown', this.newPopup(lonLat, nickname), this.markerClick);
        this.lyrMarkers.addMarker(marker);
    },

    // Creates a popup that will show up when clicking on the marker
    newPopup: function(lonLat, content) {
        var popupClass = OpenLayers.Class(OpenLayers.Popup.FramedCloud, {
            "autoSize": true,
            "minSize": new OpenLayers.Size(300, 50),
            "maxSize": new OpenLayers.Size(500, 300),
            "keepInMap": true
        });

        var popup = new OpenLayers.Feature(this.lyrMarkers, lonLat);
        popup.closeBox = true;
        popup.popupClass = popupClass;
        popup.data.popupContentHTML = content;
        popup.data.overflow = 'auto';

        return popup;
    },

    // Triggered when clicking on a map marker
    markerClick: function(evt) {
        if (SA.currentPopup != null && SA.currentPopup.visible()) {
            SA.currentPopup.hide();
        }
        if (this.popup == null) {
            this.popup = this.createPopup(this.closeBox);
            SA.map.addPopup(this.popup);
            this.popup.show();
        } else {
            this.popup.toggle();
        }
        SA.currentPopup = this.popup;
        OpenLayers.Event.stop(evt);
    },

    // If jQuery Dialog height is longer than viewport, shorten and add scrollbar
    dialogResize: function() {
        var ulHeight = $(this).outerHeight();
        var dialogHeight = $(this).parent().outerHeight();
        var headerHeight = $(this).siblings('.ui-dialog-titlebar').first().outerHeight();

        if(ulHeight > dialogHeight) {
            $(this).css({'overflow': 'scroll', 'max-height': dialogHeight - headerHeight + 'px'});
        }
    }
}

SA.init();
