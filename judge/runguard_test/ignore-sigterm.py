#!/usr/bin/python3

# Ignore the SIGTERM that runguard sends first, so that it has to escalate to
# a SIGKILL to get rid of us.

import signal
import time

signal.signal(signal.SIGTERM, signal.SIG_IGN)
print("ignoring SIGTERM", flush=True)
time.sleep(60)
