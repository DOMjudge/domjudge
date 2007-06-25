ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

all: docs
docs: admin-manual.pdf admin-manual.html

admin-manual.pdf: admin-manual.sgml ../docs_header.tex
	linuxdoc -B latex -o pdf -P "`cat ../docs_header.tex`" $< >/dev/null

admin-manual.html: admin-manual.sgml ../docs_header.html
	linuxdoc -B html --header=../docs_header.html $<

distclean:
	-rm -f admin-manual*.html admin-manual.pdf

.PHONY: docs
