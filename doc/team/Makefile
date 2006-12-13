ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

CONFIGDIR = $(TOPDIR)/etc

TARGETS = team-manual team-manual-NL

TARGETSPDF = $(addsuffix .pdf,$(TARGETS))

LATEXPACKAGES = svn expdlist
LATEXPKGFILES = $(addprefix ../,$(addsuffix .sty,$(LATEXPACKAGES)))

LATEX = pdflatex
LATEXFLAGS = --interaction=errorstopmode

docs: pdf

pdf: $(TARGETSPDF)
# Clean-up generated tex-files afterwards:
	$(MAKE) texclean

$(TARGETSPDF): %.pdf: %.tex $(CONFIGDIR)/config.tex team-manual-preamble.tex \
                            ../examples/example.* ../logos/DOMjudgelogo.* $(LATEXPKGFILES)
	-$(LATEX) $(LATEXFLAGS) $< < /dev/null &> /dev/null
	-$(LATEX) $(LATEXFLAGS) $< < /dev/null &> /dev/null
	 $(LATEX) $(LATEXFLAGS) $< < /dev/null

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
