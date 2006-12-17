ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

docs: judge-manual.pdf judge-manual.html

judge-manual.pdf: judge-manual.sgml
	linuxdoc -B latex -o pdf $<

judge-manual.html: judge-manual.sgml
	linuxdoc -B html $<

distclean:
	-rm -f judge-manual*.html judge-manual.pdf

.PHONY: docs
