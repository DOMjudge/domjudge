function initJudgehostTimeline(dataUrl) {
    const margin = {top: 20, right: 30, bottom: 40, left: 150};
    const $container = $('#judgehost-timeline');
    const fullWidth = $container.width();
    const fullHeight = $container.height();
    const width = fullWidth - margin.left - margin.right;
    const height = fullHeight - margin.top - margin.bottom;

    const svg = d3.select("#judgehost-timeline svg")
        .attr("width", fullWidth)
        .attr("height", fullHeight);

    // Clear previous content if any (for re-init)
    svg.selectAll("*").remove();

    const container = svg.append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

    // Clip path for zooming
    svg.append("defs").append("clipPath")
        .attr("id", "clip")
      .append("rect")
        .attr("width", width)
        .attr("height", height);

    const mainContent = container.append("g")
        .attr("clip-path", "url(#clip)");

    let tooltip = d3.select(".timeline-tooltip");
    if (tooltip.empty()) {
        tooltip = d3.select("body").append("div")
            .attr("class", "timeline-tooltip")
            .style("opacity", 0);
    }

    d3.json(dataUrl, function(error, json) {
        if (error) {
            console.error("Error loading timeline data:", error);
            return;
        }

        const allData = json.data;
        const now = json.now * 1000;
        const start = json.twenty_four_hours_ago * 1000;

        allData.forEach(function(d) {
            d.startTime = +d.startTime * 1000;
            d.endtime = +d.endtime * 1000;
        });

        function render() {
            const showDisabled = $('#toggle-disabled-jh').is(':checked');
            
            // Filter judgehosts
            const activeJudgehosts = [];
            for (const id in json.judgehosts) {
                const jh = json.judgehosts[id];
                if (showDisabled || jh.enabled) {
                    activeJudgehosts.push({id: id, name: jh.hostname});
                }
            }
            activeJudgehosts.sort(function(a, b) { return d3.ascending(a.name, b.name); });

            // Filter data to only include judgehosts being shown
            const hostNames = new Set(activeJudgehosts.map(d => d.name));
            const data = allData.filter(d => hostNames.has(d.hostname));

            container.selectAll(".timeline-axis").remove();
            mainContent.selectAll(".timeline-rect").remove();

            const minStart = d3.min(data, function(d) { return d.startTime; });
            let displayStart = start;
            if (minStart) {
                displayStart = Math.max(start, minStart - 1 * 60 * 1000);
            }

            const x = d3.time.scale()
                .domain([displayStart, now])
                .range([0, width]);

            const y = d3.scale.ordinal()
                .domain(activeJudgehosts.map(function(d) { return d.name; }))
                .rangeRoundBands([0, height], 0.1);

            const xAxis = d3.svg.axis()
                .scale(x)
                .orient("bottom")
                .tickFormat(d3.time.format("%H:%M"));

            const yAxis = d3.svg.axis()
                .scale(y)
                .orient("left");

            container.append("g")
                .attr("class", "timeline-axis timeline-y-axis")
                .call(yAxis);

            const xAxisGroup = container.append("g")
                .attr("class", "timeline-axis timeline-x-axis")
                .attr("transform", "translate(0," + height + ")")
                .call(xAxis);

            const zoom = d3.behavior.zoom()
                .x(x)
                .scaleExtent([1, 1000])
                .on("zoom", function() {
                    xAxisGroup.call(xAxis);
                    mainContent.selectAll(".timeline-rect")
                        .attr("x", function(d) { return x(d.startTime); })
                        .attr("width", function(d) { return Math.max(1, x(d.endtime) - x(d.startTime)); });
                });

            svg.call(zoom);

            const colors = d3.scale.category20();

            mainContent.selectAll(".timeline-rect")
                .data(data)
                .enter().append("rect")
                .attr("class", "timeline-rect")
                .attr("x", function(d) { return x(d.startTime); })
                .attr("y", function(d) { return y(d.hostname); })
                .attr("width", function(d) { return Math.max(1, x(d.endtime) - x(d.startTime)); })
                .attr("height", y.rangeBand())
                .attr("fill", function(d) { return colors(d.jobid % 20); })
                .style("pointer-events", "all")
                .on("mouseover", function(d) {
                    tooltip.transition().duration(200).style("opacity", .9);
                    const duration = ((d.endtime - d.startTime) / 1000).toFixed(3);
                    tooltip.html(
                        "<b>Contest:</b> " + d.contest_shortname + "<br/>" +
                        "<b>Problem:</b> " + d.problem_name + "<br/>" +
                        "<b>Submission:</b> s" + d.submitid + " (j" + d.jobid + ")<br/>" +
                        "<b>Result:</b> " + (d.runresult || 'judging') + "<br/>" +
                        "<b>Duration:</b> " + duration + "s"
                    )
                    .style("left", (d3.event.pageX + 10) + "px")
                    .style("top", (d3.event.pageY - 28) + "px");
                })
                .on("mousemove", function() {
                    tooltip.style("left", (d3.event.pageX + 10) + "px")
                           .style("top", (d3.event.pageY - 28) + "px");
                })
                .on("mouseout", function() {
                    tooltip.transition().duration(500).style("opacity", 0);
                })
                .on("click", function(d) {
                    window.location.href = domjudge_base_url + "/jury/submissions/" + d.submitid;
                });
        }

        render();
        $('#toggle-disabled-jh').on('change', render);
    });
}
