# Main Makefile
#
# $Id$

# Define TOPDIR from shell command and _not_ $(PWD) because that gives
# pwd before changing to a 'make -C <dir>' <dir>:
export TOPDIR = $(shell pwd)

REC_TARGETS=build domserver install-domserver judgehost install-judgehost \
            docs install-docs config submitclient test dist \
            clean distclean maintainer-clean

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
	@echo " - make test"
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
build domserver judgehost: config
install-domserver: domserver domserver-create-dirs
install-judgehost: judgehost judgehost-create-dirs
install-docs: docs-create-dirs
dist: configure

# List of default SUBDIRS for recursive targets; override below.
SUBDIRS=bin doc etc judge lib submit sql www test-sources misc-tools

config:            SUBDIRS=bin etc doc sql www judge submit test-sources
build:             SUBDIRS=bin lib judge submit test-sources misc-tools
domserver:         SUBDIRS=etc submit www
install-domserver: SUBDIRS=bin etc lib submit sql www
judgehost:         SUBDIRS=bin etc judge
install-judgehost: SUBDIRS=bin etc judge lib
docs:              SUBDIRS=doc
install-docs:      SUBDIRS=doc www
submitclient:      SUBDIRS=submit
test:              SUBDIRS=tests
maintainer-clean:  SUBDIRS=doc misc-tools
dist:              SUBDIRS=doc misc-tools
clean:
distclean:

domserver-create-dirs:
	$(INSTALL_DIR) $(domserver_dirs)
ifneq "$(fhs_enabled)" "yes"
	-$(INSTALL_PRIVATE) -m 0700 -d $(domserver_logdir)
	-$(INSTALL_WEBSITE) -m 0770 -d $(domserver_tmpdir)
	-$(INSTALL_WEBSITE) -m 0770 -d $(domserver_submitdir)
endif

judgehost-create-dirs:
	$(INSTALL_DIR) $(judgehost_dirs)
ifneq "$(fhs_enabled)" "yes"
	-$(INSTALL_PRIVATE) -m 0700 -d $(judgehost_tmpdir)
	-$(INSTALL_PRIVATE) -m 0700 -d $(judgehost_logdir)
	-$(INSTALL_PRIVATE) -m 0700 -d $(judgehost_judgedir)
endif

docs-create-dirs:
	$(INSTALL_DIR) $(docs_dirs)

install-docs-l:
	$(INSTALL_DATA) -t $(domjudge_docdir) README ChangeLog COPYING*

install-domserver-l install-judgehost-l:
	@if [ `id -u` -ne 0 ]; then \
		echo "**************************************************************" ; \
		echo "You do not seem to have root privileges, which are needed" ; \
		echo "**************************************************************" ; \
	fi

# Run aclocal separately from autoreconf, which doesn't pass -I option.
aclocal.m4: configure.ac $(wildcard m4/*.m4)
	aclocal -I m4

configure: configure.ac aclocal.m4
	autoreconf

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
# Replace libjudgedir with symlink to judge/, preventing lots of symlinks:
	-rmdir $(judgehost_libjudgedir)
	-rm -f $(judgehost_libjudgedir)
	ln -sf $(PWD)/judge $(judgehost_libjudgedir)
	ln -sf $(PWD)/submit/submit_copy.sh $(PWD)/submit/submit_db.php $(domserver_libsubmitdir)
	ln -sf $(PWD)/bin/runguard $(PWD)/bin/sh-static $(judgehost_libjudgedir)
	ln -sf $(PWD)/bin/alert $(judgehost_libdir)
	ln -sfn $(PWD)/doc $(domserver_wwwdir)/jury/doc
	su -c "chown root.root bin/runguard ; chmod u+s bin/runguard"

# Removes created symlinks; generated logs, submissions, etc. remain in output subdir.
maintainer-uninstall:
	rm -rf $(domserver_libsubmitdir)
	rm -f $(domserver_wwwdir)/jury/doc
	rm -f $(judgehost_libjudgedir)
	rm -f judge/runguard judge/sh-static lib/alert

distclean-l: clean-autoconf
	-rm -f paths.mk

clean-autoconf:
	-rm -rf config.status config.cache config.log autom4te.cache

.PHONY: $(addsuffix -create-dirs,domserver judgehost docs) check-root \
        clean-autoconf $(addprefix maintainer-,conf install uninstall)
