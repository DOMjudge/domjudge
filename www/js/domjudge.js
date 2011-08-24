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
			var newstr = ''
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
	}
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

// Autodetection of problem, language in websubmit
function detectProblemLanguage(filename)
{
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
	var filebut = document.getElementById("codebutton");
	var fileelt = document.getElementById("code");
	var filename = fileelt.value;
	var probelt = document.getElementById("probid");
	var problem = probelt.options[probelt.selectedIndex].value;
	var problemtxt = probelt.options[probelt.selectedIndex].text;

	var error = false;
	langelt.className = probelt.className = filebut.className = "";
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
		filebut.className = "errorfield";
		return false;
	}

	if ( error ) {
		return false;
	} else {
		var question =
			'Filename: ' + filename + '\n\n' +
			'Problem: ' + problemtxt + '\n'+
			'Language: ' + languagetxt + '\n' +
			'\nMake submission?';
		return confirm (question);
	}

}

function resetUploadForm(refreshtime) {
	var filebut = document.getElementById("codebutton");
	var selecttext = "Select file...";
	filebut.value = selecttext;
	setTimeout("location.reload(true);",refreshtime*1000);
}

var W3CDOM = (document.createElement && document.getElementsByTagName);

function initFileUploads() {
	if (!W3CDOM) return;
	var selecttext = "Select file...";
	var fakeFileUpload = document.createElement('span');
	fakeFileUpload.className = 'fakefile';
	var input = document.createElement('input');
	input.type = 'button';
	input.value = selecttext;
	input.id = "codebutton";
	fakeFileUpload.appendChild(input);
	var x = document.getElementsByTagName('input');
	for (var i=0;i<x.length;i++) {
		if (x[i].type != 'file') continue;
		if (x[i].parentNode.className != 'fileinputs') continue;
		x[i].className = 'file hidden';
		var clone = fakeFileUpload.cloneNode(true);
		x[i].parentNode.appendChild(clone);
		x[i].relatedElement = clone.getElementsByTagName('input')[0];
		// stop refresh when clicking a button.
		x[i].onclick = function() { window.stop(); }
		x[i].onchange = x[i].onmouseout = function () {
			if ( this.value == "" ) {
				this.relatedElement.value = selecttext;
			} else {
				var filename = this.value;
				// Opera prepends a fake fs path: C:\fakepath\. Strip that.
				if ( filename.indexOf("\\") > 0 ) filename = filename.substr(filename.lastIndexOf("\\")+1);
				detectProblemLanguage(filename);
				this.relatedElement.value = filename;
			}
		}
	}
}

