/* Generating graphs */
SA.graphLibs['visualize.jQuery.js'] = function() {
    // Apply visualize CSS
    $('#content_inner').attr('class', 'sa_visualize');

    // Generate line chart
    $('#trends_table').visualize({type: 'line', width: 700, height: 300, parseDirection: 'y', colFilter: ':not(.visualize-ignore)', rowFilter: ':not(.visualize-ignore)'})
        .appendTo('.trends_graph');

    /* Clickable table headers in "Trends" table hides/shows series in line graph */
    // For each table header in the first table row
    $('#trends_table tr:eq(0) th').each(function(i, th) {
        // Build the link that will be wrapped around the table header
        $link = $('<a href="#"></a>').click(function(e) {
            // Don't follow link on click
            e.preventDefault();
            e.stopPropagation();
            
            // Add the ignored class to the header and the rest of the colum
            $(th).toggleClass('visualize-ignore');
            $('#trends_table tr:gt(0) td:nth-child(' + parseInt(i+2) + ')').toggleClass('visualize-ignore');

            // Refresh the graph
            $('.visualize').trigger('visualizeRefresh');
        });

        // Add link around table headers
        $(this).wrapInner($link);
    });

    // Generate pie charts
    $('table.social_pie').each(function(){
        $(this).visualize({type: 'pie', width: 700, height: 300})
            .appendTo($('.' + $(this).attr('id').replace(/_table$/, '_graph')));
    });
}
