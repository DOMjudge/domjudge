ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

docs: judge-manual.pdf judge-manual.html

judge-manual.pdf: judge-manual.sgml ../docs_header.tex
	linuxdoc -B latex -o pdf -P "`cat ../docs_header.tex`" $< >/dev/null

judge-manual.html: judge-manual.sgml ../docs_header.html
	linuxdoc -B html --header=../docs_header.html $<

distclean:
	-rm -f judge-manual*.html judge-manual.pdf

.PHONY: docs
