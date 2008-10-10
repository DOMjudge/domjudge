ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

TARGETS = team-manual team-manual-NL

TARGETSPDF = $(addsuffix .pdf,$(TARGETS))

LATEX = pdflatex
LATEXFLAGS = --interaction=errorstopmode

default: docs

docs: pdf

install-docs: $(TARGETSPDF)
	cp -a -t $(domjudge_docdir) $^

pdf: $(TARGETSPDF)
# Clean-up generated tex-files afterwards:
	$(MAKE) texclean

$(TARGETSPDF): %.pdf: %.tex team-manual-preamble.tex \
                            ../examples/example.* ../logos/DOMjudgelogo.*
	@NRUNS=3 ; [ "$(QUIET)" ] || printf "Running $$NRUNS passes of '$(LATEX) $(LATEXFLAGS) $<'..." ; \
	 i=1; \
	 while [ $$i -le $$NRUNS ]; do \
		if ! $(LATEX) $(LATEXFLAGS) $< </dev/null >/dev/null ; then \
			printf "\nTeX-ing failed in run $$i, see '$*.log' for details.\n" ; \
			exit 1 ; \
		else [ "$(QUIET)" ] || printf " $$i" ; fi ; \
		i=$$(($$i+1)); \
	 done ; [ "$(QUIET)" ] || printf "\n" ;

texclean:
	-rm -f $(addsuffix .aux,$(TARGETS)) \
	       $(addsuffix .log,$(TARGETS)) \
	       $(addsuffix .toc,$(TARGETS)) \
	       systemconfig.aux

clean: texclean
	-rm -f $(TARGETSPDF)

distclean:

dist:

.PHONY: pdf texclean
