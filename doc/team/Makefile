ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

CONFIGDIR = $(TOPDIR)/etc
SHELL = /bin/bash

TARGETS = team-manual team-manual-NL

TARGETSPDF = $(addsuffix .pdf,$(TARGETS))

LATEXPACKAGES = svn expdlist
LATEXPKGFILES = $(addprefix ../,$(addsuffix .sty,$(LATEXPACKAGES)))

LATEX = pdflatex
LATEXFLAGS = --interaction=errorstopmode

default: docs

docs: pdf

pdf: $(TARGETSPDF)
# Clean-up generated tex-files afterwards:
	$(MAKE) texclean

$(TARGETSPDF): %.pdf: %.tex $(CONFIGDIR)/config.tex team-manual-preamble.tex \
                            ../examples/example.* ../logos/DOMjudgelogo.* $(LATEXPKGFILES)
	@NRUNS=3 ; printf "Running $$NRUNS passes of '$(LATEX) $(LATEXFLAGS) $<'..." ; \
	 for ((i=1; i<=NRUNS; i++)) ; do \
		if ! $(LATEX) $(LATEXFLAGS) $< </dev/null &>/dev/null ; then \
			printf "\nTeX-ing failed in run $$i, see '$*.log' for details.\n" ; \
			exit 1 ; \
		else printf " $$i" ; fi ; \
	 done ; printf "\n" ;

$(CONFIGDIR)/config.tex:
	$(MAKE) -C $(CONFIGDIR) config.tex

$(LATEXPKGFILES): ../%:
	$(MAKE) -C .. $*

texclean:
	-rm -f $(addsuffix .aux,$(TARGETS)) \
	       $(addsuffix .log,$(TARGETS)) \
	       $(addsuffix .toc,$(TARGETS)) \
	       systemconfig.aux

clean: texclean

distclean:
	-rm -f $(TARGETSPDF)

.PHONY: docs pdf texclean
