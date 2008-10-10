ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = admin team judge
REC_TARGETS = docs install-docs clean dist distclean maintainer-clean

default: docs

install-docs: create-docs-dirs

create-docs-dirs:
	install -d $(domjudge_docdir)

$(REC_TARGETS): %:
	for dir in $(SUBDIRS) ; do $(MAKE) -C $$dir $@ || exit 1 ; done

.PHONY: create-docs-dirs
