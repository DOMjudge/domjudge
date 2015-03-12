<?php
/**
 * Simple functions to start a form, add a field to a form, end a form.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Helper function to create form fields, not to be called directly,
 * only by other functions below.
 */
function addInputField($type, $name = null, $value = null, $attributes = '') {
    if ( $name !== null && $type != 'hidden' ) {
		$id = ' id="' . htmlspecialchars(strtr($name,'[]','__'));
		if ( $type == 'radio' ) $id .= htmlspecialchars($value);
		$id .= '"';
    } else {
        $id = '';
    }

	return '<input type="'.$type.'"'.
		($name  !== null ? ' name="'.htmlspecialchars($name).'"' : '') . $id .
		($value !== null ? ' value="'.htmlspecialchars($value).'"' : '') .
		' ' . $attributes . " />\n";
}

/**
 * Password input field
function addPwField($name , $value = null) {
	return addInputField('password', $name , $value);
}
 */


/**
 * Form checkbox
 */
function addCheckBox($name, $checked = false, $value = null) {
	return addInputField('checkbox', $name, $value,
	                     ($checked ? ' checked="checked"' : ''));
}


/**
 * Form radio button
 */
function addRadioButton($name, $checked = false, $value = null) {
	return addInputField('radio', $name, $value,
	                     ($checked ? ' checked="checked"' : ''));
}

/**
 * A hidden form field.
 */
function addHidden($name, $value) {
	return addInputField('hidden', $name, $value);
}

/**
 * An input textbox.
 */
function addInput($name, $value = '', $size = 0, $maxlength = 0, $extraattr = null) {
	$attr = '';
	if ( $size ) {
		$attr .= ' size="'.(int)$size.'"';
	}
	if ( $maxlength ) {
		$attr .= ' maxlength="'.(int)$maxlength .'"';
	}
	if ( $extraattr ) {
		$attr .= ' ' . $extraattr;
	}

	return addInputField('text', $name, $value, $attr);
}

/**
 * Helper function for addSelect
 */
function matchSelect($val, $default)
{
	if ( is_array($default) ) {
		return in_array($val, $default);
	} else {
		return $val==$default;
	}
}

/**
 * Function to create a selectlist from an array.
 * Usage:
 * name: html name attribute
 * values: array ( key => value )  ->  <option value="key">value</option>
 * default: the key that will be selected
 * usekeys: use the keys of the array as option value or not
 * multi: multiple values are selectable, set to integer to set vertical size
 */
function addSelect($name, $values, $default = null, $usekeys = false, $multi = false)
{
	$size = 5;
	if ( is_int($multi) ) $size = $multi;

	$ret = '<select name="' . htmlspecialchars($name) . '"' .
		($multi ? " multiple=\"multiple\" size=\"$size\"" : '') .
		' id="' . htmlspecialchars(strtr($name,'[]','__')) . "\">\n";
	foreach ($values as $k => $v) {
		if ( ! $usekeys ) $k = $v;
		$ret .= '<option value="' .	htmlspecialchars( $k ) . '"' .
			( matchSelect($k, $default) ? ' selected="selected"' : '') . '>' .
			htmlspecialchars($v) ."</option>\n";
	}
	$ret .= "</select>\n";

	return $ret;
}

/**
 * Form submission button
 * Note the switched value/name parameters!
 */
function addSubmit($value, $name = null, $onclick = null, $enable = true, $extraattrs = "") {
	return addInputField('submit', $name, $value,
		(empty($onclick) ? null : ' onclick="'.htmlspecialchars($onclick).'"') .
		($enable ? '' : ' disabled="disabled"') .
		(empty($extraattrs) ? '' : " $extraattrs"));
}
/**
 * Form reset button, $value = caption
 */
function addReset($value) {
	return addInputField('reset', null, $value);
}

/**
 * Textarea form element.
 */
function addTextArea($name, $text = '', $cols = 40, $rows = 10, $attr = '') {
	return '<textarea name="'.htmlspecialchars($name).'" '.
		'rows="'.(int)$rows .'" cols="'.(int)$cols.'" '.
		'id="' . htmlspecialchars(strtr($name,'[]','__')).'" ' .
		$attr . '>'.htmlspecialchars($text) ."</textarea>\n";
}

/**
 * 'Add row' button, that adds a row to a table based on a template using jQuery and javascript
 * Usage:
 * templateid: HTML ID of the template tag to use
 * tableid: HTML ID of the table to add a row to
 * value: Text to display in the button
 * name: Name (and ID) of the button or null if not needed
 */
function addAddRowButton($templateid, $tableid, $value = 'Add row', $name = null)
{
	$return = addInputField('button', $name, $value, 'onclick="addRow(\'' .
	                                htmlspecialchars($templateid) . '\', \'' .
	                                htmlspecialchars($tableid) . '\')"');
	$return .= "<script type=\"text/javascript\">
    $(function() {
        addFirstRow('" . htmlspecialchars($templateid) . "', '" . htmlspecialchars($tableid) . "');
    });
</script>";
	return $return;
}

/**
 * Make a <form> start-tag.
 */
function addForm($action, $method = 'post', $id = '', $enctype = '', $charset = '', $extra = '')
{
	if ( $id ) {
		$id = ' id="'.$id.'"';
	}
	if ( $enctype ) {
		$enctype = ' enctype="'.$enctype.'"';
	}
	if ( $charset ) {
		$charset = ' accept-charset="'.htmlspecialchars($charset).'"';
	}

	return '<form style="display:inline;" action="'. $action .'" method="'. $method .'"'.
		$enctype . $id . $charset . $extra .">\n";
}

/**
 * Make a </form> end-tag.
 */
function addEndForm()
{
	return "</form>\n\n";
}

/**
 * File upload field
 */
function addFileField($name, $dummy = null, $extraattr = "") {
	return addInputField('file', $name , null, $extraattr);
}
