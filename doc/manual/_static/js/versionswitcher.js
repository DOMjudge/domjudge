function versionChange(selectList) {
    var newVersion = selectList.value;
    // Replace current version with new version in URL to stay on the same page
    var newUrl = window.location.href.replace('/' + currentVersion + '/', '/' + newVersion + '/');
    window.location.href = newUrl;
}

window.onload = function() {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            var availableVersions = JSON.parse(this.responseText);
            var versionElement = document.getElementsByClassName('version')[0];

            // If no version element exists (sphinx_rtd_theme 3.x+), create one
            if (!versionElement) {
                var sidebar = document.getElementsByClassName('wy-side-nav-search')[0];
                if (!sidebar) {
                    console.warn('Version switcher: No sidebar found');
                    return;
                }
                versionElement = document.createElement('span');
                versionElement.className = 'version';
                // Parse version from URL (e.g., /manual/8.2/page.html -> 8.2)
                var match = window.location.pathname.match(/\/([^/]+)\/[^/]*$/);
                if (match) {
                    versionElement.textContent = match[1];
                }
                sidebar.appendChild(versionElement);
            }

            currentVersion = versionElement.textContent.trim();
            versionElement.innerHTML = '';
            var selectList = document.createElement("select");
            selectList.id = "versionSelect";
            versionElement.appendChild(selectList);
             
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
