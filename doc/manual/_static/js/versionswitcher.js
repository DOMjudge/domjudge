function versionChange(selectList) {
    url = window.location.href;
    for (var i = 0; i < 2; i++) {
        var to = url.lastIndexOf('/');
        to = to == -1 ? url.length : to;
        url = url.substring(0, to);
    }
    // TODO: Redirect to same page on the other version instead of the
    // overview.
    window.location.href = url + '/' + selectList.value + '/index.html'
}

window.onload = function() {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
             var availableVersions = JSON.parse(this.responseText);
             var version = document.getElementsByClassName(['version'])[0];
             currentVersion = version.innerHTML.trim();
             version.innerHTML = '';
             var selectList = document.createElement("select");
             selectList.id = "versionSelect";
             version.appendChild(selectList);
             
             for (var i = 0; i < availableVersions.length; i++) {
                 var option = document.createElement("option");
                 option.value = availableVersions[i];
                 option.text = availableVersions[i];
                 selectList.appendChild(option);
             }
             selectList.onchange = function(){versionChange(selectList);};
             selectList.value = currentVersion;
        }
    };
    xmlhttp.open("GET", '../versions.json', true);
    xmlhttp.send();
}
