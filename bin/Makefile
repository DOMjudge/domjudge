CFLAGS=-O2 -Wall
TARGETS=runguard beep mkstemps tempfile

all:    $(TARGETS) inst-setuid

clean:
	$(RM) $(TARGETS) $(addsuffix .o,$(TARGETS))

inst-setuid: runguard beep
	su root 'chown root.root runguard beep; chmod u+s runguard beep'
