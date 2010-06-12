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
	// index 0 is the "autodetect" option
	if ( elt.selectedIndex > 0 ) return;

	for (i=0;i<elt.length;i++) {
		if ( elt.options[i].value == parts[1] ) {
			elt.selectedIndex = i;
		}
	}

	// language extension

	var elt=document.getElementById('langext');
	// index 0 is the "autodetect" option
	if ( elt.selectedIndex > 0 ) return;

	var langname = getLangNameFromExtension(parts[0]);
	for (i=0;i<elt.length;i++) {
		if ( elt.options[i].text == langname ) {
			elt.selectedIndex = i;
		}
	}

}

function getUploadConfirmString()
{
	var langelt = document.getElementById("langext");
	var language = langelt.options[langelt.selectedIndex].text;
	var fileelt = document.getElementById("code");
	var filename = fileelt.value;
	var probelt = document.getElementById("probid");
	var problem = probelt.options[probelt.selectedIndex].text;
	var question =
		'Filename: ' + filename + '\n\n' +
		'Problem: ' + problem + '\n'+
		'Language: ' + language + '\n' +
		'\nMake submission?';
	return question;
}
