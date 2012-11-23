(function ($) {
    var SA = window.SA;

    /* Generating graphs */
    SA.graphLibs['visualize.jQuery.js'] = function () {
        // Apply visualize CSS
        $('#content_inner').attr('class', 'sa-vis');

        // Generate line chart
        $('#sa-trends').visualize({type: 'line', width: 700, height: 300, parseDirection: 'y', colFilter: ':not(.visualize-ignore)', rowFilter: ':not(.visualize-ignore)'})
            .appendTo('.sa-trends-graph');

        /* Clickable table headers in "Trends" table hides/shows series in line graph */
        // For each table header in the first table row
        $('#sa-trends tr:eq(0) th').each(function (i, th) {
            // Build the link that will be wrapped around the table header
            var $link = $('<a href="#"></a>').click(function (e) {
                // Don't follow link on click
                e.preventDefault();
                e.stopPropagation();

                // Add the ignored class to the header and the rest of the colum
                $(th).toggleClass('visualize-ignore');
                $('#sa-trends tr:gt(0) td:nth-child(' + parseInt(i + 2, 10) + ')').toggleClass('visualize-ignore');

                // Refresh the graph
                $('.visualize').trigger('visualizeRefresh');
            });

            // Add link around table headers
            $(this).wrapInner($link);
        });

        // Generate pie charts
        $('.sa-pie').each(function () {
            $(this).visualize({type: 'pie', width: 700, height: 300})
                .appendTo($('.' + $(this).attr('id') + '-graph'));
        });
    };
}(jQuery));