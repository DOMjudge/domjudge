ifndef TOPDIR
TOPDIR=..
endif

REC_TARGETS = config dist docs install-docs clean distclean maintainer-clean

include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = admin team judge

config: SUBDIRS=team

install-docs-l:
	$(INSTALL_DATA) -t $(domjudge_docdir)/logos logos/DOMjudgelogo.pdf logos/DOMjudgelogo.png
	$(INSTALL_DATA) -t $(domjudge_docdir)/examples examples/example.*
