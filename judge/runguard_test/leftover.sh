#!/usr/bin/bash

# Leave a single long-lived process behind in the cgroup after we exit, so that
# runguard reports and kills it. Its output goes to /dev/null to keep the test
# independent of how runguard drains the child's output pipes.
sleep 30 > /dev/null 2>&1 &

echo "spawned $!"
