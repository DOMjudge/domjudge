ifndef TOPDIR
TOPDIR=..
endif

REC_TARGETS = docs install-docs distdocs

include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = admin team judge examples

# Cleanup LaTeX logs, etc. after generating documentation for distribution.
distdocs-l:
	$(MAKE) clean

install-docs-l:
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/logos logos/DOMjudgelogo.pdf logos/DOMjudgelogo.png
