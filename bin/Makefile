all: build install-setuid

-include $(TOPDIR)/Makefile.global

TARGETS = runguard beep mkstemps tempfile

# Targets to be installed setuid root:
SUIDTARGETS=runguard beep
# Files where to save intermediate (non-setuid) builds:
SUIDBUILDS=$(addsuffix .build,$(SUIDTARGETS))
# Where to install them:
SUIDINSTALLDIR=$(PWD)
# Command to become root:
SUROOT=su root
#SUROOT=sudo -u root /bin/bash -c

build: $(TARGETS)

install: install-setuid

$(SUIDTARGETS): %: %.build

$(SUIDBUILDS): %.build: %.c
	$(CXX) $(CFLAGS) $(CXXFLAGS) $< -o $@

runguard.build: ../etc/config.h

install-setuid: $(SUIDBUILDS)
	mkdir -p $(SUIDINSTALLDIR)
	cd $(SUIDINSTALLDIR) && rm -f $(SUIDTARGETS)
	for i in $(SUIDTARGETS) ; do cp -a $$i.build $(SUIDINSTALLDIR)/$$i ; done
	$(SUROOT) 'cd $(SUIDINSTALLDIR) && \
	           chown root.root $(SUIDTARGETS) && \
	           chmod u+s $(SUIDTARGETS)'
ifneq ($(SUIDINSTALLDIR),$(PWD))
	for i in $(SUIDTARGETS) ; do ln -sf $(INSTALLDIR)/$$i ; done
endif

clean:
	-rm -f $(TARGETS) $(SUIDBUILDS)

.PHONY: install-setuid $(SUIDTARGETS)
