CFLAGS=-O2 -Wall
TARGETS=runguard beep mkstemps tempfile
SUIDTARGETS=runguard beep
SUIDBUILDS=$(addsuffix .build,$(SUIDTARGETS))

CSINSTALLDIR=/tmp/private/nkpjury/bin
CSDOMAIN=students.cs.uu.nl
DOMAIN=$(shell dnsdomainname)

ifeq ($(DOMAIN),$(CSDOMAIN))
INSTALLDIR=$(CSINSTALLDIR)
SUROOT=sudo -u root
else
INSTALLDIR=$(PWD)
SUROOT=su root
endif

all: $(TARGETS) install-setuid

$(SUIDTARGETS): %: %.build

$(SUIDBUILDS): %.build: %.c
	$(CXX) $(CFLAGS) $(CXXFLAGS) $< -o $@

runguard.build: ../etc/config.h

install-setuid: $(SUIDBUILDS)
	mkdir -p $(INSTALLDIR)
	cd $(INSTALLDIR) && rm -f $(SUIDTARGETS)
	for i in $(SUIDTARGETS) ; do cp -a $$i.build $(INSTALLDIR)/$$i ; done
	$(SUROOT) 'cd $(INSTALLDIR) && chown root.root $(SUIDTARGETS) && chmod u+s $(SUIDTARGETS)'
ifneq ($(INSTALLDIR),$(PWD))
	for i in $(SUIDTARGETS) ; do ln -sf $(INSTALLDIR)/$$i ; done
endif

clean:
	-rm -f $(TARGETS) $(addsuffix .o,$(TARGETS))

.PHONY: clean install-setuid runguard beep
