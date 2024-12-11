import json
import sys
import time
import hashlib

storage1 = {}
storage2 = {}


def cleanHash(toHash):
    return hashlib.sha224(toHash).hexdigest()


def sec_start(job, header):
    print('section_start\r\033[0K'+header)


def sec_end(job):
    print('section_end\r\033[0K')


with open(sys.argv[1], 'r') as f:
    data = json.load(f)
    for message in data['messages']:
        mtyp = message['type'].encode('utf-8', 'ignore')
        murl = message['url'].encode('utf-8', 'ignore')
        mmes = message['message'].encode('utf-8', 'ignore')
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
