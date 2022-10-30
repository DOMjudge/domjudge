ifndef TOPDIR
TOPDIR=..
endif

REC_TARGETS = docs install-docs distdocs inplace-install inplace-uninstall

include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = manual examples
inplace-install inplace-uninstall: SUBDIRS=manual

install-docs-l:
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/logos logos/DOMjudgelogo.pdf
