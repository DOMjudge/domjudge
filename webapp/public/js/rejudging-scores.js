'use strict';

/**
 * Score changes visualization for rejudgings.
 * Provides a heatmap visualization, delta filtering, and sortable table.
 */

// Configuration constants
let PRECISION = 0.0001; // Default, will be overridden by contest setting
const DELTA_THRESHOLDS = [0.0001, 0.0005, 0.0025, 0.01, 0.05, 0.25, 1, 2, 5, 25, 100, 250];
const NUM_X_BINS = 20;
const SLIDER_DEBOUNCE_MS = 50;

/**
 * Debounce helper to limit function call frequency with cleanup support.
 */
function debounce(func, wait)
{
    let timeout;
    const debounced = function() {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            func.apply(context, args);
        }, wait);
    };
    debounced.cancel = function() {
        clearTimeout(timeout);
    };
    return debounced;
}

/**
 * Initialize score changes visualization.
 */
function initScoreChangesVisualization()
{
    // Support both shadow mode (score-comparisons-data) and rejudging (score-changes-data)
    const dataElement = document.getElementById('score-comparisons-data')
        || document.getElementById('score-changes-data');
    const maxScoreElement = document.getElementById('max-score-data');
    const epsilonElement = document.getElementById('score-diff-epsilon-data');

    if (!dataElement || !maxScoreElement) {
        return;
    }

    let scoreComparisonsData;
    let maxScore;
    try {
        scoreComparisonsData = JSON.parse(dataElement.textContent || '[]');
        maxScore = JSON.parse(maxScoreElement.textContent || '0');
        if (epsilonElement) {
            const epsilon = JSON.parse(epsilonElement.textContent || '0.0001');
            if (typeof epsilon === 'number' && !isNaN(epsilon) && epsilon > 0) {
                PRECISION = epsilon;
            }
        }
    } catch (e) {
        console.error('Failed to parse score changes data:', e);
        return;
    }

    // Validate parsed data
    if (!Array.isArray(scoreComparisonsData)) {
        console.error('Score changes data is not an array');
        return;
    }
    if (typeof maxScore !== 'number' || isNaN(maxScore)) {
        maxScore = 0;
    }

    createScoreHeatmap(scoreComparisonsData, maxScore);
    const cleanupFilter = initDeltaFilter(scoreComparisonsData);
    const cleanupSorting = initTableSorting();
    
    // Store cleanup functions for potential later use
    window.scoreVisualizationCleanup = function() {
        if (cleanupFilter) cleanupFilter();
        if (cleanupSorting) cleanupSorting();
    };
}

/**
 * Create the score changes heatmap using d3.js.
 */
function createScoreHeatmap(data, maxScore)
{
    const container = document.getElementById('score-heatmap');
    if (!container) {
        return;
    }

    const width = container.clientWidth || 600;
    const height = 500;
    const margin = {top: 40, right: 80, bottom: 60, left: 80};
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;

    // Clear existing content
    d3.select('#score-heatmap').selectAll('*').remove();

    const svg = d3.select('#score-heatmap')
        .append('svg')
        .attr('width', width)
        .attr('height', height);

    const g = svg.append('g')
        .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

    if (maxScore <= 0 || data.length === 0) {
        g.append('text')
            .attr('x', plotWidth / 2)
            .attr('y', plotHeight / 2)
            .attr('text-anchor', 'middle')
            .text('No score data available');
        return;
    }

    const numDeltaBinsOneSide = DELTA_THRESHOLDS.length;
    const totalYBins = numDeltaBinsOneSide * 2 + 1;
    const centerIdx = numDeltaBinsOneSide;

    let actualMaxOldScore = d3.max(data, function(d) { return d.oldScore; });
    // Guard against null, zero, or negative max scores
    if (actualMaxOldScore === null || actualMaxOldScore <= 0) {
        actualMaxOldScore = 1;
    }
    const xBinSize = actualMaxOldScore / NUM_X_BINS;

    // Create bins
    const bins = createBins(totalYBins, xBinSize, centerIdx, maxScore);
    populateBins(bins, data, xBinSize, centerIdx, totalYBins);

    const cellWidth = plotWidth / NUM_X_BINS;
    const cellHeight = plotHeight / totalYBins;

    // Background
    g.append('rect')
        .attr('width', plotWidth)
        .attr('height', plotHeight)
        .attr('fill', '#f8f9fa');

    // Center line (zero delta)
    g.append('line')
        .attr('x1', 0)
        .attr('y1', plotHeight - (centerIdx + 0.5) * cellHeight)
        .attr('x2', plotWidth)
        .attr('y2', plotHeight - (centerIdx + 0.5) * cellHeight)
        .attr('stroke', '#adb5bd')
        .attr('stroke-width', 1)
        .attr('stroke-dasharray', '4,2');

    // Tooltip
    const tooltip = d3.select('#score-heatmap').append('div')
        .attr('class', 'heatmap-tooltip');

    // Flatten bins for d3
    const flatBins = [];
    bins.forEach(function(row) {
        row.forEach(function(cell) {
            flatBins.push(cell);
        });
    });

    // Draw cells
    const cells = g.selectAll('.heatmap-cell')
        .data(flatBins)
        .enter()
        .append('g');

    cells.append('rect')
        .attr('class', 'heatmap-cell')
        .attr('x', function(d) { return d.xBin * cellWidth; })
        .attr('y', function(d) { return plotHeight - (d.yBin + 1) * cellHeight; })
        .attr('width', cellWidth - 1)
        .attr('height', cellHeight - 1)
        .attr('rx', 2)
        .attr('fill', function(d) { return getCellColor(d.count, d.yBin, centerIdx, numDeltaBinsOneSide); })
        .style('cursor', function(d) { return d.count > 0 ? 'pointer' : 'default'; })
        .on('mouseover', function(d) {
            if (d.count > 0) {
                d3.select(this).attr('stroke', '#000');
                showTooltip(tooltip, d, centerIdx);
            }
        })
        .on('mousemove', function() {
            try {
                const coords = d3.mouse(container);
                if (coords) {
                    tooltip
                        .style('top', (coords[1] + 10) + 'px')
                        .style('left', (coords[0] + 10) + 'px');
                }
            } catch (e) {
                // d3.mouse can throw if container is not in DOM
            }
        })
        .on('mouseout', function() {
            d3.select(this).attr('stroke', null);
            tooltip.style('visibility', 'hidden');
        })
        .on('click', function(d) {
            if (d.count > 0) {
                highlightTableRows(d.submissions);
                const table = document.getElementById('score-changes-table');
                if (table) {
                    table.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });

    // Cell labels
    cells.filter(function(d) { return d.count > 0; })
        .append('text')
        .attr('x', function(d) { return d.xBin * cellWidth + cellWidth / 2; })
        .attr('y', function(d) { return plotHeight - (d.yBin + 1) * cellHeight + cellHeight / 2; })
        .attr('text-anchor', 'middle')
        .attr('dominant-baseline', 'middle')
        .attr('font-size', '12px')
        .attr('fill', '#000')
        .attr('pointer-events', 'none')
        .text(function(d) { return d.count; });

    // X axis
    const xScale = d3.scale.linear().domain([0, actualMaxOldScore]).range([0, plotWidth]);
    const xAxis = d3.svg.axis().scale(xScale).orient('bottom').ticks(5);
    const xAxisG = g.append('g')
        .attr('transform', 'translate(0,' + plotHeight + ')')
        .call(xAxis);
    xAxisG.select('.domain').remove();

    g.append('text')
        .attr('x', plotWidth / 2)
        .attr('y', plotHeight + 40)
        .attr('text-anchor', 'middle')
        .style('font-weight', 'bold')
        .text('Old Score');

    // Y axis labels
    drawYAxisLabels(g, plotHeight, cellHeight, totalYBins, centerIdx);

    g.append('text')
        .attr('transform', 'rotate(-90)')
        .attr('x', -plotHeight / 2)
        .attr('y', -55)
        .attr('text-anchor', 'middle')
        .style('font-weight', 'bold')
        .text('Score Delta');
}

/**
 * Create empty bins structure.
 */
function createBins(totalYBins, xBinSize, centerIdx, maxScore)
{
    const bins = [];
    for (let x = 0; x < NUM_X_BINS; x++) {
        bins[x] = [];
        for (let y = 0; y < totalYBins; y++) {
            bins[x][y] = {
                xBin: x,
                yBin: y,
                count: 0,
                submissions: [],
                xRange: [x * xBinSize, (x + 1) * xBinSize],
                yDeltaRange: getDeltaRange(y, centerIdx, maxScore)
            };
        }
    }
    return bins;
}

/**
 * Populate bins with data.
 */
function populateBins(bins, data, xBinSize, centerIdx, totalYBins)
{
    data.forEach(function(d) {
        const xIdx = Math.min(Math.floor(d.oldScore / xBinSize), NUM_X_BINS - 1);
        const yIdx = getDeltaBin(d.delta, centerIdx);
        if (xIdx >= 0 && yIdx >= 0 && yIdx < totalYBins) {
            bins[xIdx][yIdx].count++;
            bins[xIdx][yIdx].submissions.push(d);
        }
    });
}

/**
 * Get the bin index for a given delta value.
 */
function getDeltaBin(delta, centerIdx)
{
    if (Math.abs(delta) < PRECISION) {
        return centerIdx;
    }
    const absDelta = Math.abs(delta);
    let bin = 0;
    for (let i = 0; i < DELTA_THRESHOLDS.length; i++) {
        if (absDelta >= DELTA_THRESHOLDS[i]) {
            bin = i + 1;
        } else {
            break;
        }
    }
    return delta > 0 ? centerIdx + bin : centerIdx - bin;
}

/**
 * Get the delta range for a bin index.
 */
function getDeltaRange(binIdx, centerIdx, maxScore)
{
    if (binIdx === centerIdx) {
        return [0, PRECISION];
    }
    const sideIdx = Math.abs(binIdx - centerIdx) - 1;
    const lower = DELTA_THRESHOLDS[sideIdx];
    const upper = DELTA_THRESHOLDS[sideIdx + 1] || maxScore;
    return [lower, upper];
}

/**
 * Get cell background color based on count and position.
 */
function getCellColor(count, yBin, centerIdx, numDeltaBinsOneSide)
{
    if (count === 0) {
        return 'transparent';
    }
    if (yBin === centerIdx) {
        return '#dfd';
    }

    return '#fdd';
}

/**
 * Show tooltip with bin information.
 * Uses DOM methods instead of .html() to prevent XSS.
 */
function showTooltip(tooltip, d, centerIdx)
{
    const label = d.yBin > centerIdx ? 'Improved' : (d.yBin < centerIdx ? 'Degraded' : 'Unchanged');
    let deltaText;
    if (d.yBin === centerIdx) {
        deltaText = `< ${PRECISION}`;
    } else {
        const sign = d.yBin > centerIdx ? '+' : '-';
        deltaText = `${sign}${d.yDeltaRange[0].toFixed(4)} to ${sign}${d.yDeltaRange[1].toFixed(4)}`;
    }

    // Build tooltip content safely using DOM methods
    const tooltipNode = tooltip.node();
    tooltipNode.textContent = '';

    const strong = document.createElement('strong');
    strong.textContent = label;
    tooltipNode.appendChild(strong);
    tooltipNode.appendChild(document.createElement('br'));
    tooltipNode.appendChild(document.createTextNode(`Old Score: ${d.xRange[0].toFixed(3)}-${d.xRange[1].toFixed(3)}`));
    tooltipNode.appendChild(document.createElement('br'));
    tooltipNode.appendChild(document.createTextNode(`Delta: ${deltaText}`));
    tooltipNode.appendChild(document.createElement('br'));
    tooltipNode.appendChild(document.createTextNode(`${d.count} submission(s)`));

    tooltip.style('visibility', 'visible');
}

/**
 * Draw Y axis labels.
 */
function drawYAxisLabels(g, plotHeight, cellHeight, totalYBins, centerIdx)
{
    const yAxisG = g.append('g');

    for (let idx = 0; idx < totalYBins; idx++) {
        let label;
        if (idx === centerIdx) {
            label = '0';
        } else {
            const sideIdx = Math.abs(idx - centerIdx) - 1;
            const val = DELTA_THRESHOLDS[sideIdx];
            const sign = idx > centerIdx ? '+' : '-';
            label = `${sign}${val.toFixed(4)}`;
        }

        yAxisG.append('text')
            .attr('x', -10)
            .attr('y', plotHeight - (idx + 0.5) * cellHeight)
            .attr('text-anchor', 'end')
            .attr('dominant-baseline', 'middle')
            .style('font-size', '9px')
            .style('fill', '#495057')
            .text(label);
    }
}

/**
 * Highlight table rows for selected submissions.
 */
function highlightTableRows(submissions)
{
    const submitIds = submissions.map(function(s) { return s.submitId; });

    $('#score-changes-tbody tr').each(function() {
        const row = $(this);
        const submitId = row.data('submit-id');
        if (submitIds.indexOf(submitId) !== -1) {
            row.addClass('table-warning');
        } else {
            row.removeClass('table-warning');
        }
    });
}

/**
 * Initialize the delta threshold filter slider.
 * Returns cleanup function for memory management.
 */
function initDeltaFilter(data)
{
    const slider = document.getElementById('delta-threshold');
    const valueDisplay = document.getElementById('delta-threshold-value');
    const countDisplay = document.getElementById('filtered-count');

    if (!slider || !valueDisplay || !countDisplay) {
        return;
    }

    const totalCount = data.length;

    // Set slider max to the maximum delta in the data
    // Use iteration to avoid stack overflow with large arrays
    let maxDelta = 0;
    for (let i = 0; i < data.length; i++) {
        if (data[i].absDelta > maxDelta) {
            maxDelta = data[i].absDelta;
        }
    }
    slider.max = Math.max(1, Math.ceil(maxDelta));

    // Cache the rows for better performance
    const $rows = $('#score-changes-tbody .score-change-row');

    const updateFilter = debounce(function(sliderValue) {
        let threshold = parseFloat(sliderValue);
        if (isNaN(threshold)) {
            threshold = 0;
        }
        valueDisplay.textContent = threshold.toFixed(1);

        let visibleCount = 0;
        let totalChangedCount = 0;
        $rows.each(function() {
            let absDelta = parseFloat($(this).data('abs-delta'));
            // Treat NaN as 0 (show by default if threshold is 0)
            if (isNaN(absDelta)) {
                absDelta = 0;
            }
            
            // Count total submissions with actual changes (delta > precision threshold)
            if (absDelta > PRECISION) {
                totalChangedCount++;
            }
            
            // Show only if meets threshold AND has actual changes
            if (absDelta >= threshold && absDelta > PRECISION) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });

        countDisplay.textContent = '';
        countDisplay.appendChild(document.createTextNode('showing '));
        const strong = document.createElement('strong');
        strong.textContent = visibleCount;
        countDisplay.appendChild(strong);
        countDisplay.appendChild(document.createTextNode(` of ${totalChangedCount} submissions with score changes`));
    }, SLIDER_DEBOUNCE_MS);

    const inputHandler = function() {
        updateFilter(this.value);
    };
    
    slider.addEventListener('input', inputHandler);
    
    // Initial filter: hide unchanged submissions and show all with changes
    updateFilter(0);
    
    // Return cleanup function
    return function() {
        slider.removeEventListener('input', inputHandler);
        updateFilter.cancel();
        // Clear jQuery references
        $rows.length = 0;
    };
}

/**
 * Initialize sortable table headers.
 * Returns cleanup function for memory management.
 */
function initTableSorting()
{
    const currentSort = { column: 'absDelta', ascending: false }; // Default: absDelta descending

    $('#score-changes-table thead th.sortable').on('click', function() {
        const column = $(this).data('sort');

        // Toggle sort direction if same column
        if (currentSort.column === column) {
            currentSort.ascending = !currentSort.ascending;
        } else {
            currentSort.column = column;
            currentSort.ascending = true;
        }

        // Update header indicators
        $('#score-changes-table thead th').removeClass('sorted-asc sorted-desc');
        $(this).addClass(currentSort.ascending ? 'sorted-asc' : 'sorted-desc');

        // Sort the table
        const tbody = $('#score-changes-tbody');
        const rows = tbody.find('tr').get();

        rows.sort(function(a, b) {
            const aVal = getRowSortValue(a, column);
            const bVal = getRowSortValue(b, column);

            if (typeof aVal === 'string') {
                return currentSort.ascending ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
            }
            return currentSort.ascending ? aVal - bVal : bVal - aVal;
        });

        $.each(rows, function(idx, row) {
            tbody.append(row);
        });
    });

    // Perform initial sort by absDelta descending
    function performInitialSort() {
        const tbody = $('#score-changes-tbody');
        const rows = tbody.find('tr').get();

        rows.sort(function(a, b) {
            const aVal = getRowSortValue(a, 'absDelta');
            const bVal = getRowSortValue(b, 'absDelta');
            return bVal - aVal; // Descending order
        });

        $.each(rows, function(idx, row) {
            tbody.append(row);
        });
    }

    // Initial sort on page load
    performInitialSort();

    // Return cleanup function
    return function() {
        $('#score-changes-table thead th.sortable').off('click');
        // Clear jQuery references
        rows.length = 0;
    };
}

/**
 * Helper to parse numeric value from text content with fallback.
 */
function parseNumericValue(text) {
    const val = parseFloat(text);
    return isNaN(val) ? 0 : val;
}

/**
 * Helper to get text content from table cell with fallback.
 */
function getCellText($row, cellIndex) {
    return ($row.find(`td:eq(${cellIndex})`).text() || '').toLowerCase();
}

/**
 * Get sort value from a table row.
 * Returns 0 for numeric columns if parsing fails.
 */
function getRowSortValue(row, column)
{
    const $row = $(row);
    let val;
    switch (column) {
        case 'submitId':
            val = $row.data('submit-id');
            return typeof val === 'number' ? val : 0;
        case 'teamName':
            return getCellText($row, 1);
        case 'problemName':
            return getCellText($row, 2);
        case 'oldScore':
            return parseNumericValue($row.find('td:eq(3)').text());
        case 'newScore':
            return parseNumericValue($row.find('td:eq(4)').text());
        case 'delta':
            return parseNumericValue($row.find('td:eq(5)').text());
        case 'absDelta':
            val = $row.data('abs-delta');
            return typeof val === 'number' ? val : 0;
        default:
            return '';
    }
}
