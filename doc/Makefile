TARGETS = team-handleiding

TARGETSDVI = $(addsuffix .dvi,$(TARGETS))
TARGETSPS  = $(addsuffix .ps ,$(TARGETS))
TARGETSPDF = $(addsuffix .pdf,$(TARGETS))

LATEXOPTIONS = --file-line-error-style

all: dvi ps pdf

$(TARGETSDVI): %.dvi: %.tex
	latex $(LATEXOPTIONS) $<
	latex $(LATEXOPTIONS) $<
	latex $(LATEXOPTIONS) $<

$(TARGETSPS): %.ps: %.dvi
	dvips $<

$(TARGETSPDF): %.pdf: %.dvi
	dvipdf $<

team-handleiding.dvi: systemconfig.tex voorbeeld.*

.PHONY: dvi ps pdf clean texclean

dvi: $(TARGETSDVI)
ps:  $(TARGETSPS)
pdf: $(TARGETSPDF)

texclean:
	-rm -f *.{aux,log,toc}

clean: texclean
	-rm -f $(TARGETSDVI) $(TARGETSPS) $(TARGETSPDF)
