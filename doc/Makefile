-include Makefile.global

TARGETS = team-manual team-manual-NL

TARGETSDVI = $(addsuffix .dvi,$(TARGETS))
TARGETSPS  = $(addsuffix .ps ,$(TARGETS))
TARGETSPDF = $(addsuffix .pdf,$(TARGETS))

LATEXOPTIONS = --file-line-error-style

all: build

build: ps pdf

dvi: $(TARGETSDVI)
# Clean-up generated tex-files afterwards:
	make texclean

ps:  $(TARGETSPS)
pdf: $(TARGETSPDF)

$(TARGETSDVI): %.dvi: %.tex expdlist.sty svn.sty logos/DOMjudgelogo.eps systemconfig.tex example.*
	latex $(LATEXOPTIONS) $<
	latex $(LATEXOPTIONS) $<
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
