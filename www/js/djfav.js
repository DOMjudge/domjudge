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
	var firstCol = getRank(row);
	var color = isFav ? "red" : "gray";
	return "<span style=\"cursor:pointer;color:" + color + ";\" onclick=\"toggle(" + id + "," + (isFav ? "false" : "true") + ")\">&#9829;</span>" + firstCol.innerHTML;
}

window.onload = function() {
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
		var rank = firstCol.innerHTML;
		for (var i = 0; i < favTeams.length; i++) {
			if (teamname.innerHTML == favTeams[i]) {
				found = true;
				firstCol.innerHTML = addHeart(rank, scoreboard[j], j, found);
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
			firstCol.innerHTML = addHeart(rank, scoreboard[j], j, found);
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
};
