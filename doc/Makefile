all: build

-include $(TOPDIR)/Makefile.global

TARGETS = team-manual team-manual-NL

TARGETSDVI = $(addsuffix .dvi,$(TARGETS))
TARGETSPS  = $(addsuffix .ps ,$(TARGETS))
TARGETSPDF = $(addsuffix .pdf,$(TARGETS))

LATEXOPTIONS = --file-line-error-style

CONFIGDIR = ../etc

build: ps pdf

dvi: $(TARGETSDVI)
# Clean-up generated tex-files afterwards:
	make texclean

ps:  $(TARGETSPS)
pdf: $(TARGETSPDF)

$(TARGETSDVI): %.dvi: %.tex $(CONFIGDIR)/global.cfg \
                            $(CONFIGDIR)/config.template.tex \
                            example.* logos/DOMjudgelogo.eps \
                            expdlist.sty svn.sty 
	latex $(LATEXOPTIONS) $< &> /dev/null
	latex $(LATEXOPTIONS) $< &> /dev/null
	latex $(LATEXOPTIONS) $<

$(TARGETSPS): %.ps: %.dvi
	dvips $<

$(TARGETSPDF): %.pdf: %.dvi
	dvipdf $<

texclean:
	-rm -f $(addsuffix .aux,$(TARGETS)) \
	       $(addsuffix .log,$(TARGETS)) \
	       $(addsuffix .toc,$(TARGETS))

clean: texclean
	-rm -f $(TARGETSPS) $(TARGETSPDF)

.PHONY: dvi ps pdf texclean
