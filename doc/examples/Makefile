ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

BASE = hello fltcmp
PDF = $(BASE:=.pdf)

docs: dist

install-docs:
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/examples example.*
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/examples hello.pdf hello.in hello.out
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/examples fltcmp.pdf fltcmp.in fltcmp.out

$(PDF): %.pdf: %.tex %.in %.out ../logos/DOMjudgelogo.pdf
	pdflatex -interaction=nonstopmode $< > /dev/null

dist: $(PDF)
	$(MAKE) clean-l

clean-l:
	-rm -f $(BASE:=.aux) $(BASE:=.log)

maintainer-clean-l:
	-rm -f $(PDF)
