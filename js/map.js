var SA = {
    map: new OpenLayers.Map("mapdiv"),
    bounds: new OpenLayers.Bounds(),
    lyrMarkers: new OpenLayers.Layer.Markers("Markers"),
    currentPopup: null,

    init: function() {
        this.map.addLayer(new OpenLayers.Layer.OSM());
        this.map.addLayer(this.lyrMarkers);

        // FIXME: sa_follow{ing,ers}_coords is generated with PHP and globally defined in the markup
        this.addMarkers(sa_following_coords, 'marker.png');
        this.addMarkers(sa_followers_coords, 'marker-blue.png');

        center = this.bounds.getCenterLonLat();
        this.map.setCenter(center, this.map.getZoomForExtent(this.bounds) - 1);
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
    }

}

SA.init();
