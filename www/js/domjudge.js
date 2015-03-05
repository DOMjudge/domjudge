function XMLHttpHandle()
{
	var ajaxRequest;
	try {
		ajaxRequest = new XMLHttpRequest();
	} catch (e) {
		try {
			ajaxRequest = new ActiveXObject("MSXML2.XMLHTTP.3.0");
		} catch (e) {
			ajaxRequest = false;
		}
	}
	return ajaxRequest;
}

function updateMenu(doreload_clarifications, doreload_judgehosts, doreload_rejudgings)
{
	var handle = XMLHttpHandle();
	if (!handle) {
		return;
	}
	handle.onreadystatechange = function() {
		if (handle.readyState == 4) {
			var resp = JSON.parse(handle.responseText);
			var nclars  = resp.clarifications.length;
			var nhosts  = resp.judgehosts.length;
			var nrejuds = resp.rejudgings.length;

			var elem = document.getElementById('menu_clarifications');
			var newstr = '';
			if ( elem!==null ) {
				if ( nclars == 0 ) {
					elem.className = null;
				} else {
					newstr = ' ('+nclars+' new)';
					elem.className = 'new';
				}
				if ( elem.innerHTML != 'clarifications' + newstr ) {
					elem.innerHTML = 'clarifications' + newstr;
					if(doreload_clarifications) {
						location.reload()
					}
				}
			}
			var elem = document.getElementById('menu_judgehosts');
			var newstr = '';
			if ( elem!==null ) {
				if ( nhosts == 0 ) {
					elem.className = null;
				} else {
					newstr = ' ('+nhosts+' down)';
					elem.className = 'new';
				}
				if ( elem.innerHTML != 'judgehosts' + newstr ) {
					elem.innerHTML = 'judgehosts' + newstr;
					if(doreload_judgehosts) {
						location.reload()
					}
				}
			}
			var elem = document.getElementById('menu_rejudgings');
			var newstr = '';
			if ( elem!==null ) {
				if ( nrejuds == 0 ) {
					elem.className = null;
				} else {
					newstr = ' ('+nrejuds+' active)';
					elem.className = 'new';
				}
				if ( elem.innerHTML != 'rejudgings' + newstr ) {
					elem.innerHTML = 'rejudgings' + newstr;
					if(doreload_judgehosts) {
						location.reload()
					}
				}
			}

			for(i=0; i<nclars; i++) {
				sendNotification('New clarification.',
				                 {'tag': 'clar_'+resp.clarifications[i].clarid,
				                  'link': 'clarification.php?id='+resp.clarifications[i].clarid,
				                  'body': resp.clarifications[i].body });
			}
			for(i=0; i<nhosts; i++) {
				sendNotification('Judgehost down.',
				                 {'tag': 'host_'+resp.judgehosts[i].hostname+'@'+
				                  Math.floor(resp.judgehosts[i].polltime)});
			}
		}
	};
	handle.open("GET", "updates.php", true);
	handle.send(null);
}

// If the browser supports desktop notifications, toggle whether these
// are enabled. This requires user permission.
// Returns whether setting it was successful.
function toggleNotifications(enable)
{
	if ( enable ) {
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
		if ( Notification.permission=='denied' ) {
			alert('Browser denied permission to send desktop notifications.\n' +
			      'Re-enable notification permission in the browser and retry.');
		} else
			if ( Notification.permission!=='granted' ) {
				Notification.requestPermission(function (permission) {
					// Safari and Chrome don't support the static 'permission'
					// variable, so in this case we set it ourselves.
					if ( !('permission' in Notification) ) {
						Notification.permission = permission;
					}
					if ( Notification.permission!=='granted' ) {
						alert('Browser denied permission to send desktop notifications.');
					} else {
						sendNotification('DOMjudge notifications enabled.',
						                 {'timeout': 5});
						window.location.href = 'toggle_notify.php?enable=1';
						return false;
					}
				});
			}

		return (Notification.permission==='granted');
	} else {
		// disable: no need/possibility to ask user to revoke permission.

		// FIXME: Should we close any notifications currently showing?
	}

	return true;
}

// Send a notification if notifications have been enabled.
// The options argument is passed to the Notification constructor,
// except that the following tags (if found) are interpreted and
// removed from options:
// * timeout    notification timeout in seconds (default: 5 minutes)
// * link       URL to redirect to on click, relative to DOMjudge base
//
// We use HTML5 localStorage to keep track of which notifications the
// client has already received to display each notification only once.
function sendNotification(title, options)
{
	if ( getCookie('domjudge_notify')!=1 ) return;

//	if ( typeof options.tag === 'undefined' ) options.tag = null;

	// Check if we already sent this notification:
	var senttags = localStorage.getItem('notifications_sent');
	if ( senttags===null || senttags=='' ) {
		senttags = [];
	} else {
		senttags = senttags.split(',');
	}
	if ( options.tag!==null && senttags.indexOf(options.tag)>=0 ) return;

	var timeout = 600;
	if ( typeof options.timeout !== 'undefined' ) {
		timeout = options.timeout;
		delete options.timeout;
	}

	var link = null;
	if ( typeof options.link !== 'undefined' ) {
		link = options.link;
		delete options.link;
	}

	var not = new Notification(title, options);

	not.onshow = function() { setTimeout(not.close, timeout*1000); }
// FIXME: setting timeout doesn't work in Chromium nor in Firefox
// (also overriden by default 4 second close timeout, see:
// https://bugzilla.mozilla.org/show_bug.cgi?id=875114).

	if ( link!==null ) {
		not.onclick = function() { window.open(link); }
	}

	if ( options.tag!==null ) {
		senttags.push(options.tag);
		localStorage.setItem('notifications_sent',senttags.join(','));
	}
}

// make corresponding testcase description editable
function editTcDesc(descid)
{
	var node = document.getElementById('tcdesc_' + descid);
	node.parentNode.setAttribute('onclick', '');
	node.parentNode.removeChild(node.nextSibling);
	node.style.display = 'block';
	node.setAttribute('name', 'description[' + descid + ']');
}

// hides edit field if javascript is enabled
function hideTcDescEdit(descid)
{
	var node = document.getElementById('tcdesc_' + descid);
	node.style.display = 'none';
	node.setAttribute('name', 'invalid');

	var span = document.createElement('span');
	span.innerHTML = node.innerHTML;
	node.parentNode.appendChild(span);
}

// make corresponding testcase sample dropdown editable
function editTcSample(tcid)
{
	var node = document.getElementById('sample_' + tcid + '_');
	node.parentNode.setAttribute('onclick', '');
	var remove = node.nextSibling;
	while (remove.nodeName == '#text')
		remove = remove.nextSibling;
	node.parentNode.removeChild(remove);
	node.style.display = 'block';
	node.setAttribute('name', 'sample[' + tcid + ']');
}

// hides sample dropdown field if javascript is enabled
function hideTcSample(tcid, str)
{
	var node = document.getElementById('sample_' + tcid + '_');
	node.style.display = 'none';
	node.setAttribute('name', 'invalid');

	var span = document.createElement('span');
	span.innerHTML = str;
	node.parentNode.appendChild(span);
}

// Autodetection of problem, language in websubmit
function detectProblemLanguage(filename)
{
	var addfile = document.getElementById("addfile");
	if ( addfile ) addfile.disabled = false;

	var parts = filename.replace(/^.*[\\\/]/, '')
	            .toLowerCase().split('.').reverse();
	if ( parts.length < 2 ) return;

	// problem ID

	var elt=document.getElementById('probid');
	// the "autodetect" option has empty value
	if ( elt.value != '' ) return;

	for (i=0;i<elt.length;i++) {
		if ( elt.options[i].text.toLowerCase() == parts[1] ) {
			elt.selectedIndex = i;
		}
	}

	// language ID

	var elt=document.getElementById('langid');
	// the "autodetect" option has empty value
	if ( elt.value != '' ) return;

	var langid = getMainExtension(parts[0]);
	for (i=0;i<elt.length;i++) {
		if ( elt.options[i].value == langid ) {
			elt.selectedIndex = i;
		}
	}

}

function checkUploadForm()
{
	var langelt = document.getElementById("langid");
	var language = langelt.options[langelt.selectedIndex].value;
	var languagetxt = langelt.options[langelt.selectedIndex].text;
	var fileelt = document.getElementById("maincode");
	var filename = fileelt.value;
	var probelt = document.getElementById("probid");
	var problem = probelt.options[probelt.selectedIndex].value;
	var problemtxt = probelt.options[probelt.selectedIndex].text + " - " + getProbDescription(probelt.options[probelt.selectedIndex].text);
	var auxfiles = document.getElementsByName("code[]");

	var error = false;
	langelt.className = probelt.className = "";
	if ( language == "" ) {
		langelt.focus();
		langelt.className = "errorfield";
		error = true;
	}
	if ( problem == "" ) {
		probelt.focus();
		probelt.className = "errorfield";
		error = true;
	}
	if ( filename == "" ) {
		return false;
	}

	if ( error ) {
		return false;
	} else {
		var auxfileno = 0;
		// start at one; skip maincode file field
		for (var i = 1; i < auxfiles.length; i++) {
			if (auxfiles[i].value != "" ) {
				auxfileno++;
			}
		}
		var extrafiles = '';
		if ( auxfileno > 0 ) {
			extrafiles = "Additional source files: " + auxfileno + '\n';
		}
		var question =
			'Main source file: ' + filename + '\n' +
			extrafiles + '\n' +
			'Problem: ' + problemtxt + '\n'+
			'Language: ' + languagetxt + '\n' +
			'\nMake submission?';
		return confirm (question);
	}

}

function resetUploadForm(refreshtime, maxfiles) {
	var addfile = document.getElementById("addfile");
	var auxfiles = document.getElementById("auxfiles");
	addfile.disabled = true;
	auxfiles.innerHTML = "";
	doReload = true;
	setTimeout('reloadPage()', refreshtime * 1000);
}

var doReload = true;

function reloadPage()
{
	// interval is in seconds
	if (doReload) {
		location.reload();
	}
}

function initReload(refreshtime)
{
	// interval is in seconds
	setTimeout('reloadPage()', refreshtime * 1000);
}

function initFileUploads(maxfiles) {
	var fileelt = document.getElementById("maincode");

	if ( maxfiles > 1 ) {
		var fileadd = document.getElementById("addfile");
		var supportshtml5multi = ("multiple" in fileelt);
		if ( supportshtml5multi ) {
			fileadd.style.display = "none";
		}
	}
	fileelt.onclick = function() { doReload = false; };
	fileelt.onchange = fileelt.onmouseout = function () {
		if ( this.value != "" ) {
			detectProblemLanguage(this.value);
		}
	}
}

function collapse(x){
	var oTemp=document.getElementById("detail"+x);
	if (oTemp.style.display=="none") {
		oTemp.style.display="block";
	} else {
		oTemp.style.display="none";
	}
}

function addFileUpload() {
	var input = document.createElement('input');
	input.type = 'file';
	input.name = 'code[]';
	var br = document.createElement('br');

	document.getElementById('auxfiles').appendChild( input );
	document.getElementById('auxfiles').appendChild( br );
}

function togglelastruns() {
	var names = {'lastruntime':0, 'lastresult':1, 'lasttcruns':2};
	for (var name in names) {
		cells = document.getElementsByClassName(name);
		for (i = 0; i < cells.length; i++) {
			style = 'inline';
			if (name == 'lasttcruns') {
				style = 'table-row';
			}
			cells[i].style.display = (cells[i].style.display == 'none') ? style : 'none';
		}
	}
}

function updateClock()
{
	curtime = initial+offset;
	date.setTime(curtime*1000);

	var fmt = "";
	if (curtime >= starttime && curtime < endtime ) {
		var left = endtime - curtime;
		var what = "time left: ";
	} else if (curtime >= activatetime && curtime < starttime ) {
		var left = starttime - curtime;
		var what = "time to start: ";
	} else {
		var left = 0;
		var what = "";
	}

	if ( left ) {
		if ( left > 24*60*60 ) {
			d = Math.floor(left/(24*60*60));
			fmt += d + "d ";
			left -= d * 24*60*60;
		}
		if ( left > 60*60 ) {
			h = Math.floor(left/(60*60));
			fmt += h + ":";
			left -= h * 60*60;
		}
		m = Math.floor(left/60);
		if ( m < 10 ) { fmt += "0"; }
		fmt += m + ":";
		left -= m * 60;
		if ( left < 10 ) { fmt += "0"; }
		fmt += left;
	}

	timeleftelt.innerHTML = what + fmt;
	offset++;
}

function setCookie(name, value) {
	var expire = new Date();
	expire.setDate(expire.getDate() + 3); // three days valid
	document.cookie = name + "=" + escape(value) + "; expires=" + expire.toUTCString();
}

function getCookie(name) {
	var cookies = document.cookie.split(";");
	for (var i = 0; i < cookies.length; i++) {
		var idx = cookies[i].indexOf("=");
		var key = cookies[i].substr(0, idx);
		var value = cookies[i].substr(idx+1);
		key = key.replace(/^\s+|\s+$/g,""); // trim
		if (key == name) {
			return unescape(value);
		}
	}
	return "";
}

function getSelectedTeams() {
	var cookieVal = getCookie("domjudge_teamselection");
	if (cookieVal == null || cookieVal == "") {
		return new Array();
	}
	return JSON.parse(cookieVal);
}

function getScoreboard() {
	var scoreboard = document.getElementsByClassName("scoreboard");
	if (scoreboard == null || scoreboard[0] == null) {
		return null;
	}
	return scoreboard[0].rows;
}

function getRank(row) {
	return row.getElementsByTagName("td")[0];
}

function getHeartCol(row) {
	return row.getElementsByTagName("td")[1];
}

function getTeamname(row) {
	var res = row.getAttribute("id");
	if ( res == null ) return res;
	return res.replace(/^team:/, '');
}

function toggle(id, show) {
	var scoreboard = getScoreboard();

	var favTeams = getSelectedTeams();
	// count visible favourite teams (if filtered)
	var visCnt = 0;
	for (var i = 0; i < favTeams.length; i++) {
		for (var j = 0; j < scoreboard.length; j++) {
			var scoreTeamname = getTeamname(scoreboard[j]);
			if (scoreTeamname == null) {
				continue;
			}
			if (scoreTeamname == favTeams[i]) {
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
			if (favTeams[i] != teamname) {
				newFavTeams[newFavTeams.length] = favTeams[i];
			}
		}
		favTeams = newFavTeams;
	}

	var cookieVal = JSON.stringify(favTeams);
	setCookie("domjudge_teamselection", cookieVal);

	window.location.reload();
}

function addHeart(rank, row, id, isFav) {
	var heartCol = getHeartCol(row);
	var color = isFav ? "red" : "gray";
	return heartCol.innerHTML + "<span class=\"heart\" style=\"color:" + color + ";\" onclick=\"toggle(" + id + "," + (isFav ? "false" : "true") + ")\">&#9829;</span>";
}

function initFavouriteTeams() {
	var scoreboard = getScoreboard();
	if (scoreboard == null) {
		return;
	}

	var favTeams = getSelectedTeams();
	var toAdd = new Array();
	var cntFound = 0;
	var lastRank = 0;
	for (var j = 0; j < scoreboard.length - 1; j++) {
		var found = false;
		var teamname = getTeamname(scoreboard[j]);
		if (teamname == null) {
			continue;
		}
		var firstCol = getRank(scoreboard[j]);
		var heartCol = getHeartCol(scoreboard[j]);
		var rank = firstCol.innerHTML;
		for (var i = 0; i < favTeams.length; i++) {
			if (teamname == favTeams[i]) {
				found = true;
				heartCol.innerHTML = addHeart(rank, scoreboard[j], j, found);
				toAdd[cntFound] = scoreboard[j].cloneNode(true);
				if (rank == "") {
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
		if (rank != "") {
			lastRank = rank;
		}
	}

	// copy favourite teams to the top of the scoreboard
	for (var i = 0; i < cntFound; i++) {
		var copy = toAdd[i];
		var firstCol = getRank(copy);
		var color = "red";
		var style = "";
		if (i == 0) {
			style += "border-top: 2px solid black;";
		}
		if (i == cntFound - 1) {
			style += "border-bottom: thick solid black;";
		}
		copy.setAttribute("style", style);
		var tbody = scoreboard[1].parentNode;
		tbody.insertBefore(copy, scoreboard[i + 1]);
	}
}
