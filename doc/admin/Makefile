ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

ifneq ($(DOC_BUILD_ENABLED),no)
docs distdocs: admin-manual.pdf admin-manual.html
endif

install-docs: docs
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/admin \
		admin-manual.pdf admin-manual*.html

admin-manual.pdf: %.pdf: %.sgml $(TOPDIR)/doc/domjudge-overview-inc.sgml \
                                $(TOPDIR)/doc/docs_header.tex
	linuxdoc -B latex -o pdf -P "`cat $(TOPDIR)/doc/docs_header.tex`" $< >/dev/null

admin-manual.html: %.html: %.sgml  $(TOPDIR)/doc/domjudge-overview-inc.sgml \
                                   $(TOPDIR)/doc/docs_header.html
	linuxdoc -B html --header=$(TOPDIR)/doc/docs_header.html $< >/dev/null

maintainer-clean-l:
	-rm -f admin-manual*.html admin-manual.pdf

.PHONY: docs distdocs install-docs
