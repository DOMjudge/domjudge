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

function updateClarifications(ajaxtitle)
{
	var handle = XMLHttpHandle();
	if (!handle) {
		return;
	}
	handle.onreadystatechange = function() {
		if (handle.readyState == 4) {
			var elem = document.getElementById('menu_clarifications');
			var cnew = handle.responseText;
			var newstr = '';
			if (cnew == 0) {
				elem.className = null;
			} else {
				newstr = ' ('+cnew+' new)';
				elem.className = 'new';
			}
			elem.innerHTML = 'clarifications' + newstr;
			if(ajaxtitle) {
				document.title = ajaxtitle + newstr;
			}
		}
	};
	handle.open("GET", "update_clarifications.php", true);
	handle.send(null);
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

	var parts = filename.toLowerCase().split('.').reverse();
	if ( parts.length < 2 ) return;

	// problem ID

	var elt=document.getElementById('probid');
	// the "autodetect" option has empty value
	if ( elt.value != '' ) return;

	for (i=0;i<elt.length;i++) {
		if ( elt.options[i].value.toLowerCase() == parts[1] ) {
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
		location.reload(true);
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
	var names = {'lastruntime':0, 'lastresult':1};
	for (var name in names) {
		cells = document.getElementsByName(name);
		for (i = 0; i < cells.length; i++) {
			cells[i].style.display = (cells[i].style.display == 'none') ? 'table-cell' : 'none';
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

	timecurelt.innerHTML = date.toString().replace(/(\w{3})\ (\w{3})\ (\d{2})\ (\d{4})\ (\d{2}:\d{2}:\d{2}).*\((\w+)\)/, "$1 $3 $2 $4 $5 $6");
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
	var cookieVal = getCookie("dj_teamselection");
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
	return row.getElementsByTagName("td")[2];
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
			if (scoreTeamname.innerHTML == favTeams[i]) {
				visCnt++;
				break;
			}
		}
	}
	var teamname = getTeamname(scoreboard[id + visCnt]).innerHTML;
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
	setCookie("dj_teamselection", cookieVal);

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
			if (teamname.innerHTML == favTeams[i]) {
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
