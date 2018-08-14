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
function addInputField(string $type, $name = null, $value = null, string $attributes = '') : string
{
    if ($name !== null && $type != 'hidden') {
        $id = ' id="' . specialchars(strtr($name, '[]', '__'));
        if ($type == 'radio') {
            $id .= specialchars($value);
        }
        $id .= '"';
    } else {
        $id = '';
    }

    return '<input type="'.$type.'"'.
        ($name  !== null ? ' name="'.specialchars($name).'"' : '') . $id .
        ($value !== null ? ' value="'.specialchars($value).'"' : '') .
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
function addCheckBox(string $name, bool $checked = false, $value = null) : string
{
    return addInputField(
        'checkbox',
        $name,
        $value,
        ($checked ? ' checked="checked"' : '')
    );
}


/**
 * Form radio button
 */
function addRadioButton(string $name, bool $checked = false, $value = null) : string
{
    return addInputField(
        'radio',
        $name,
        $value,
        ($checked ? ' checked="checked"' : '')
    );
}

/**
 * A hidden form field.
 */
function addHidden(string $name, string $value) : string
{
    return addInputField('hidden', $name, $value);
}

/**
 * An input textbox.
 */
function addInput(string $name, $value = '', $size = 0, $maxlength = 0, $extraattr = null) : string
{
    $attr = '';
    if ($size) {
        $attr .= ' size="'.(int)$size.'"';
    }
    if ($maxlength) {
        $attr .= ' maxlength="'.(int)$maxlength .'"';
    }
    if ($extraattr) {
        $attr .= ' ' . $extraattr;
    }

    return addInputField('text', $name, $value, $attr);
}

/**
 * Helper function for addSelect
 */
function matchSelect($val, $default) : bool
{
    if (is_array($default)) {
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
function addSelect(string $name, array $values, $default = null, bool $usekeys = false, bool $multi = false) : string
{
    $size = 5;
    if (is_int($multi)) {
        $size = $multi;
    }

    $ret = '<select name="' . specialchars($name) . '"' .
        ($multi ? " multiple=\"multiple\" size=\"$size\"" : '') .
        ' id="' . specialchars(strtr($name, '[]', '__')) . "\">\n";
    foreach ($values as $k => $v) {
        if (! $usekeys) {
            $k = $v;
        }
        $ret .= '<option value="' . specialchars($k) . '"' .
            (matchSelect($k, $default) ? ' selected="selected"' : '') . '>' .
            specialchars($v) ."</option>\n";
    }
    $ret .= "</select>\n";

    return $ret;
}

/**
 * Form submission button
 * Note the switched value/name parameters!
 */
function addSubmit(string $value, $name = null, $onclick = null, bool $enable = true, string $extraattrs = "") : string
{
    return addInputField(
        'submit',
        $name,
        $value,
        (empty($onclick) ? null : ' onclick="'.specialchars($onclick).'"') .
        ($enable ? '' : ' disabled="disabled"') .
        (empty($extraattrs) ? '' : " $extraattrs")
    );
}
/**
 * Form reset button, $value = caption
 */
function addReset(string $value) : string
{
    return addInputField('reset', null, $value);
}

/**
 * A normal non-submit button.
 */
function addButton(string $name, string $value, $onclick = null, bool $enable = true, string $extraattrs = "") : string
{
    return addInputField(
        'button',
        $name,
        $value,
        (empty($onclick) ? null : ' onclick="'.specialchars($onclick).'"') .
        ($enable ? '' : ' disabled="disabled"') .
        (empty($extraattrs) ? '' : " $extraattrs")
    );
}

/**
 * Textarea form element.
 */
function addTextArea(string $name, string $text = '', int $cols = 40, int $rows = 10, string $attr = '') : string
{
    return '<textarea name="'.specialchars($name).'" '.
        'rows="'.(int)$rows .'" cols="'.(int)$cols.'" '.
        'id="' . specialchars(strtr($name, '[]', '__')).'" ' .
        $attr . '>'.specialchars($text) ."</textarea>\n";
}

/**
 * 'Add row' button, that adds a row to a table based on a template using jQuery and javascript
 * Usage:
 * templateid: HTML ID of the template tag to use
 * tableid: HTML ID of the table to add a row to
 * value: Text to display in the button
 * name: Name (and ID) of the button or null if not needed
 */
function addAddRowButton(string $templateid, string $tableid, string $value = 'Add row', $name = null) : string
{
    $return = addInputField('button', $name, $value, 'onclick="addRow(\'' .
                                    specialchars($templateid) . '\', \'' .
                                    specialchars($tableid) . '\')"');
    $return .= "<script type=\"text/javascript\">
    $(function() {
        addFirstRow('" . specialchars($templateid) . "', '" . specialchars($tableid) . "');
    });
</script>";
    return $return;
}

/**
 * Make a <form> start-tag.
 */
function addForm(string $action, string $method = 'post', $id = '', string $enctype = '', string $charset = '', string $extra = '') : string
{
    if ($id) {
        $id = ' id="'.$id.'"';
    }
    if ($enctype) {
        $enctype = ' enctype="'.$enctype.'"';
    }
    if ($charset) {
        $charset = ' accept-charset="'.specialchars($charset).'"';
    }

    return '<form style="display:inline;" action="'. $action .'" method="'. $method .'"'.
        $enctype . $id . $charset . $extra .">\n";
}

/**
 * Make a </form> end-tag.
 */
function addEndForm() : string
{
    return "</form>\n\n";
}

/**
 * File upload field
 */
function addFileField(string $name, $dummy = null, string $extraattr = "") : string
{
    return addInputField('file', $name, null, $extraattr);
}
