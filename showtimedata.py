import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import numpy as np
from os import walk
from matplotlib import rcParams
rcParams.update({'figure.autolayout': True})

from matplotlib.pyplot import figure

figure(figsize=(16, 6), dpi=80)

begin = []
end   = []
event = []

mypath = 'duration'
filenames = next(walk(mypath), (None, None, []))[2]  # [] if no file
for f in filenames:
    event.append(f)
    with open(f"{mypath}/{f}",'r') as fp:
        for line in fp.readlines():
            if 'job_begin' in line:
                begin.append(int(line.split(' ')[1]))
            elif 'job_end' in line:
                end.append(int(line.split(' ')[1]))
offset = min(begin)
begin = np.array(begin)-offset
end =   np.array(end)-offset

plt.barh(range(len(begin)),  end-begin, left=begin)

plt.xlim([-10,max(end)+10])

plt.yticks(range(len(begin)), event)
plt.savefig('time.png')
