(function ($) {
    var SA = window.SA;

    /* Generating graphs */
    SA.graphLibs['highcharts.js'] = function () {
        // Trends chart
        var _data = [],
            i,
            len,
            trends = [],
            trendsHeaders = [],
            tmp  = {};

        $('#content_inner').attr('class', 'sa-hcharts');

        $('.sa-wrap').each(function (i) { // FIXME: This is crap. Revise.
            if (i % 2 === 0) {
                $(this).addClass('sa-r');
            } else {
                $(this).addClass('sa-l');
            }
        });

        // Fetch headings and init sub-arrays
        $('#sa-trends thead th').each(function (j) {
            trendsHeaders.push($(this).text());
            trends[j] = [];
        });

        // Parse table
        $('#sa-trends tbody tr').each(function (i) {
            $(this).find('td').each(function (j) {
                trends[j].push([i, parseInt($(this).text(), 10)]);
            });
        });

        // Attach name to data
        for (i = 0, len = trends.length; i < len; i += 1) {
            tmp.name = trendsHeaders[i];
            tmp.data = trends[i];
            trends[i] = tmp;
            tmp = {};
        }

        // Draw line chart
        new Highcharts.Chart({
            chart: {
                renderTo: $('.sa-trends-graph')[0]
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
                formatter: function () { return this.y; }
            }
        });

        // Pie charts
        $('.sa-pie').each(function () {
            var $this = $(this),
                graphContainer = $('.' + $(this).attr('id') + '-graph');

            $this.find('tbody tr').each(function (i) {
                var $this = $(this);
                _data.push([$this.children('th').text(), parseInt($this.children('td').text(), 10)]);
            });

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
                    formatter: function () { return this.y + ' (' + this.percentage.toFixed(2) + '%)'; }
                }
            });

            _data = [];
        });
    };
}(jQuery));