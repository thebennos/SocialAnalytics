/* Generating graphs */
SA.graphLibs['highcharts.js'] = function() {
    $('#content_inner').attr('class', 'sa_highcharts');

    $('.social_wrapper').each(function(i) { // FIXME: This is crap. Revise.
        if(i%2 === 0) {
            $(this).addClass('sa_right');
        }
        else {
            $(this).addClass('sa_left');
        }
    });

    /* Trends chart */
    var trends = [],
        trendsHeaders = [],
        tmp  = {};

    // Fetch headings and init sub-arrays
    $('#trends_table thead th').each(function(j) {
        trendsHeaders.push($(this).text());
        trends[j] = [];
    });

    // Parse table
    $('#trends_table tbody tr').each(function(i) {
        $(this).find('td').each(function(j) {
            trends[j].push([i, parseInt($(this).text())]);
        });
    });

    // Attach name to data
    for(var i=0; i<trends.length; i++) {
        tmp.name = trendsHeaders[i];
        tmp.data = trends[i];
        trends[i] = tmp;
        tmp = new Object;
    }

    // Draw line chart
    new Highcharts.Chart({
        chart: {
            renderTo: $('.trends_graph')[0]
        },
        title: {
            text: ''
        },
        yAxis: {
            title: {
                text: ''
            }
        },
        series: trends,
        tooltip: {
            formatter: function() {return this.y;}
        }
    });

    /* Pie charts */
    var _data = [];

    $('.social_pie').each(function() {
        $(this).find('tbody tr').each(function(i) {
            _data.push([$(this).children('th').text(), parseInt($(this).children('td').text())]);
        });
        var graphContainer = $('.' + $(this).attr('id').replace(/_table$/, '_graph'));

        new Highcharts.Chart({
            chart: {
                renderTo: graphContainer[0]
            },
            title: {
                text: ''
            },
            series: [{
                type: 'pie',
                data: _data
            }],
            tooltip: {
                formatter: function() {return this.y + ' (' + this.percentage.toFixed(2) + '%)';}
            }
        });

        _data = [];
    });
}
