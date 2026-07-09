import argparse
import json
import sys
import time
import hashlib
import os

parser = argparse.ArgumentParser()
parser.add_argument('input', help='JSON file to process')
parser.add_argument('--summary-file', help='Write a concise error summary to this file')
args = parser.parse_args()

storage1 = {}
storage2 = {}

# Get the base directory to make file paths relative
base_dir = os.getcwd()
summary_lines = []


def cleanHash(toHash):
    return hashlib.sha224(toHash).hexdigest()


def sec_start(job, header):
    print('section_start\r\033[0K'+header)


def sec_end(job):
    print('section_end\r\033[0K')


with open(args.input, 'r') as f:
    data = json.load(f)
    # Handle both vnu format {"messages": [...]} and pa11y format [...]
    if isinstance(data, dict) and 'messages' in data:
        messages = data['messages']
    elif isinstance(data, list):
        messages = data
    else:
        messages = []

    for message in messages:
        mtyp = str(message.get('type', 'error'))
        murl = str(message.get('url', message.get('file', 'unknown')))
        mmes = str(message.get('message', 'no message'))

        line = message.get('lastLine', message.get('line', 1))
        col = message.get('lastColumn', message.get('column', 1))
        file_path = murl
        if file_path.startswith('file:'):
            file_path = file_path[5:]
        if file_path.startswith(base_dir):
            file_path = os.path.relpath(file_path, base_dir)

        # Emit GNU-style error for standard logs
        gnu_line = f"{file_path}:{line}.{col}: {mtyp}: {mmes}"
        print(gnu_line)
        summary_lines.append(gnu_line)

        # Emit GHA error annotation
        if mtyp == 'error':
            # Escape newlines in message for GHA
            escaped_mes = mmes.replace('\n', '%0A').replace('\r', '%0D')
            print(f"::error file={file_path},line={line},col={col}::{escaped_mes}")

        if mtyp not in storage1.keys():
            storage1[mtyp] = {"messages": {}, "cnt": 0}
            storage2[mtyp] = {"urls": {}, "cnt": 0}
        if mmes not in storage1[mtyp]["messages"].keys():
            storage1[mtyp]["messages"][mmes] = {"urls": {}, "cnt": 0}
        if murl not in storage2[mtyp]["urls"].keys():
            storage2[mtyp]["urls"][murl] = {"messages": {}, "cnt": 0}
        if murl not in storage1[mtyp]["messages"][mmes]["urls"].keys():
            storage1[mtyp]["messages"][mmes]["urls"][murl] = 0
        if mmes not in storage2[mtyp]["urls"][murl]["messages"].keys():
            storage2[mtyp]["urls"][murl]["messages"][mmes] = 0
        storage1[mtyp]["messages"][mmes]["urls"][murl] += 1
        storage1[mtyp]["messages"][mmes]["cnt"] += 1
        storage1[mtyp]["cnt"] += 1
        storage2[mtyp]["urls"][murl]["messages"][mmes] += 1
        storage2[mtyp]["urls"][murl]["cnt"] += 1
        storage2[mtyp]["cnt"] += 1

if args.summary_file and summary_lines:
    with open(args.summary_file, 'w') as sf:
        sf.write('\n'.join(summary_lines) + '\n')

for key, value in sorted(storage1.items(), key=lambda x: x[1]['cnt']):
    print("Type:  {}, Totalfound:  {}".format(key, value["cnt"]))
    for key2, value2 in sorted(storage1[key]["messages"].items(), key=lambda x: x[1]['cnt'], reverse=True):
        sec_start(key+key2, key2)
        print("[{}] [{}%] Message:  {}".format(key, round(100*value2["cnt"]/value["cnt"], 2), key2))
        for key3, value3 in sorted(storage1[key]["messages"][key2]["urls"].items(), key=lambda x: x[1], reverse=True):
            print("[{}%] URL:  {}".format(round(100*value3/value2["cnt"], 2), key3))
        sec_end(key+key2)
    for key2, value2 in sorted(storage2[key]["urls"].items(), key=lambda x: x[1]['cnt'], reverse=True):
        sec_start(key+key2, key2)
        print("[{}] [{}%] URL:  {}".format(key, round(100*value2["cnt"]/value["cnt"], 2), key2))
        for key3, value3 in sorted(storage2[key]["urls"][key2]["messages"].items(), key=lambda x: x[1], reverse=True):
            print("[{}%] Message:  {}".format(round(100*value3/value2["cnt"], 2), key3))
        sec_end(key+key2)
