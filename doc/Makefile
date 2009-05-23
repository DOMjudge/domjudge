ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = admin team judge
REC_TARGETS = docs install-docs dist clean-r distclean-r maintainer-clean-r

default: docs

install-docs: install-docs-l

install-docs-l:
	$(INSTALL_DATA) -t $(domjudge_docdir)/logos logos/DOMjudgelogo.pdf logos/DOMjudgelogo.png
	$(INSTALL_DATA) -t $(domjudge_docdir)/examples examples/example.*

$(REC_TARGETS): %:
	for dir in $(SUBDIRS) ; do $(MAKE) -C $$dir $@ || exit 1 ; done

.PHONY: install-docs-l
