<?php

require('init.php');

$extrahead = '';
$extrahead .= '<script language="javascript" type="text/javascript" src="../js/d3.min.js"></script>';

// one bar per 10 minutes, should be in config somewhere
$bar_size = 10;

$title = "Statistics";

if (!empty($_GET['probid']) && is_numeric($_GET['probid'])) {
    $probid = (int)$_GET['probid'];
}

$unfrozen = false;
if (!empty($_GET['unfrozen'])) {
    $unfrozen = (bool)$_GET['unfrozen'];
}

if (!empty($cid) && isset($probid)) {
    $shortname = $DB->q('VALUE SELECT shortname FROM problem p
                         INNER JOIN contestproblem cp USING (probid)
                         WHERE cp.cid = %i AND p.probid = %i', $cid, $probid);
    $title .= " - Problem " . specialchars($shortname);
}

require(LIBWWWDIR . '/header.php');
echo "<h1>" . specialchars($title) . "</h1>\n\n";

if (empty($cid)) {
    echo "<p class=\"nodata\">No active contest available</p>\n\n";

    require(LIBWWWDIR . '/footer.php');
    exit;
}

$res = $DB->q('
SELECT
    COUNT(result) as count,
    (FLOOR(submittime - c.starttime) DIV %i) * %i AS minute,' .
    ((!isset($unfrozen) || !$unfrozen) ?
        'if(c.freezetime IS NOT NULL && submittime >= freezetime,
        "frozen", result)' : 'result') . ' as fresult
FROM
    submission s
    JOIN judging j ON(s.submitid=j.submitid AND j.valid=1)
    LEFT OUTER JOIN contest c ON(c.cid=j.cid)
    LEFT OUTER JOIN team t USING(teamid)
    LEFT JOIN contestteam ct ON(t.teamid=ct.teamid AND c.cid=ct.cid)
    LEFT JOIN team_category USING (categoryid)
    LEFT JOIN team_affiliation USING (affilid)
WHERE
    t.enabled = 1 AND
    visible = 1 AND
    (ct.teamid IS NOT NULL OR c.public = 1) AND
    s.cid = %i AND s.valid = 1 ' .
    (isset($probid) ? 'AND s.probid = %i ' : '%_') .
    'AND submittime < c.endtime AND submittime >= c.starttime
    GROUP BY minute, fresult', $bar_size * 60, $bar_size, $cid, $probid);

// All problems
$problems = $DB->q('SELECT p.probid,p.name FROM problem p
                    INNER JOIN contestproblem USING (probid)
                    WHERE cid = %i ORDER by shortname', $cid);

function relativeUrl($params)
{
    $params = array_merge($_GET, $params);
    return strtok($_SERVER["REQUEST_URI"], '?') . '?' .
        http_build_query($params);
}

print '<p>';
print '<a href="statistics.php">All problems</a>&nbsp;&nbsp;&nbsp;';
while ($row = $problems->next()) {
    print '<a href="' .
        relativeUrl(array('unfrozen' => !!$unfrozen, 'probid' => $row['probid'])) . '">' .
        specialchars($row['name']) . '</a>&nbsp;&nbsp;&nbsp;';
}
print '</p>';
print('<a href="' . relativeUrl(array("unfrozen" => !$unfrozen)) . '">Toggle freeze</a>');
print '</p>';

// Contest information
$start = $cdata['starttime'];
$end = $cdata['endtime'];
$length = ($end - $start) / 60;
?>


<div id="placeholder" style="width:1000px;height:400px;"></div>

<script id="source">
var data = <?= json_encode($res->gettable()); ?>;
var contestlen = <?= $length; ?>;

$(function () {
    var answers = [
        {label : "correct", color : "#01DF01", bars : { fill: 1 } },
        {label : "wrong-answer", color : "red", bars : { fill: 0.6} },
        {label : "timelimit", color : "orange", bars : { fill: 0.6} },
        {label : "run-error", color : "#FF3399", bars : { fill: 0.6} },
        {label : "compiler-error", color : "grey", bars : { fill: 0.6 }, },
        {label : "no-output", color : "purple", bars : { fill: 0.6 } },
        {label : "frozen", color : "blue", bars : { fill: 0.6 } },
    ];
    var charts = [];
    for(var i = 0; i < answers.length; i++) {
        var cur = [];
        for(var j = 0; j < contestlen / <?= $bar_size ?>; j++)
            cur.push([j * <?= $bar_size ?>,0]);
        var answer = answers[i].label;
        for(var j = 0; j < data.length; j++) {
            if(data[j].fresult == answer) {
                cur[parseInt(data[j].minute) / <?= $bar_size ?>][1] = parseInt(data[j].count);
            }
        }
        var newchart = answers[i];
        newchart.data = cur;
        charts.push(newchart);
    }
    var n = charts.length;
    var stack = d3.layout.stack();
    var layers = stack(d3.range(n).map(function(i){return charts[i].data.map(function(a){return {x:a[0], y:a[1]};});}));
    var yStackMax = d3.max(layers, function(layer) {
            return d3.max(layer, function(d) {
                return d.y0 + d.y;
            });
    });
    var xMax = d3.max(layers, function(layer) {
        return d3.max(layer, function(d) {
            return d.x;
        });
    });

    var placeholder = d3.select("#placeholder");
    var realWidth = parseFloat(placeholder.style("width"));
    var realHeight = parseFloat(placeholder.style("height"));
    var margin = {
            top: 12,
            right: 15,
            bottom: 22,
            left: 30
        };
    var width = realWidth - margin.left - margin.right;
    var height = realHeight - margin.top - margin.bottom;

    var x = d3.scale.linear()
        .domain([0, contestlen])
        .range([0, width]);

    var yTicks = Math.min(5,yStackMax);
    var xTicks = 7;
    var y = d3.scale.linear()
        .domain([0, yStackMax*1.05])
        .range([height, 0])
        .nice(yTicks);

    var svg = placeholder.append("svg")
        .attr("id", "stats_graph")
        .attr("width", "100%")
        .attr("height", "100%")
        .append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

    svg.append("defs").append("svg:clipPath")
        .attr("id", "clip")
        .append("svg:rect")
            .attr("id", "clip-rect")
            .attr("x", 0)
            .attr("y", 0)
            .attr("width", width)
            .attr("height", height);

    var xAxis = d3.svg.axis()
        .scale(x)
        .ticks(xTicks)
        .tickSize(-height)
        .tickPadding(6)
        .orient("bottom");

    var yAxis = d3.svg.axis()
        .scale(y)
        .ticks(yTicks)
        .tickFormat(d3.format("d"))
        .tickSize(-width)
        .tickPadding(6)
        .orient("left");

    svg.append("g")
        .attr("class", "x axis")
        .attr("transform", "translate(0," + height + ")")
        .call(xAxis);

    svg.append("g")
        .attr("class", "y axis")
        .attr("transform", "translate(0,0)")
        .call(yAxis);

    var layer = svg.selectAll(".layer")
        .data(layers)
        .enter().append("g")
        .attr("class", "layer")
        .style("fill", function(d, i) {
            return charts[i].color;
        })
        .style("fill-opacity", function(d, i) {
            return charts[i].bars.fill;
        }).attr("clip-path", "url(#clip)");

    var gap = 0.1;
    var rangeGap = x(<?= $bar_size ?>) - x(0);
    var rect = layer.selectAll("rect")
        .data(function(d) {
            return d;
        })
        .enter().append("rect")
        .attr("x", function(d) {
            return x(d.x+gap*<?= $bar_size ?>);
        })
        .attr("y", function(d) {
            return y(d.y0 + d.y);
        })
        .attr("width", (1-2*gap)*rangeGap)
        .attr("height", function(d) {
            return y(d.y0) - y(d.y0 + d.y);
        });

    svg.append("rect")
        .attr("x", 0)
        .attr("y", 0)
        .attr("height", height)
        .attr("width", width)
        .style("stroke", "black")
        .style("fill", "none")
        .style("stroke-width", 1);

    var textstyle = "13px 'DejaVu Sans',Arial";

    svg.selectAll(".axis")
        .style("fill", "none")
        .style("stroke", "black")
        .style("stroke-opacity", 0.15);
    svg.selectAll("text")
        .style("font", textstyle)
        .style("stroke", "none")
        .style("fill", "black");
    var caption = d3.select("#stats_graph")
        .append("g")
        .attr("transform", "translate(" + (margin.left + 15) + "," + (margin.top + 8) + ")")
    var captionBackground = caption.append("rect")

    hdist = 18;
    var off = 0;
    charts.forEach(function(c) {
        off += hdist;
        var gap = 0.35;
        caption.append("rect")
            .attr("y",off-hdist + hdist*gap)
            .attr("x",0)
            .attr("width", 15)
            .attr("height", hdist*(1-gap))
            .style("fill", c.color);
        caption.append("text")
            .text(c.label)
            .attr("y",off)
            .attr("x",20)
            .style("font", textstyle)
    });

    var bbox = caption.node().getBBox();
    var captionMargin = 7;
    captionBackground
        .attr("width", bbox.width + 2*captionMargin)
        .attr("height", bbox.height + 2*captionMargin)
        .attr("x", bbox.x - captionMargin)
        .attr("y", bbox.y - captionMargin)
        .style("fill", "rgb(255,255,255)")
        .style("fill-opacity", 0.7);

    function utf8_to_b64(str) {
        return window.btoa(unescape(encodeURIComponent(str)));
    }

    placeholder.append("br");
    placeholder.append("a")
        .attr("download", "<?=isset($shortname) ? $shortname . "_" : ""?>stats.svg")
        .attr("href", "data:image/svg+xml;base64," +
            utf8_to_b64("<svg xmlns=\"http://www.w3.org/2000/svg\" width='" + $("#placeholder").width() +
            "' height='" + $("#placeholder").height()+ "'>" +
            new XMLSerializer().serializeToString(document.getElementById('stats_graph')) + "</svg>"))
        .text("download graph");
    placeholder.append("p")
        .text("If the download link does not work for you, try a recent Firefox or Chrome.");
});
</script>
<?php
require(LIBWWWDIR . '/footer.php');
