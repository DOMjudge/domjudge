# Main Makefile
#
# $Id$

# Define TOPDIR from shell command and _not_ $(PWD) because that gives
# pwd before changing to a 'make -C <dir>' <dir>:
export TOPDIR = $(shell pwd)

REC_TARGETS=build domserver install-domserver judgehost install-judgehost \
            docs install-docs config submitclient

# Global Makefile definitions
include $(TOPDIR)/Makefile.global

default:
	@echo "No default target"
	@echo
	@echo "Try:"
	@echo " - make domserver"
	@echo " - make judgehost"
	@echo " - make docs"
	@echo " - make submitclient"
	@echo or
	@echo " - make install-domserver"
	@echo " - make install-judgehost"
	@echo " - make install-docs"
	@echo or
	@echo " - make build"
	@echo " - make clean"
	@echo " - make distclean"
	@exit 1

install:
	@echo "Try:"
	@echo " - make install-domserver"
	@echo " - make install-judgehost"
	@echo " - make install-docs"
	@exit 1

# MAIN TARGETS
build domserver judgehost: paths.mk config
install-domserver: domserver domserver-create-dirs
install-judgehost: judgehost judgehost-create-dirs
install-docs: docs-create-dirs
dist: configure

# List of SUBDIRS for recursive targets:
config:            SUBDIRS=etc doc lib sql www judge submit tests misc-tools
build:             SUBDIRS=        lib         judge submit tests misc-tools
domserver:         SUBDIRS=etc             www       submit
install-domserver: SUBDIRS=etc     lib sql www       submit
judgehost:         SUBDIRS=etc                 judge
install-judgehost: SUBDIRS=etc     lib         judge              misc-tools
docs:              SUBDIRS=    doc
install-docs:      SUBDIRS=    doc         www
submitclient:      SUBDIRS=                          submit
dist:              SUBDIRS=    doc                                misc-tools
clean:             SUBDIRS=etc doc lib sql www judge submit tests misc-tools
distclean:         SUBDIRS=etc doc lib sql www judge submit tests misc-tools
maintainer-clean:  SUBDIRS=etc doc lib sql www judge submit tests misc-tools

domserver-create-dirs:
	$(INSTALL_DIR) $(addprefix $(DESTDIR),$(domserver_dirs))
# Fix permissions for special directories (don't touch tmpdir when FHS enabled):
	-$(INSTALL_USER)    -m 0700 -d $(DESTDIR)$(domserver_logdir)
	-$(INSTALL_WEBSITE) -m 0770 -d $(DESTDIR)$(domserver_submitdir)
ifneq "$(fhs_enabled)" "yes"
	-$(INSTALL_WEBSITE) -m 0770 -d $(DESTDIR)$(domserver_tmpdir)
endif

judgehost-create-dirs:
	$(INSTALL_DIR) $(addprefix $(DESTDIR),$(judgehost_dirs))
# Fix permissions for special directories (don't touch tmpdir when FHS enabled):
	-$(INSTALL_USER) -m 0700 -d $(DESTDIR)$(judgehost_logdir)
	-$(INSTALL_USER) -m 0711 -d $(DESTDIR)$(judgehost_judgedir)
ifneq "$(fhs_enabled)" "yes"
	-$(INSTALL_USER) -m 0700 -d $(DESTDIR)$(judgehost_tmpdir)
endif

docs-create-dirs:
	$(INSTALL_DIR) $(addprefix $(DESTDIR),$(docs_dirs))

install-docs-l:
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir) README ChangeLog COPYING*

install-domserver-l install-judgehost-l:
	@if [ `id -u` -ne 0 ]; then \
		echo "**************************************************************" ; \
		echo "You do not seem to have root privileges, which are needed" ; \
		echo "**************************************************************" ; \
	fi

dist-l:
	$(MAKE) clean-autoconf

# Run aclocal separately from autoreconf, which doesn't pass -I option.
aclocal.m4: configure.ac $(wildcard m4/*.m4)
	aclocal -I m4

configure: configure.ac aclocal.m4
	autoreconf

paths.mk:
	@echo "The file 'paths.mk' is not available. Probably you"
	@echo "have not run './configure' yet, aborting..."
	@exit 1

# Configure for running in source tree, not meant for normal use:
maintainer-conf: configure
	./configure $(subst 1,-q,$(QUIET)) --prefix=$(PWD) \
	            --with-domserver_root=$(PWD) \
	            --with-judgehost_root=$(PWD) \
	            --with-domjudge_docdir=$(PWD)/doc \
	            --with-domserver_logdir=$(PWD)/output/log \
	            --with-judgehost_logdir=$(PWD)/output/log \
	            --with-domserver_tmpdir=$(PWD)/output/tmp \
	            --with-judgehost_tmpdir=$(PWD)/output/tmp \
	            --with-judgehost_judgedir=$(PWD)/output/judging \
	            --with-domserver_submitdir=$(PWD)/output/submissions \
	            CFLAGS='-g -O2 -Wall -fstack-protector -fPIE -Wformat -Wformat-security' \
	            CXXFLAGS='-g -O2 -Wall -fstack-protector -fPIE -Wformat -Wformat-security' \
	            LDFLAGS='-pie' \
	            $(CONFIGURE_FLAGS)

# Install the system in place: don't really copy stuff, but create
# symlinks where necessary to let it work from the source tree.
# This stuff is a hack!
maintainer-install: domserver judgehost docs submitclient \
                    domserver-create-dirs judgehost-create-dirs
# Replace lib{judge,submit}dir with symlink to prevent lots of symlinks:
	-rmdir $(judgehost_libjudgedir) $(domserver_libsubmitdir)
	-rm -f $(judgehost_libjudgedir) $(domserver_libsubmitdir)
	ln -sf $(PWD)/judge  $(judgehost_libjudgedir)
	ln -sf $(PWD)/submit $(domserver_libsubmitdir)
	ln -sfn $(PWD)/doc $(domserver_wwwdir)/jury/doc
	mkdir -p $(judgehost_bindir)
	ln -sf $(PWD)/judge/runguard $(judgehost_bindir)
# This doesn't work in FreeBSD:
	su -c "chown root:root judge/runguard ; chmod u+s,a+x judge/runguard"

# Removes created symlinks; generated logs, submissions, etc. remain in output subdir.
maintainer-uninstall:
	rm -f $(judgehost_libjudgedir) $(domserver_libsubmitdir)
	rm -f $(domserver_wwwdir)/jury/doc
	rm -rf $(judgehost_bindir)

distclean-l: clean-autoconf
	-rm -f paths.mk

maintainer-clean-l:
	-rm -f configure aclocal.m4

clean-autoconf:
	-rm -rf config.status config.cache config.log autom4te.cache

.PHONY: $(addsuffix -create-dirs,domserver judgehost docs) check-root \
        clean-autoconf $(addprefix maintainer-,conf install uninstall)
