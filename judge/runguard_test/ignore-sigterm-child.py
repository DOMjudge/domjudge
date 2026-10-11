#!/usr/bin/python3

# Die on the SIGTERM that runguard sends first, but leave behind a child that
# ignores it, so that runguard has to escalate to a SIGKILL for the child.

import os
import signal
import time

if os.fork() == 0:
    signal.signal(signal.SIGTERM, signal.SIG_IGN)
    print("child ignoring SIGTERM", flush=True)
time.sleep(60)
