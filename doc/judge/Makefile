ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

SPEC_SGML = checktestdata-grammar-inc.sgml
SPEC_TXT  = $(SPEC_SGML:-inc.sgml=.txt)

docs: judge-manual.pdf judge-manual.html $(SPEC_TXT)

install-docs: docs
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/judge \
		judge-manual.pdf judge-manual*.html
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir) $(SPEC_TXT)

judge-manual.pdf: judge-manual.sgml $(SPEC_SGML) $(TOPDIR)/doc/docs_header.tex
	linuxdoc -B latex -o pdf -P "`cat $(TOPDIR)/doc/docs_header.tex`" $< >/dev/null

judge-manual.html: judge-manual.sgml $(SPEC_SGML) $(TOPDIR)/doc/docs_header.html
	linuxdoc -B html --header=$(TOPDIR)/doc/docs_header.html $< >/dev/null

$(SPEC_TXT): %.txt: %.sgml %-inc.sgml
	linuxdoc -B txt $< >/dev/null

dist: docs

maintainer-clean-l:
	-rm -f judge-manual*.html judge-manual.pdf $(SPEC_TXT)
