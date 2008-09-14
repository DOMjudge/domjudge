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
	@echo " - make test"
	@echo " - make clean"
	@echo " - make distclean"
	@exit 1

# MAIN TARGETS
domserver:
install-domserver: domserver
judgehost:
install-judgehost: judgehost

# List all targets that exist in subdirs too, and optionally list in
# which subdirs they are, overriding default SUBDIRS list.
REC_TARGETS=build domserver install-domserver judgehost install-judgehost \
            docs install-docs submitclient clean distclean maintainer-clean \
            test
SUBDIRS=bin doc etc judge lib submit www test-programs test-sources

domserver:         SUBDIRS=etc submit
install-domserver: SUBDIRS=etc lib submit www
judgehost:         SUBDIRS=bin etc judge
install-judgehost: SUBDIRS=bin etc judge lib
docs:              SUBDIRS=doc
install-docs:      SUBDIRS=doc
submitclient:      SUBDIRS=submit
test:              SUBDIRS=tests
maintainer-clean:  SUBDIRS=doc

install-domserver: domserver-create-dirs
install-judgehost: judgehost-create-dirs

domserver-create-dirs:
	install -d $(domserver_dirs)

judgehost-create-dirs:
	install -d $(judgehost_dirs)

$(REC_TARGETS): %:
	for dir in $(SUBDIRS) ; do $(MAKE) -C $$dir $@ || exit 1 ; done

maintainer-clean: clean-autoconf

clean-autoconf:
	-rm -f config.status config.cache config.log \
		configure paths.mk etc/Makefile

.PHONY: domserver-create-dirs judgehost-create-dirs clean-autoconf
