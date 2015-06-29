ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

SPEC_SGML = problem-format-inc.sgml
SPEC_TXT  = $(SPEC_SGML:-inc.sgml=.txt)

docs distdocs: judge-manual.pdf judge-manual.html $(SPEC_TXT)

install-docs: docs
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/judge \
		judge-manual.pdf judge-manual*.html
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir) $(SPEC_TXT)

judge-manual.pdf: %.pdf: %.sgml $(TOPDIR)/doc/domjudge-overview-inc.sgml \
                                $(TOPDIR)/doc/docs_header.tex $(SPEC_SGML)
	linuxdoc -B latex -o pdf -P "`cat $(TOPDIR)/doc/docs_header.tex`" $< >/dev/null

judge-manual.html: %.html: %.sgml  $(TOPDIR)/doc/domjudge-overview-inc.sgml \
                                   $(TOPDIR)/doc/docs_header.html $(SPEC_SGML)
	linuxdoc -B html --header=$(TOPDIR)/doc/docs_header.html $< >/dev/null

$(SPEC_TXT): %.txt: %.sgml %-inc.sgml
	linuxdoc -B txt $< >/dev/null

maintainer-clean-l:
	-rm -f judge-manual*.html judge-manual.pdf $(SPEC_TXT)

.PHONY: docs distdocs install-docs
