function addRow(templateid, tableid) {
    var $template = $('#' + templateid);
    var $table = $('#' + tableid);
    var maxId = $table.data('max-id');

    if ( maxId === undefined ) {
	// If not set on the table yet, we start at 0
	maxId = 0;
    } else {
	// Oterwise we should add 1 to the old value
	maxId++;
    }

    // Set it back on the table
    $table.data('max-id', maxId);

    var templateContents = $template.text().replace(/\{id\}/g, maxId);

    $('tbody', $table).append(templateContents);
}
