ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

FIGS = submitflow
FIGSPNG = $(addsuffix .png,$(FIGS))
FIGSEPS = $(addsuffix .eps,$(FIGS))

default: docs

%.png: %.fig
	fig2dev -L png $< $@

%.eps: %.fig
	fig2dev -L eps -m 0.5 $< $@

docs: admin-manual.pdf admin-manual.html

install-docs: docs
	$(INSTALL_DATA) -t $(domjudge_docdir)/admin admin-manual.pdf admin-manual*.html $(FIGSPNG)

admin-manual.pdf: admin-manual.sgml ../docs_header.tex $(FIGSPNG) $(FIGSEPS)
	linuxdoc -B latex -o pdf -P "`cat ../docs_header.tex`" $< >/dev/null

admin-manual.html: admin-manual.sgml ../docs_header.html $(FIGSPNG)
	linuxdoc -B html --header=../docs_header.html $< >/dev/null

dist: docs

maintainer-clean-l:
	-rm -f $(FIGSPNG) $(FIGSEPS) admin-manual*.html admin-manual.pdf

.PHONY:
