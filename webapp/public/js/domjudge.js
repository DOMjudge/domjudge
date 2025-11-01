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
    if ( Notification.permission==='granted' ) {
        setCookie('domjudge_notify', 1);
        sendNotification('DOMjudge notifications enabled.');
        $("#notify_disable").removeClass('d-none');
        $("#notify_disable").show();
        $("#notify_enable").hide();
        return true;
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
            setCookie('domjudge_notify', 1);
            sendNotification('DOMjudge notifications enabled.');
            $("#notify_disable").removeClass('d-none');
            $("#notify_disable").show();
            $("#notify_enable").hide();
            return true;
        });
    }
}

function disableNotifications()
{
    setCookie('domjudge_notify', 0);
    $("#notify_enable").removeClass('d-none');
    $("#notify_enable").show();
    $("#notify_disable").hide();
    return true;
}

function enableKeys()
{
   setCookie('domjudge_keys', 1);
    $("#keys_disable").removeClass('d-none');
    $("#keys_disable").show();
    $("#keys_enable").hide();
}

function disableKeys()
{
    setCookie('domjudge_keys', 0);
    $("#keys_enable").removeClass('d-none');
    $("#keys_enable").show();
    $("#keys_disable").hide();
}

function getEditorThemes()
{
    const element = document.querySelector('[data-editor-themes]');
    return JSON.parse(element.dataset.editorThemes);
}

function getCurrentEditorTheme()
{
    const theme = localStorage.getItem('domjudge_editor_theme');
    if (theme === null) {
        return Object.keys(getEditorThemes())[0];
    }
    return theme;
}

function applyEditorTheme(theme = undefined, isExternal = false)
{
    if (theme === undefined) {
        theme = getCurrentEditorTheme();
        const themes = getEditorThemes();
        for (const key in themes) {
            if (key === theme) {
                isExternal = themes[key].external || false;
                break;
            }
        }
    }

    localStorage.setItem('domjudge_editor_theme', theme);
    const themeElements = document.querySelectorAll('[data-editor-theme]');
    themeElements.forEach(element => {
        const themeForElement = element.dataset.editorTheme;
        if (themeForElement === theme) {
            element.classList.add('active');
        } else {
            element.classList.remove('active');
        }
    });

    require(['vs/editor/editor.main'], function () {
        if (isExternal) {
            fetch(`${window.editorThemeFolder}/${theme}.json`)
                .then(data => data.json())
                .then(data => {
                    monaco.editor.defineTheme(theme, data);
                    monaco.editor.setTheme(theme);
                })
        } else {
            monaco.editor.setTheme(theme);
        }
    });
}

function getDiffMode()
{
    let diffMode = localStorage.getItem('domjudge_editor_diff_mode');
    if (diffMode === undefined) {
        return 'side-by-side';
    }
    return diffMode;
}

function setDiffMode(value)
{
    localStorage.setItem('domjudge_editor_diff_mode', value);
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

    // Only actually send notification when enabled
    if ( getCookie('domjudge_notify')==1 ) {
        var not = new Notification(title, options);
        if ( link!==null ) {
            not.onclick = function() {
                window.location.href = link;
            };
        }
    }

    if ( options.tag ) {
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
    var cookieVal = localStorage.getItem("domjudge_teamselection");
    if (cookieVal === null || cookieVal === "") {
        return new Array();
    }
    return JSON.parse(cookieVal);
}

function getScoreboards(mobile)
{
    const scoreboards = document.getElementsByClassName("scoreboard");
    if (scoreboards === null || scoreboards[0] === null || scoreboards[0] === undefined) {
        return null;
    }
    let scoreboardRows = {};
    const mobileScoreboardClass = 'mobile-scoreboard';
    const desktopScoreboardClass = 'desktop-scoreboard';
    for (let i = 0; i < scoreboards.length; i++) {
        if (scoreboards[i].classList.contains(mobileScoreboardClass)) {
            scoreboardRows.mobile = scoreboards[i].rows;
        } else if (scoreboards[i].classList.contains(desktopScoreboardClass)) {
            scoreboardRows.desktop = scoreboards[i].rows;
        }
    }
    if (mobile === undefined) {
        return scoreboardRows;
    } else if (mobile) {
        return scoreboardRows.mobile;
    } else {
        return scoreboardRows.desktop;
    }
}

function getRank(row)
{
    return row.querySelector('.rank');
}

function getHeartCol(row) {
    const tds = row.getElementsByTagName("td");
    let td = null;
    // search for td to store the hearts
    for (let i = 1; i < 5; i++) {
        if (tds[i] && tds[i].classList.contains("heart")) {
            td = tds[i];
            break;
        }
    }
    return td;
}

function getTeamname(row)
{
    return row.getAttribute("data-team-id");
}

function toggle(id, show, mobile)
{
    var scoreboard = getScoreboards(mobile);
    if (scoreboard === null) return;

    // Filter out all rows that do not have a data-team-id attribute or have
    // the class `scoreheader`.
    // The mobile scoreboard has them, and we need to ignore them.
    scoreboard = Array.from(scoreboard)
        .filter(
            row => row.getAttribute("data-team-id")
                || row.classList.contains("scoreheader")
        );

    var favTeams = getSelectedTeams();
    // count visible favourite teams (if filtered)
    var visCnt = 0;
    for (var i = 0; i < favTeams.length; i++) {
        for (var j = 0; j < scoreboard.length; j++) {
            if (!scoreboard[j].getAttribute("data-team-id")) {
                continue;
            }
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
    localStorage.setItem("domjudge_teamselection", cookieVal);


    $('.loading-indicator').addClass('ajax-loader');
    // If we are on a local file system, reload the window
    if (window.location.protocol === 'file:') {
        window.location.reload();
        return;
    }
    $.ajax({
        url: scoreboardUrl,
        cache: false
    }).done(function(data, status, jqXHR) {
        processAjaxResponse(jqXHR, data);
        $('.loading-indicator').removeClass('ajax-loader');
    });
}

function getHeart(rank, row, id, isFav, mobile)
{
    var iconClass = isFav ? "fas fa-heart" : "far fa-heart";
    return "<span class=\"heart " + iconClass + "\" onclick=\"toggle(" + id + "," + (isFav ? "false" : "true") + "," + mobile + ")\"></span>";
}

function initFavouriteTeams()
{
    const scoreboards = getScoreboards();
    if (scoreboards === null) {
        return;
    }

    var favTeams = getSelectedTeams();
    Object.keys(scoreboards).forEach(function(key) {
        var toAdd = new Array();
        var toAddMobile = new Array();
        var cntFound = 0;
        var lastRank = 0;
        const scoreboard = scoreboards[key];
        const mobile = key === 'mobile';
        let teamIndex = 1;
        for (var j = 0; j < scoreboard.length; j++) {
            var found = false;
            var teamname = getTeamname(scoreboard[j]);
            if (teamname === null) {
                continue;
            }
            let rankElement;
            if (mobile) {
                rankElement = getRank(scoreboard[j + 1]);
            } else {
                rankElement = getRank(scoreboard[j]);
            }
            var heartCol = getHeartCol(scoreboard[j]);
            if (!heartCol) {
                continue;
            }
            var rank = rankElement.innerHTML.trim();
            for (var i = 0; i < favTeams.length; i++) {
                if (teamname === favTeams[i]) {
                    found = true;
                    heartCol.innerHTML = getHeart(rank, scoreboard[j], teamIndex, found, mobile);
                    toAdd[cntFound] = scoreboard[j].cloneNode(true);
                    if (mobile) {
                        toAddMobile[cntFound] = scoreboard[j + 1].cloneNode(true);
                    }
                    if (rank.length === 0) {
                        // make rank explicit in case of tie
                        if (mobile) {
                            getRank(toAddMobile[cntFound]).innerHTML += lastRank;
                        } else {
                            getRank(toAdd[cntFound]).innerHTML += lastRank;
                        }
                    }
                    scoreboard[j].style.background = "lightyellow";
                    const whiteCells = scoreboard[j].querySelectorAll('.cl_FFFFFF');
                    for (let k = 0; k < whiteCells.length; k++) {
                        const whiteCell = whiteCells[k];
                        whiteCell.classList.remove('cl_FFFFFF');
                        whiteCell.classList.add('cl_FFFFE0');
                    }
                    if (mobile) {
                        scoreboard[j + 1].style.background = "lightyellow";
                    }
                    cntFound++;
                    break;
                }
            }
            if (!found) {
                heartCol.innerHTML = getHeart(rank, scoreboard[j], teamIndex, found, mobile);
            }
            if (rank !== "") {
                lastRank = rank;
            }

            teamIndex++;
        }

        let addCounter = 1;
        const copyRow = function (i, copy, addTopBorder, addBottomBorder, noMiddleBorder) {
            let style = "";
            if (noMiddleBorder) {
                style += "border-bottom-width: 0;";
            }
            if (addTopBorder && i === 0) {
                style += "border-top: 2px solid black;";
            }
            if (addBottomBorder && i === cntFound - 1) {
                style += "border-bottom: thick solid black;";
            }
            copy.setAttribute("style", style);
            const tbody = scoreboard[1].parentNode;
            tbody.insertBefore(copy, scoreboard[addCounter]);
            addCounter++;
        }

        // copy favourite teams to the top of the scoreboard
        for (let i = 0; i < cntFound; i++) {
            copyRow(i, toAdd[i], true, !mobile, mobile);
            if (mobile) {
                copyRow(i, toAddMobile[i], false, true, false);
            }
        }
    });
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
    var textbox = $('#jury_clarification_message');
    textbox.val(textbox.val().replace(/\n$/, "") + '\n' + selected);
    textbox.scrollTop(textbox[0].scrollHeight);
    previewClarification($('#jury_clarification_message') , $('#messagepreview'));
}

function confirmLogout() {
    return confirm("Really log out?");
}

function processAjaxResponse(jqXHR, data) {
    if (jqXHR.getResponseHeader('X-Login-Page')) {
        window.location = jqXHR.getResponseHeader('X-Login-Page');
    } else {
        var newCurrentContest = jqXHR.getResponseHeader('X-Current-Contest');
        var dataCurrentContest = $('[data-current-contest]').data('current-contest');
        var refreshStop = $('[data-ajax-refresh-stop]').data('ajax-refresh-stop');

        // Reload the whole page if
        // - we were signaled to stop refreshing, or
        // - if the contest ID changed from another tab.
        if ((refreshStop !== undefined && refreshStop.toString() === "1")
            || (dataCurrentContest !== undefined && newCurrentContest !== dataCurrentContest.toString())) {
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

function updateClarifications()
{
    $.ajax({
        url: $('#menuDefault').data('update-url'),
        cache: false
    }).done(function(json, status, jqXHR) {
        if (jqXHR.getResponseHeader('X-Login-Page')) {
            window.location = jqXHR.getResponseHeader('X-Login-Page');
        } else {
            let data = json['unread_clarifications'];
            let num = data.length;
            for (let i = 0; i < num; i++) {
                sendNotification('New clarification',
                 {'tag': 'clar_' + data[i].clarid,
                        'link': domjudge_base_url + '/team/clarifications/'+data[i].clarid,
                        'body': data[i].body });
            }
        }
    })
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
            var text = (data[i].location !== null) ? data[i].location+': ' : '';
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

function previewClarification($input, $previewDiv) {
    var message = $input.val();
    if (message) {
        $.ajax({
            url: markdownPreviewUrl,
            method: 'POST',
            data: {
                message: message
            }
        }).done(function (data) {
            $previewDiv.html(data.html);
        });
    }
}

function setupPreviewClarification($input, $previewDiv, previewInitial) {
    if (previewInitial) {
        previewClarification($input, $previewDiv);
    }
    $($input).on('keyup', $.debounce(function() {
        previewClarification($input, $previewDiv);
    }, 250));
}

$(function () {
    $('[data-bs-toggle="tooltip"]').tooltip();
});

function initializeKeyboardShortcuts() {
    var $body = $('body');
    var ignore = false;
    $body.on('keydown', function(e) {
        var keysCookie = getCookie('domjudge_keys');
        if (keysCookie != 1 && keysCookie != "") {
            return;
        }
        // Do not trigger shortcuts if user is pressing Ctrl/Alt/Option/Meta key.
        if (e.altKey || e.ctrlKey || e.metaKey) {
            return;
        }
        // Check if the user is not typing in an input field.
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        var key = e.key.toLowerCase();
        if (key === '?') {
            var $keyhelp = $('#keyhelp');
            if ($keyhelp.length) {
                $keyhelp.toggleClass('d-none');
            }
            return;
        }
        if (key === 'escape') {
            var $keyhelp = $('#keyhelp');
            if ($keyhelp.length && !$keyhelp.hasClass('d-none')) {
                $keyhelp.addClass('d-none');
            }
        }

        if (!ignore && !e.shiftKey && (key === 'j' || key === 'k')) {
            var parts = window.location.href.split('/');
            var lastPart = parts[parts.length - 1];
            var params = lastPart.split('?');
            var currentNumber = parseInt(params[0]);
            if (isNaN(currentNumber)) {
                return;
            }
            if (key === 'j') {
                parts[parts.length - 1] = currentNumber + 1;
            } else if (key === 'k') {
                parts[parts.length - 1] = currentNumber - 1;
            }
            if (params.length > 1) {
                parts[parts.length - 1] += '?' + params[1];
            }
            window.location = parts.join('/');
        } else if (!ignore && (key === 's' || key === 't' || key === 'p' || key === 'j' || key === 'c')) {
            if (e.shiftKey && key === 's') {
                window.location = domjudge_base_url + '/jury/scoreboard';
                return;
            }
            var type = key;
            ignore = true;
            var oldFunc = null;
            var events = $._data($body[0], 'events');
            if (events && events.keydown) {
                oldFunc = events.keydown[0].handler;
            }
            var sequence = '';
            var box = null;
            var $sequenceBox = $('<div class="keybox"></div>');
            box = $sequenceBox;
            $sequenceBox.text(type + sequence);
            $sequenceBox.appendTo($body);
            $body.on('keydown', function(e) {
                // Check if the user is not typing in an input field.
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                    ignore = false;
                    if (box) {
                        box.remove();
                    }
                    sequence = '';
                    return;
                }
                if (e.key >= '0' && e.key <= '9') {
                    sequence += e.key;
                    box.text(type + sequence);
                } else {
                    ignore = false;
                    if (box) {
                        box.remove();
                    }
                    // We want to reset the `sequence` variable before redirecting, but then we do need to save the value typed by the user
                    var typedSequence = sequence;
                    sequence = '';
                    $body.off('keydown');
                    $body.on('keydown', oldFunc);
                    if (e.key === 'Enter') {
                        switch (type) {
                            case 's':
                                type = 'submissions';
                                break;
                            case 't':
                                type = 'teams';
                                break;
                            case 'p':
                                type = 'problems';
                                break;
                            case 'c':
                                type = 'clarifications';
                                break;
                            case 'j':
                                type = 'submissions/by-judging-id';
                                break;
                        }
                        var redirect_to = domjudge_base_url + '/jury/' + type;
                        if (typedSequence) {
                            redirect_to += '/' + typedSequence;
                        }
                        window.location = redirect_to;
                    }
                }
            });
        }
    });
}

// Make sure the items in the desktop scoreboard fit
document.querySelectorAll(".desktop-scoreboard .forceWidth:not(.toolong)").forEach(el => {
    if (el instanceof Element && el.scrollWidth > el.offsetWidth) {
        el.classList.add("toolong");
    }
});

/**
 * Helper method to resize mobile team names and problem badges
 */
function resizeMobileTeamNamesAndProblemBadges() {
    // Make team names fit on the screen, but only when the mobile
    // scoreboard is visible
    const mobileScoreboard = document.querySelector('.mobile-scoreboard');
    if (mobileScoreboard.offsetWidth === 0) {
        return;
    }
    const windowWidth = document.body.offsetWidth;
    const teamNameMaxWidth = Math.max(10, windowWidth - 150);
    const problemBadgesMaxWidth = Math.max(10, windowWidth - 78);
    document.querySelectorAll(".mobile-scoreboard .forceWidth:not(.toolong)").forEach(el => {
        el.classList.remove("toolong");
        el.style.maxWidth = teamNameMaxWidth + 'px';
        if (el instanceof Element && el.scrollWidth > el.offsetWidth) {
            el.classList.add("toolong");
        } else {
            el.classList.remove("toolong");
        }
    });
    document.querySelectorAll(".mobile-scoreboard .mobile-problem-badges:not(.toolong)").forEach(el => {
        el.classList.remove("toolong");
        el.style.maxWidth = problemBadgesMaxWidth + 'px';
        if (el instanceof Element && el.scrollWidth > el.offsetWidth) {
            el.classList.add("toolong");
            const scale = el.offsetWidth / el.scrollWidth;
            const offset = -1 * (el.scrollWidth - el.offsetWidth) / 2;
            el.style.transform = `scale(${scale}) translateX(${offset}px)`;
        } else {
            el.classList.remove("toolong");
            el.style.transform = null;
        }
    });
}

function createSubmissionGraph(submissionStats, contestStartTime, contestDurationSeconds, submissions, minBucketCount = 30, maxBucketCount = 301) {
    const units = [
        { 'name': 'seconds', 'convert': 1, 'step': 60 },
        { 'name': 'minutes', 'convert': 60, 'step': 15 },
        { 'name': 'hours', 'convert': 60 * 60, 'step': 6 },
        { 'name': 'days', 'convert': 60 * 60 * 24, 'step': 7 },
        { 'name': 'weeks', 'convert': 60 * 60 * 24 * 7, 'step': 1 },
        { 'name': 'years', 'convert': 60 * 60 * 24 * 365, 'step': 1 }
    ];
    let unit = units[0];

    for (let u of units) {
        const newDuration = Math.ceil(contestDurationSeconds / u.convert);
        if (newDuration > minBucketCount) {
            unit = u;
        } else {
            break;
        }
    }
    const contestDuration = Math.ceil(contestDurationSeconds / unit.convert);
    const bucketCount = Math.min(contestDuration + 1, maxBucketCount);
    // Make sure buckets have whole unit
    const secondsPerBucket = Math.ceil(contestDuration / (bucketCount - 1)) * unit.convert;

    submissionStats.forEach(stat => {
        stat.values = Array.from({ length: bucketCount }, (_, i) => [i * secondsPerBucket / unit.convert, 0]);
    });

    const statMap = submissionStats.reduce((map, stat) => {
        map[stat.key] = stat;
        return map;
    }, {});

    submissions.forEach(submission => {
        const submissionBucket = Math.floor((submission.submittime - contestStartTime) / secondsPerBucket);
        const stat = statMap[submission.result];
        if (stat && submissionBucket >= 0 && submissionBucket < bucketCount) {
            stat.values[submissionBucket][1]++;
        }
    });

    let maxSubmissionsPerBucket = 1
    for (let bucket = 0; bucket < bucketCount; bucket++) {
        let sum = 0;
        submissionStats.forEach(stat => {
            sum += stat.values[bucket][1];
        });
        maxSubmissionsPerBucket = Math.max(maxSubmissionsPerBucket, sum);
    }

    // Pick a nice round tickDelta and tickValues based on the step size of units.
    // We want whole values in the unit, and the ticks MUST match a corresponding bucket otherwise the resulting
    // coordinate will be NaN.
    const convertFactor = secondsPerBucket / unit.convert;
    const maxTicks = Math.min(bucketCount, contestDuration / unit.step, minBucketCount)
    const tickDelta = convertFactor * Math.ceil(contestDuration / (maxTicks * convertFactor));
    const ticks = Math.floor(contestDuration / tickDelta) + 1;
    const tickValues = Array.from({ length: ticks }, (_, i) => i * tickDelta);

    nv.addGraph(function () {
        var chart = nv.models.multiBarChart()
            .showControls(false)
            .stacked(true)
            .x(function (d) { return d[0] })
            .y(function (d) { return d[1] })
            .showYAxis(true)
            .showXAxis(true)
            .reduceXTicks(false)
            ;
        chart.xAxis     //Chart x-axis settings
            .axisLabel(`Contest Time (${unit.name})`)
            .ticks(tickValues.length)
            .tickValues(tickValues)
            .tickFormat(d3.format('d'));
        chart.yAxis     //Chart y-axis settings
            .axisLabel('Total Submissions')
            .tickFormat(d3.format('d'));

        d3.select('#graph_submissions svg')
            .datum(submissionStats)
            .call(chart);
        nv.utils.windowResize(chart.update);
        return chart;
    });
}

$(function() {
    if (document.querySelector('.mobile-scoreboard')) {
        window.addEventListener('resize', resizeMobileTeamNamesAndProblemBadges);
        resizeMobileTeamNamesAndProblemBadges();
    }

    // For dropdown menus inside dropdown menus we need to make sure the outer
    // dropdown stays open when the inner dropdown is opened.
    const dropdowns = document.querySelectorAll('.dropdown-item.dropdown-toggle')
    dropdowns.forEach((dd) => {
        dd.addEventListener('click', function (e) {
            // Find the parent dropdown
            let parent = e.target;
            while (parent && !parent.classList.contains('dropdown-menu')) {
                parent = parent.parentElement;
            }

            // Also get the `a` element belonging to the menu
            const a = parent.parentElement.querySelector('.dropdown-toggle');

            setTimeout(() => {
                parent.classList.add('show');
                parent.dataset.bsPopper = 'static';
                a.classList.add('show');
            });
        });
    });
});

function loadSubmissions(dataElement, $displayElement) {
    const url = dataElement.dataset.submissionsUrl
    fetch(url)
        .then(data => data.json())
        .then(data => {
            const teamId = dataElement.dataset.teamId;
            const problemId = dataElement.dataset.problemId;
            const teamKey = `team-${teamId}`;
            const problemKey = `problem-${problemId}`;
            if (!data.submissions || !data.submissions[teamKey] || !data.submissions[teamKey][problemKey]) {
                return;
            }

            const submissions = data.submissions[teamKey][problemKey];
            if (submissions.length === 0) {
                $displayElement.html(document.querySelector('#empty-submission-list').innerHTML);
            } else {
                let templateData = document.querySelector('#submission-list').innerHTML;
                const $table = $(templateData);
                const itemTemplateData = document.querySelector('#submission-list-item').innerHTML;
                const $itemTemplate = $(itemTemplateData);
                const $submissionList = $table.find('[data-submission-list]');
                for (const submission of submissions) {
                    const $item = $itemTemplate.clone();
                    $item.find('[data-time]').html(submission.time);
                    $item.find('[data-language-id]').html(submission.language);
                    $item.find('[data-verdict]').html(submission.verdict);
                    $submissionList.append($item);
                }
                $displayElement.find('.spinner-border').remove();
                $displayElement.append($table);
            }
        });
}

function initScoreboardSubmissions() {
    $('[data-submissions-url]').on('click', function (e) {
        const linkEl = e.currentTarget;
        e.preventDefault();
        const $modal = $('[data-submissions-modal] .modal').clone();
        const $teamEl = $(`[data-team-external-id="${linkEl.dataset.teamId}"]`);
        const $problemEl = $(`[data-problem-external-id="${linkEl.dataset.problemId}"]`);
        $modal.find('[data-team]').html($teamEl.data('teamName'));
        $modal.find('[data-problem-badge]').html($problemEl.data('problemBadge'));
        $modal.find('[data-problem-name]').html($problemEl.data('problemName'));
        $modal.modal();
        $modal.modal('show');
        $modal.on('hidden.bs.modal', function (e) {
            $(e.currentTarget).remove();
        });
        $modal.on('shown.bs.modal', function (e) {
            const $modalBody = $(e.currentTarget).find('.modal-body');
            loadSubmissions(linkEl, $modalBody);
        });
    });
}
