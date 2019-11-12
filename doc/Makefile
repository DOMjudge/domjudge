ifndef TOPDIR
TOPDIR=..
endif

REC_TARGETS = docs install-docs distdocs

include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = manual team examples

install-docs-l:
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/logos logos/DOMjudgelogo.pdf
