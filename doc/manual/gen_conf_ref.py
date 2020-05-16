#!/usr/bin/env python3
import json
import sys
import textwrap
import yaml


def _get_category_description(category):
    return '''
{category}
{underline}
{description}

'''.format(
    category=category['category'],
    underline='-' * len(category['category']),
    description=category['description'])


def _wrap(wrap, text):
    return wrap + text + wrap


def _get_default_value(item):
    if item['type'] in ['bool', 'int']:
        return _wrap('``', str(item['default_value']))
    elif item['type'] == 'string':
        return _wrap('``', _wrap('\'', item['default_value']))
    else:
        return '''

  .. code-block:: json

{json}
'''.format(
        json=textwrap.indent(
            json.dumps(item['default_value'], indent=2),
            prefix=' ' * 8))

def _get_description(item):
    description = item['description'] + ' '
    if 'docdescription' in item:
        description += item['docdescription']
    return description

def _get_options(item):
    if not 'options' in item:
        return ''
    options = item['options']
    options_header = '* **Possible options:**\n\n'
    if type(options) is dict:
        return options_header + '\n'.join(['    * ``{option}``: *{desc}*'.format(
            option=option,
            desc=options[option]
        ) for option in options])
    else:
        return options_header + '\n'.join(['    * ``{}``'.format(option) for option in options])

def _get_item_description(item):
    return '''
``{name}``
{underline}

{description}

* **Type:** ``{typ}``
* **Public:** {public}
* **Default value:** {default_value}
{maybe_options}

'''.format(
    name=item['name'],
    underline='^' * (len(item['name']) + 4),
    typ=item['type'],
    public='yes' if item['public'] else 'no',
    default_value=_get_default_value(item),
    description=_get_description(item),
    maybe_options=_get_options(item))


if __name__ == "__main__":
    try:
        with open('../../etc/db-config.yaml') as db_config_file:
            db_config = yaml.load(db_config_file, Loader=yaml.SafeLoader)
    except IOError as e:
        print('Failed to read config values: %s' % e.strerror)
        sys.exit(-1)

    try:
        with open('conf_ref.rst', 'w') as rst_file:
            for category in db_config:
                rst_file.write(_get_category_description(category))
                for item in category['items']:
                    rst_file.write(_get_item_description(item))
    except IOError as e:
        print('Failed to write rst file: %s' % e.strerror)
        sys.exit(-1)
