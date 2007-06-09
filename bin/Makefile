ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = runguard beep mkstemps tempfile

# Some configuration of programs that need set-uid root permissions:
SUIDTARGETS=runguard beep
SUIDBUILDS=$(addsuffix .build,$(SUIDTARGETS))

build: $(TARGETS)

install: build install-setuid

$(SUIDTARGETS): %: %.build

$(SUIDBUILDS): %.build: %.c
	$(CXX) $(CXXFLAGS) $< -o $@

runguard.build: $(TOPDIR)/etc/config.h

install-setuid: $(SUIDBUILDS)
	@echo $(SUIDINSTALLDIR)
	mkdir -p $(SUIDINSTALLDIR)
	cd $(SUIDINSTALLDIR) && rm -f $(SUIDTARGETS)
	for i in $(SUIDTARGETS) ; do cp -p $$i.build $(SUIDINSTALLDIR)/$$i ; done
	$(ROOTCMD) 'cd $(SUIDINSTALLDIR) && \
	            chown root $(SUIDTARGETS) && \
	            chmod 4755 $(SUIDTARGETS)'
# Make symlinks to setuid binaries when not located here:
ifneq ($(SUIDINSTALLDIR),$(CURDIR))
	for i in $(SUIDTARGETS) ; do ln -sf $(SUIDINSTALLDIR)/$$i ; done
endif

clean:
	-rm -f $(TARGETS) $(SUIDBUILDS)

.PHONY: install-setuid $(SUIDTARGETS)
