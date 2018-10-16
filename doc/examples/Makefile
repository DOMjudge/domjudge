ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

PROBLEMS = hello fltcmp boolfind

INI = $(PROBLEMS:=.ini)
PDF = $(PROBLEMS:=.pdf)
ZIP = $(PROBLEMS:=.zip)

docs distdocs: $(PDF) $(ZIP)

# Used by sql/Makefile:
.pdf-generated: $(PDF)
	touch .pdf-generated

install-docs:
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/examples example.*
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/examples hello.pdf hello.in hello.out
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/examples fltcmp.pdf fltcmp.in fltcmp.out
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/examples boolfind.pdf boolfind.in boolfind.out

$(PDF): %.pdf: %.tex %.in %.out $(TOPDIR)/doc/logos/DOMjudgelogo.pdf
# Ugly hack to prevent running pdflatex, but do create a PDF file,
# since this is needed for DB installation.
ifeq ($(BUILD_DOCS),no)
	cp $(TOPDIR)/doc/logos/DOMjudgelogo.pdf $@
else
	pdflatex -interaction=nonstopmode $< > /dev/null
endif
	touch .pdf-generated

$(INI): %.ini:
	@[ -n "$(QUIET)" ] || echo "Generating '$@'..."
	@echo '; DOMjudge problem properties file' > $@
	@echo 'probid       = $*' >> $@
	@echo 'name         = $*' >> $@
	@echo 'timelimit    = 5'  >> $@
	@echo 'allow_submit = 1'  >> $@

# Use secondary expansion to use stem $* in dependencies:
.SECONDEXPANSION:
$(ZIP): %.zip: %.pdf %.ini $$(foreach ext,in out c cc java,$$(wildcard $$**.$$(ext)))
	TMPDIR=`mktemp -d --tmpdir dj_probzip_XXXXXX` ; \
	cp $^ $$TMPDIR ; mv $$TMPDIR/$*.ini $$TMPDIR/domjudge-problem.ini ; \
	zip -q -j -r $@ $$TMPDIR ; rm -rf $$TMPDIR

clean-l:
	-rm -f $(PROBLEMS:=.aux) $(PROBLEMS:=.log)

maintainer-clean-l:
	-rm -f $(INI) $(PDF) $(ZIP) .pdf-generated

.PHONY: docs distdocs install-docs
