# Main Makefile
#
# $Id$

# Define TOPDIR from shell command and _not_ $(PWD) because that gives
# pwd before changing to a 'make -C <dir>' <dir>:
export TOPDIR = $(shell pwd)

# Global Makefile definitions
include $(TOPDIR)/Makefile.global

default:
	@echo "No default target"
	@echo
	@echo "Try:"
	@echo " - make domserver"
	@echo " - make judgehost"
	@echo " - make db"
	@echo " - make docs"
	@echo " - make submitclient"
	@echo or
	@echo " - make install-domserver"
	@echo " - make install-judgehost"
	@echo " - make install-db"
	@echo " - make install-docs"
	@echo or
	@echo " - make build"
	@echo " - make test"
	@echo " - make clean"
	@echo " - make distclean"
	@exit 1

# MAIN TARGETS
domserver:
install-domserver: domserver
judgehost:
install-judgehost: judgehost
dist: maintainer-clean

# List all targets that exist in subdirs too, and optionally list in
# which subdirs they are, overriding default SUBDIRS list.
REC_TARGETS=build domserver install-domserver judgehost install-judgehost \
            docs install-docs submitclient test dist \
            clean-r distclean-r maintainer-clean-r
SUBDIRS=bin doc etc judge lib submit www test-sources misc-tools

build:             SUBDIRS=bin lib judge submit test-sources misc-tools
domserver:         SUBDIRS=etc submit
install-domserver: SUBDIRS=etc lib submit www
judgehost:         SUBDIRS=bin etc judge
install-judgehost: SUBDIRS=bin etc judge lib
docs:              SUBDIRS=doc
install-docs:      SUBDIRS=doc
submitclient:      SUBDIRS=submit
test:              SUBDIRS=tests
maintainer-clean:  SUBDIRS=doc
dist:              SUBDIRS=doc

install-domserver: domserver-create-dirs
install-judgehost: judgehost-create-dirs

domserver-create-dirs:
	install -d $(domserver_dirs)

judgehost-create-dirs:
	install -d $(judgehost_dirs)

$(REC_TARGETS): %:
	for dir in $(SUBDIRS) ; do $(MAKE) -C $$dir $@ || exit 1 ; done

configure: configure.ac $(wildcard m4/*.m4)
	# you really should call ./autogen.sh yourself ...
	./autogen.sh

# Configure for running in source tree, not meant for normal use:
maintainer-conf: configure
	./configure $(subst 1,-q,$(QUIET)) --prefix=$(PWD) \
	            --with-domserver_root=$(PWD) \
	            --with-judgehost_root=$(PWD) \
	            --with-domserver_logdir=$(PWD)/output/log \
	            --with-judgehost_logdir=$(PWD)/output/log \
	            --with-judgehost_judgedir=$(PWD)/output/judging \
	            CFLAGS='-g -O2 -Wall -fstack-protector -fPIE -Wformat -Wformat-security' \
	            CXXFLAGS='-g -O2 -Wall -fstack-protector -fPIE -Wformat -Wformat-security' \
	            LDFLAGS='-pie' \
	            $(CONFIGURE_FLAGS)

maintainer-clean: clean-autoconf

clean-autoconf:
	-rm -rf config.status config.cache config.log autom4te.cache
	-for i in `find . -name \*.in` ; do rm -f $${i%.in} ; done

.PHONY: domserver-create-dirs judgehost-create-dirs clean-autoconf
