<?php declare(strict_types=1);
/**
 * Simple functions to start a form, add a field to a form, end a form.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

// TODO: still used in combined_scoreboard. Refactor them and remove this

/**
 * Helper function to create form fields, not to be called directly,
 * only by other functions below.
 */
function addInputField(string $type, $name = null, $value = null, string $attributes = '') : string
{
    if ($name !== null && $type != 'hidden') {
        $id = ' id="' . specialchars(strtr($name, '[]', '__'));
        if ($type == 'radio') {
            $id .= specialchars((string)($value ?? ''));
        }
        $id .= '"';
    } else {
        $id = '';
    }

    return '<input type="'.$type.'"'.
        ($name  !== null ? ' name="'.specialchars($name).'"' : '') . $id .
        ($value !== null ? ' value="'.specialchars((string)$value).'"' : '') .
        ' ' . $attributes . " />\n";
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
function addSelect(string $name, array $values, $default = null, bool $usekeys = false, $multi = false) : string
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
        $ret .= '<option value="' . specialchars((string) $k) . '"' .
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
