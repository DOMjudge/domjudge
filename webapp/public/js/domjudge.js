'use strict';

function enableNotifications()
{
    if ( !('Notification' in window) ) {
        alert('Your browser does not support desktop notifications.');
        return false;
    }
    if ( !('localStorage' in window) || window.localStorage===null ) {
        alert('Your browser does not support local storage;\n'+
              'this is required to keep track of sent notifications.');
        return false;
    }
    // Ask user (via browser) for permission if not already granted.
    if ( Notification.permission==='denied' ) {
        alert('Browser denied permission to send desktop notifications.\n' +
              'Re-enable notification permission in the browser and retry.');
        return false;
    }

    if ( Notification.permission!=='granted' ) {
        Notification.requestPermission(function (permission) {
            // Safari and Chrome don't support the static 'permission'
            // variable, so in this case we set it ourselves.
            if ( !('permission' in Notification) ) {
                Notification.permission = permission;
            }
            if ( Notification.permission!=='granted' ) {
                alert('Browser denied permission to send desktop notifications.');
                return false;
            }
        });
    }

    setCookie('domjudge_notify', 1);
    sendNotification('DOMjudge notifications enabled.');
    $("#notify_disable").removeClass('d-none');
    $("#notify_disable").show();
    $("#notify_enable").hide();
    return true;
}

function disableNotifications()
{
    setCookie('domjudge_notify', 0);
    $("#notify_enable").removeClass('d-none');
    $("#notify_enable").show();
    $("#notify_disable").hide();
    return true;
}

// Send a notification if notifications have been enabled.
// The options argument is passed to the Notification constructor,
// except that the following tags (if found) are interpreted and
// removed from options:
// * link       URL to redirect to on click, relative to DOMjudge base
//
// We use HTML5 localStorage to keep track of which notifications the
// client has already received to display each notification only once.
function sendNotification(title, options = {})
{
    if ( getCookie('domjudge_notify')!=1 ) return;

    // Check if we already sent this notification:
    var senttags = localStorage.getItem('domjudge_notifications_sent');
    if ( senttags===null || senttags==='' ) {
        senttags = [];
    } else {
        senttags = senttags.split(',');
    }
    if ( options.tag!==null && senttags.indexOf(options.tag)>=0 ) { return; }

    var link = null;
    if ( typeof options.link !== 'undefined' ) {
        link = options.link;
        delete options.link;
    }
    options['icon'] = domjudge_base_url + '/apple-touch-icon.png';

    var not = new Notification(title, options);

    if ( link!==null ) {
        not.onclick = function() { window.open(link); };
    }

    if ( options.tag!==null ) {
        senttags.push(options.tag);
        localStorage.setItem('domjudge_notifications_sent',senttags.join(','));
    }
}

var doReload = true;

function reloadPage()
{
    if (doReload) {
        window.location.reload();
    }
}

function initReload(refreshtime)
{
    // interval is in seconds
    setTimeout(function() { reloadPage(); }, refreshtime * 1000);
}

function collapse(x)
{
    $(x).toggleClass('d-none');
}

function togglelastruns()
{
    var names = {'lastruntime':0, 'lastresult':1, 'lasttcruns':2};
    for (var name in names) {
        var cells = document.getElementsByClassName(name);
        for (var i = 0; i < cells.length; i++) {
            var style = 'inline';
            if (name === 'lasttcruns') {
                style = 'table-row';
            }
            cells[i].style.display = (cells[i].style.display === 'none') ? style : 'none';
        }
    }
}

// TODO: We should probably reload the page if the clock hits contest
// start (and end?).
function updateClock()
{
    var curtime = Math.round((new Date().getTime() - clientOffset) / 1000);

    var fmt = "";
    if ( timeleftelt.innerHTML=='start delayed' || timeleft.innerHTML == 'no contest' ) { // FIXME
        var left = 0;
        var what = timeleftelt.innerHTML;
    } else if (curtime >= starttime && curtime < endtime ) {
        var left = endtime - curtime;
        var what = "";
    } else if (curtime >= activatetime && curtime < starttime ) {
        var left = starttime - curtime;
        var what = "time to start: ";
    } else {
        var left = 0;
        var what = "contest over";
    }

    if ( left ) {
        if ( left > 24*60*60 ) {
            var d = Math.floor(left/(24*60*60));
            fmt += d + "d ";
            left -= d * 24*60*60;
        }
        if ( left > 60*60 ) {
            var h = Math.floor(left/(60*60));
            fmt += h + ":";
            left -= h * 60*60;
        }
        var m = Math.floor(left/60);
        if ( m < 10 ) { fmt += "0"; }
        fmt += m + ":";
        left -= m * 60;
        if ( left < 10 ) { fmt += "0"; }
        fmt += left;
    }

    timeleftelt.innerHTML = what + fmt;
}

function setCookie(name, value)
{
    var expire = new Date();
    expire.setDate(expire.getDate() + 3); // three days valid
    document.cookie = name + "=" + escape(value) + "; expires=" + expire.toUTCString();
}

function getCookie(name)
{
    var cookies = document.cookie.split(";");
    for (var i = 0; i < cookies.length; i++) {
        var idx = cookies[i].indexOf("=");
        var key = cookies[i].substr(0, idx);
        var value = cookies[i].substr(idx+1);
        key = key.replace(/^\s+|\s+$/g,""); // trim
        if (key === name) {
            return unescape(value);
        }
    }
    return "";
}

function getSelectedTeams()
{
    var cookieVal = getCookie("domjudge_teamselection");
    if (cookieVal === null || cookieVal === "") {
        return new Array();
    }
    return JSON.parse(cookieVal);
}

function getScoreboard()
{
    var scoreboard = document.getElementsByClassName("scoreboard");
    if (scoreboard === null || scoreboard[0] === null || scoreboard[0] === undefined) {
        return null;
    }
    return scoreboard[0].rows;
}

function getRank(row)
{
    return row.getElementsByTagName("td")[0];
}

function getHeartCol(row) {
    var tds = row.getElementsByTagName("td");
    var td = null;
    // search for td before the team name
    for (var i = 1; i < 4; i++) {
        if (tds[i].className == "scoretn") {
            td = tds[i - 1];
            break;
        }
    }
    if (td === null) {
        td = tds[1];
    }
    if (td !== null) {
        if (td.children.length) {
            return td.children[0];
        }
        return td;
    }

    return null;
}

function getTeamname(row)
{
    var res = row.getAttribute("id");
    if ( res === null ) return res;
    return res.replace(/^team:/, '');
}

function toggle(id, show)
{
    var scoreboard = getScoreboard();
    if (scoreboard === null) return;

    var favTeams = getSelectedTeams();
    // count visible favourite teams (if filtered)
    var visCnt = 0;
    for (var i = 0; i < favTeams.length; i++) {
        for (var j = 0; j < scoreboard.length; j++) {
            var scoreTeamname = getTeamname(scoreboard[j]);
            if (scoreTeamname === null) {
                continue;
            }
            if (scoreTeamname === favTeams[i]) {
                visCnt++;
                break;
            }
        }
    }
    var teamname = getTeamname(scoreboard[id + visCnt]);
    if (show) {
        favTeams[favTeams.length] = teamname;
    } else {
        // copy all other teams
        var newFavTeams = new Array();
        for (var i = 0; i < favTeams.length; i++) {
            if (favTeams[i] !== teamname) {
                newFavTeams[newFavTeams.length] = favTeams[i];
            }
        }
        favTeams = newFavTeams;
    }

    var cookieVal = JSON.stringify(favTeams);
    setCookie("domjudge_teamselection", cookieVal);


    $('.loading-indicator').addClass('ajax-loader');
    $.ajax({
        url: scoreboardUrl,
        cache: false
    }).done(function(data, status, jqXHR) {
        processAjaxResponse(jqXHR, data);
        $('.loading-indicator').removeClass('ajax-loader');
    });
}

function addHeart(rank, row, id, isFav)
{
    var heartCol = getHeartCol(row);
    var iconClass = isFav ? "fas fa-heart" : "far fa-heart";
    return heartCol.innerHTML + "<span class=\"heart " + iconClass + "\" onclick=\"toggle(" + id + "," + (isFav ? "false" : "true") + ")\"></span>";
}

function initFavouriteTeams()
{
    var scoreboard = getScoreboard();
    if (scoreboard === null) {
        return;
    }

    var favTeams = getSelectedTeams();
    var toAdd = new Array();
    var cntFound = 0;
    var lastRank = 0;
    for (var j = 0; j < scoreboard.length; j++) {
        var found = false;
        var teamname = getTeamname(scoreboard[j]);
        if (teamname === null) {
            continue;
        }
        var firstCol = getRank(scoreboard[j]);
        var heartCol = getHeartCol(scoreboard[j]);
        var rank = firstCol.innerHTML;
        for (var i = 0; i < favTeams.length; i++) {
            if (teamname === favTeams[i]) {
                found = true;
                heartCol.innerHTML = addHeart(rank, scoreboard[j], j, found);
                toAdd[cntFound] = scoreboard[j].cloneNode(true);
                if (rank === "") {
                    // make rank explicit in case of tie
                    getRank(toAdd[cntFound]).innerHTML += lastRank;
                }
                scoreboard[j].style.background = "lightyellow";
                cntFound++;
                break;
            }
        }
        if (!found) {
            heartCol.innerHTML = addHeart(rank, scoreboard[j], j, found);
        }
        if (rank !== "") {
            lastRank = rank;
        }
    }

    // copy favourite teams to the top of the scoreboard
    for (var i = 0; i < cntFound; i++) {
        var copy = toAdd[i];
        var style = "";
        if (i === 0) {
            style += "border-top: 2px solid black;";
        }
        if (i === cntFound - 1) {
            style += "border-bottom: thick solid black;";
        }
        copy.setAttribute("style", style);
        var tbody = scoreboard[1].parentNode;
        tbody.insertBefore(copy, scoreboard[i + 1]);
    }
}

// This function is a specific addition for using DOMjudge within a
// ICPC analyst setup, to automatically include an analyst's comment
// on the submission in the iCAT system.
// Note that team,prob,submission IDs are as expected by iCAT.
function postVerifyCommentToICAT(url, user, teamid, probid, submissionid)
{
    var msg = document.getElementById("comment");
    if (msg === null) {
        return;
    }

    var form = document.createElement("form");
    form.setAttribute("method", "post");
    form.setAttribute("action", url);
    form.setAttribute("hidden", true);

    // setting form target to a window named 'formresult'
    form.setAttribute("target", "formresult");

    function addField(name, value) {
        var field = document.createElement("input");
        field.setAttribute("name", name);
        field.setAttribute("id",   name);
        field.setAttribute("value", value);
        form.appendChild(field);
    };

    addField("user", user);
    addField("priority", 1);
    addField("submission", submissionid);
    addField("text", "Team #t"+teamid+" on problem #p"+probid+": "+msg.value);

    document.body.appendChild(form);

    // creating the 'formresult' window prior to submitting the form
    window.open('about:blank', 'formresult');

    form.submit();
}

function toggleExpand(event)
{
    var node = event.target.parentNode.querySelector('[data-expanded]');
    if (event.target.getAttribute('data-expanded') === '1') {
        node.innerHTML = node.getAttribute('data-collapsed');
        event.target.setAttribute('data-expanded', 0);
        event.target.innerHTML = '[expand]';
    } else {
        node.innerHTML = node.getAttribute('data-expanded');
        event.target.setAttribute('data-expanded', 1);
        event.target.innerHTML = '[collapse]';
    }
}

function clarificationAppendAnswer() {
    if ( $('#clar_answers').val() == '_default' ) { return; }
    var selected = $("#clar_answers option:selected").text();
    var textbox = $('#bodytext');
    textbox.val(textbox.val().replace(/\n$/, "") + '\n' + selected);
    textbox.scrollTop(textbox[0].scrollHeight);
}

function confirmLogout() {
    return confirm("Really log out?");
}

function processAjaxResponse(jqXHR, data) {
    if (jqXHR.getResponseHeader('X-Login-Page')) {
        window.location = jqXHR.getResponseHeader('X-Login-Page');
    } else {
        var newCurrentContest = parseInt(jqXHR.getResponseHeader('X-Current-Contest'));
        var dataCurrentContest = $('[data-current-contest]').data('current-contest');
        var currentContest = parseInt(dataCurrentContest);

        // If the contest ID changed from another tab, reload or whole page
        if (dataCurrentContest !== undefined && newCurrentContest !== currentContest) {
            window.location.reload();
            return;
        }

        var $refreshTarget = $('[data-ajax-refresh-target]');
        var $data = $(data);
        // When using the static scoreboard, we need to find the children of the [data-ajax-refresh-target]
        var $dataRefreshTarget = $data.find('[data-ajax-refresh-target]');
        if ($dataRefreshTarget.length) {
            $data = $dataRefreshTarget.children();
        }
        if ($refreshTarget.data('ajax-refresh-before')) {
            window[$refreshTarget.data('ajax-refresh-before')]();
        }
        $refreshTarget.html($data);
        if ($refreshTarget.data('ajax-refresh-after')) {
            window[$refreshTarget.data('ajax-refresh-after')]();
        }
    }
}

var refreshHandler = null;
var refreshEnabled = false;
function enableRefresh($url, $after, usingAjax) {
    if (refreshEnabled) {
        return;
    }
    var refresh = function () {
        if (usingAjax) {
            $('.loading-indicator').addClass('ajax-loader');
            $.ajax({
                url: $url,
                cache: false
            }).done(function(data, status, jqXHR) {
                processAjaxResponse(jqXHR, data);
                $('.loading-indicator').removeClass('ajax-loader');
                refreshHandler = setTimeout(refresh, $after * 1000);
            });
        } else {
            window.location = $url;
        }
    };
    refreshHandler = setTimeout(refresh, $after * 1000);
    refreshEnabled = true;
    setCookie('domjudge_refresh', 1);

    if(window.location.href == localStorage.getItem('lastUrl')) {
        window.scrollTo(0, localStorage.getItem('scrollTop'));
    } else {
        localStorage.setItem('lastUrl', window.location.href);
        localStorage.setItem('scrollTop', 42);
    }
    var scrollTimeout;
    document.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(function() {
            localStorage.setItem('scrollTop', $(window).scrollTop());
        }, 100)
    });
}

function disableRefresh(usingAjax) {
    if (!refreshEnabled) {
        return;
    }
    if (usingAjax) {
        clearInterval(refreshHandler);
    } else {
        clearTimeout(refreshHandler);
    }
    refreshEnabled = false;
    setCookie('domjudge_refresh', 0);
}

function toggleRefresh($url, $after, usingAjax) {
    if ( refreshEnabled ) {
        disableRefresh(usingAjax);
    } else {
        enableRefresh($url, $after, usingAjax);
    }

    var text = refreshEnabled ? 'Disable refresh' : 'Enable refresh';
    $('#refresh-toggle').val(text);
    $('#refresh-toggle').text(text);
}

function updateMenuAlerts()
{
    $.ajax({
        url: $('#menuDefault').data('update-url'),
        cache: false
    }).done(function(json, status, jqXHR) {
        if (jqXHR.getResponseHeader('X-Login-Page')) {
            window.location = jqXHR.getResponseHeader('X-Login-Page');
        } else {
            updateMenuClarifications(json.clarifications);
            updateMenuRejudgings(json.rejudgings);
            updateMenuJudgehosts(json.judgehosts);
            updateMenuInternalErrors(json.internal_errors);
            updateMenuBalloons(json.balloons);
            if (json.shadow_difference_count !== undefined) {
                updateMenuShadowDifferences(json.shadow_difference_count);
            }
            if (json.external_contest_source_is_down !== undefined) {
                updateMenuExternalContest(json);
            }
        }
    });
}

function updateMenuClarifications(data)
{
    var num = data.length;
    if ( num == 0 ) {
        $("#num-alerts-clarifications").hide();
        $("#menu_clarifications").removeClass("text-info");
    } else {
        $("#num-alerts-clarifications").html(num);
        $("#num-alerts-clarifications").show();
        $("#menu_clarifications").addClass("text-info");
        for (var i=0; i<num; i++) {
            sendNotification('New clarification requested.',
                 {'tag': 'clar_' + data[i].clarid,
                        'link': domjudge_base_url + '/jury/clarifications/'+data[i].clarid,
                        'body': data[i].body });
        }
    }
}

function updateMenuRejudgings(data)
{
    var num = data.length;
    if ( num == 0 ) {
        $("#num-alerts-rejudgings").hide();
        $("#menu_rejudgings").removeClass("text-info");
    } else {
        $("#num-alerts-rejudgings").html(num);
        $("#num-alerts-rejudgings").show();
        $("#menu_rejudgings").addClass("text-info");
    }
}

function updateMenuJudgehosts(data)
{
    var num = data.length;
    if ( num == 0 ) {
        $("#num-alerts-judgehosts").hide();
        $("#num-alerts-judgehosts-sub").html("");
        $("#menu_judgehosts").removeClass("text-danger");
    } else {
        $("#num-alerts-judgehosts").html(num);
        $("#num-alerts-judgehosts").show();
        $("#num-alerts-judgehosts-sub").html(num + " down");
        $("#menu_judgehosts").addClass("text-danger");
        for(var i=0; i<num; i++) {
            sendNotification('Judgehost down.',
                {'tag': 'host_'+data[i].hostname+'@'+
                    Math.floor(data[i].polltime),
                    'link': domjudge_base_url + '/jury/judgehosts/' + encodeURIComponent(data[i].hostname),
                    'body': data[i].hostname + ' is down'});
        }
    }
}

function updateMenuInternalErrors(data)
{
    var num = data.length;
    if ( num == 0 ) {
        $("#num-alerts-internalerrors").hide();
        $("#num-alerts-internalerrors-sub").html("");
        $("#menu_internal_error").removeClass("text-danger").addClass("dropdown-disabled");
    } else {
        $("#num-alerts-internalerrors").html(num);
        $("#num-alerts-internalerrors").show();
        $("#num-alerts-internalerrors-sub").html(num + " new");
        $("#menu_internal_error").addClass("text-danger").removeClass("dropdown-disabled");
        for(var i=0; i<num; i++) {
            sendNotification('Judgehost internal error occurred.',
                {'tag': 'ie_'+data[i].errorid,
                    'link': domjudge_base_url + '/internal-errors/' + data[i].errorid,
                    'body': data[i].description});
        }
    }
}

function updateMenuBalloons(data)
{
    var num = data.length;
    if ( num == 0 ) {
        $("#num-alerts-balloons").hide();
    } else {
        $("#num-alerts-balloons").html(num);
        $("#num-alerts-balloons").show();
        for (var i=0; i<num; i++) {
            var text = (data[i].room !== null) ? data[i].room+': ' : '';
            text += data[i].pname + ' ' + data[i].name;
            sendNotification('New balloon:',
                 {'tag': 'ball_' + data[i].baloonid,
                        'link': domjudge_base_url + '/jury/balloons',
                        'body': text});
        }
    }
}

function updateMenuShadowDifferences(data)
{
    var num = data;
    if ( num == 0 ) {
        $("#num-alerts-shadowdifferences").hide();
        $("#num-alerts-shadowdifferences-sub").html("");
        $("#menu_shadow_differences").removeClass("text-danger");
    } else {
        $("#num-alerts-shadowdifferences").html(num);
        $("#num-alerts-shadowdifferences").show();
        $("#num-alerts-shadowdifferences-sub").html(num + " differences");
        $("#menu_shadow_differences").addClass("text-danger");
    }
}

function updateMenuExternalContest(data)
{
    var isDown = data.external_contest_source_is_down;
    var numWarnings = parseInt(data.external_source_warning_count);
    if ( !isDown && numWarnings == 0 ) {
        $("#num-alerts-externalcontest").hide();
        $("#num-alerts-externalcontest-sub").html("");
        $("#menu_shadow_differences").removeClass("text-danger");
    } else {
        $("#num-alerts-externalcontest").html((isDown ? 1 : 0) + numWarnings);
        $("#num-alerts-externalcontest").show();
        var text;
        if (isDown && numWarnings > 0) {
            text = "down, " + numWarnings + " warnings";
        } else if (isDown) {
            text = "down";
        }  else {
            text = numWarnings + " warnings";
        }
        $("#num-alerts-externalcontest-sub").html(text);
        $("#menu_external_contest_sources").addClass("text-danger");
    }
}

function initializeAjaxModals()
{
    var $body = $('body');
    $body.on('click', '[data-ajax-modal]', function() {
        var $elem = $(this);
        var url = $elem.attr('href');
        if (!url) {
            return;
        }
        $.ajax({
            url: url,
            cache: false
        }).done(function(data, status, jqXHR) {
            if (jqXHR.getResponseHeader('X-Login-Page')) {
                window.location = jqXHR.getResponseHeader('X-Login-Page');
            } else {
                var $data = $(data);
                $('body').append($data);
                $data.modal('show');
                if ($elem.data('ajax-modal-after')) {
                    window[$elem.data('ajax-modal-after')]($elem);
                }

                $data.on('hidden.bs.modal', function () {
                    $data.remove();
                });
            }
        });
        return false;
    });

    $body.on('click', '.modal-dialog button[data-url]', function() {
        var url = $(this).data('url');
        $.ajax({
            url: url,
            method: 'POST'
        }).done(function(data) {
            window.location = data.url;
        });
    });
}

function pinScoreheader()
{
    var $scoreHeader = $('.scoreheader');
    if (!$scoreHeader.length) {
        return;
    }
    var static_scoreboard = $scoreHeader.data('static');
    if (!static_scoreboard) {
        $('.scoreheader th').css('top', $('.fixed-top').css('height'));
        if ('ResizeObserver' in window) {
            var resizeObserver = new ResizeObserver(() => {
                $scoreHeader.find('th').css('top', $('.fixed-top').css('height'));
            });
            resizeObserver.observe($('.fixed-top')[0]);
        }
    }
}

function humanReadableTimeDiff(seconds) {
    var intervals = [
        ['years', 365 * 24 * 60 * 60],
        ['months', 30 * 24 * 60 * 60],
        ['days', 24 * 60 * 60],
        ['hours', 60 * 60],
        ['minutes', 60],
    ];
    for (let [name, length] of intervals) {
        if (seconds / length >= 2) {
            return Math.floor(seconds/length) + ' ' + name;
        }
    }
    return Math.floor(seconds) + ' seconds';
}

function humanReadableBytes(bytes) {
    var sizes = [
      ['GB', 1024*1024*1024],
      ['MB', 1024*1024],
      ['KB', 1024],
    ];
    for (let [name, length] of sizes) {
        if (bytes / length >= 2) {
            return Math.floor(bytes/length) + name;
        }
    }
    return Math.floor(bytes) + 'B';
}

$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});
