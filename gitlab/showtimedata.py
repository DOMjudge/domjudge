import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import numpy as np
from os import walk
from matplotlib import rcParams
rcParams.update({'figure.autolayout': True})

from matplotlib.pyplot import figure
from datetime import timedelta

def get_data(overall,job):
    global storage_job
    global skiplst
    begin = []
    end   = []
    event = []
    if overall:
        begin = [storage_job[x][job]['begin'] for x in storage_job.keys()]
        end   = [storage_job[x][job]['end'] for x in storage_job.keys()]
        event = list(storage_job.keys())
    else:
        filename = job
        begin = [storage_job[filename][subjob]['begin'] for subjob in storage_job[filename].keys() if subjob != 'job']
        end   = [storage_job[filename][subjob]['end'] for subjob in storage_job[filename].keys() if subjob != 'job']
        event = [x for x in storage_job[filename].keys() if x != 'job']
    if len(begin)==0:
        return
    offset = min(begin)
    begin = np.array(begin)-offset
    end =   np.array(end)-offset

    if len(begin)>1:
        figure(figsize=(16, 6), dpi=80)
        plt.barh(range(len(begin)),  end-begin, left=begin)
        plt.xlim([-10,max(end)+10])
        plt.yticks(range(len(begin)), event)
        plt.savefig(f"plot/time_{job}.png")
    
    # Get some statistics
    if overall:
        last_start  = event[np.where(begin==max(begin))[0][0]]
        last_finish = event[np.where(end==max(end))[0][0]]
        print(f"Last start: {last_start}")
        print(f"Last finish: {last_finish}")
        for name in storage_job.keys():
            if 'orig_' in name:
                time_orig = storage_job[name]['job']['end']-storage_job[name]['job']['begin']
                time_new = storage_job[name.replace('orig_','')]['job']['end']-storage_job[name.replace('orig_','')]['job']['begin']
                print(name, time_orig-time_new)
    else:
        print(f"\n\n-----{job}-----")
    msg = ["Duration of job","End of Job","Begin of Job"]
    tmpdiff = {}
    tmpend = {}
    tmpbegin = {}
    for i,e in enumerate(event):
        tmpdiff[e] = end[i]-begin[i]
        tmpend[e] = end[i]
        tmpbegin[e] = begin[i]
    tmplst = [tmpdiff]
    if overall:
        tmplst = [tmpdiff,tmpbegin,tmpend]
    for i,tmp in enumerate(tmplst):
        print()
        print(msg[i])
        avg = sum(tmp.values())/len(tmp.values())
        if avg==0.0:
            avg=0.001
        for event,value in {k: v for k, v in sorted(tmp.items(), key=lambda item: item[1])}.items():
            if value/avg < 1.0:
                if overall:
                    skiplst.add(event)
                continue
            multiplier = int((-100+value*100)/avg)
            timestr = str(timedelta(seconds=int(value)))
            print(f"{event}: {multiplier} {timestr}")

mypath = 'duration'
filenames = next(walk(mypath), (None, None, []))[2]  # [] if no file
storage_job = {}
for f in sorted(filenames):
    print(f)
    storage_job[f] = {}
    with open(f"{mypath}/{f}",'r') as fp:
        for line in fp.readlines():
            identifier,value = line.split(' ')
            value = int(value)
            action,section = identifier.split('|')
            if 'begin|' in line:
                assert section not in storage_job[f].keys()
                storage_job[f][section] = {action: value}
            elif 'end|' in line:
                assert section in storage_job[f].keys()
                storage_job[f][section][action] = value
# First print the overall job stats
skiplst = set() # Dont report slow jobs in next step
get_data(True,'job')

# Now generate the same stats per CI job
for job in storage_job.keys():
    if job not in skiplst:
        get_data(False,job)
