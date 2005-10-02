ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = team-manual team-manual-NL

TARGETSPDF = $(addsuffix .pdf,$(TARGETS))

LATEX = pdflatex
LATEXFLAGS = --interaction=errorstopmode

CONFIGDIR = $(TOPDIR)/etc

docs: pdf

pdf: $(TARGETSPDF)
# Clean-up generated tex-files afterwards:
	$(MAKE) texclean

$(TARGETSPDF): %.pdf: %.tex $(CONFIGDIR)/config.tex team-manual-preamble.tex \
                            example.* logos/DOMjudgelogo.* \
                            expdlist.sty svn.sty
	-$(LATEX) $(LATEXFLAGS) $< < /dev/null &> /dev/null
	-$(LATEX) $(LATEXFLAGS) $< < /dev/null &> /dev/null
	 $(LATEX) $(LATEXFLAGS) $< < /dev/null

$(CONFIGDIR)/config.tex:
	$(MAKE) -C $(CONFIGDIR) config.tex

texclean:
	-rm -f $(addsuffix .aux,$(TARGETS)) \
	       $(addsuffix .log,$(TARGETS)) \
	       $(addsuffix .toc,$(TARGETS)) \
	       systemconfig.aux

clean: texclean
	-rm -f $(TARGETSPDF)

.PHONY: docs pdf texclean
