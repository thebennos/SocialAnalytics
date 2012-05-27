var SA = {
    map: new OpenLayers.Map("mapdiv"),
    bounds: new OpenLayers.Bounds(),
    lyrMarkers: new OpenLayers.Layer.Markers("Markers"),
    currentPopup: null,
    viewHeight: $(window).height(), // Nothing to do with maps

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

        /* Common JS below. Nothing to do with the map */
        // Show/hide custom date form
        $('.social_nav .cust a').click(function(e) {
            e.preventDefault();
            e.stopPropagation;
            $('.social_date_picker').fadeToggle();
        });
        
        // Bind datepickers
        $('#social_start_date, #social_end_date').datepicker({
            showOn: "button",
            buttonImage: "/plugins/SocialAnalytics/images/calendar.png",  // FIXME: This won't work on instances installed in a subdir
            buttonImageOnly: true,
            dateFormat: 'yy-mm-dd',
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

    addMarkers: function(arr, icon) {
        for(var i=0; i<arr.length; i++) {
            this.addMarker(arr[i].lon, arr[i].lat, arr[i].nickname, icon);
        }
    },

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

    // Nothing to do with maps
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
