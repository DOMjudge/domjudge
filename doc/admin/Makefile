ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

docs: admin-manual.pdf admin-manual.html

admin-manual.pdf: admin-manual.sgml
	linuxdoc -B latex -o pdf $<

admin-manual.html: admin-manual.sgml
	linuxdoc -B html $<

distclean:
	-rm -f admin-manual*.html admin-manual.pdf

.PHONY: docs
