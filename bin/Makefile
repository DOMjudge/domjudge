CFLAGS=-O2 -Wall
TARGETS=runguard beep mkstemps tempfile

all: $(TARGETS) install-setuid

.PHONY: clean install-setuid

install-setuid: runguard beep
	su root 'chown root.root runguard beep; chmod u+s runguard beep'

clean:
	-rm -f $(TARGETS) $(addsuffix .o,$(TARGETS))
