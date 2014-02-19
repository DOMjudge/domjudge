ifndef TOPDIR
TOPDIR=..
endif

REC_TARGETS = config docs install-docs

include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = admin team judge examples

config: SUBDIRS=team

# Cleanup LaTeX logs, etc. after generating documentation for distribution.
distdocs: docs
	$(MAKE) clean

install-docs-l:
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/logos logos/DOMjudgelogo.pdf logos/DOMjudgelogo.png
