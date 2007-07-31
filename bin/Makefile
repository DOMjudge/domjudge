ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = runguard beep

# Some configuration of programs that need set-uid root permissions:
SUIDTARGETS=runguard beep
SUIDBUILDS=$(addsuffix .build,$(SUIDTARGETS))

build: $(TARGETS)

install: build install-setuid

$(SUIDTARGETS): %: %.build

beep.build: %.build: %.c
	-$(CXX) $(CXXFLAGS) $< -o $@
	@if [ ! -x $@ ]; then \
		echo "Error building 'beep', probably because this is a non-Linux system." ; \
		echo "Replacing 'beep' by '/bin/true'." ; \
		cp /bin/true $@ ; \
	fi

runguard.build: %.build: %.c $(TOPDIR)/etc/config.h
	$(CXX) $(CXXFLAGS) $< -o $@

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
