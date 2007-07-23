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

function updateClarifications()
{
	var handle = XMLHttpHandle();
	if (!handle) {
		return;
	}
	handle.onreadystatechange = function() {
		if (handle.readyState == 4) {
			var elem = document.getElementById('menu_clarifications');
			var cnew = handle.responseText;
			if (cnew == 0) {
				elem.innerHTML = 'clarifications';
				elem.className = null;
			} else {
				elem.innerHTML = 'clarifications ('+cnew+' new)';
				elem.className = 'new';
			}
		}
	}
	handle.open("GET", "update_clarifications.php", true);
	handle.send(null); 
}
