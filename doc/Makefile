TARGETS = team-manual team-manual-NL

TARGETSDVI = $(addsuffix .dvi,$(TARGETS))
TARGETSPS  = $(addsuffix .ps ,$(TARGETS))
TARGETSPDF = $(addsuffix .pdf,$(TARGETS))

LATEXOPTIONS = --file-line-error-style

all: build

build: dvi ps pdf

$(TARGETSDVI): %.dvi: %.tex logos/DOMjudgelogo.eps systemconfig.tex example.*
	latex $(LATEXOPTIONS) $<
	latex $(LATEXOPTIONS) $<
	latex $(LATEXOPTIONS) $<

$(TARGETSPS): %.ps: %.dvi
	dvips $<

$(TARGETSPDF): %.pdf: %.dvi
	dvipdf $<

dvi: $(TARGETSDVI)
ps:  $(TARGETSPS)
pdf: $(TARGETSPDF)

texclean:
	-rm -f *.{aux,log,toc}

clean: texclean
	-rm -f $(TARGETSDVI) $(TARGETSPS) $(TARGETSPDF)
	-rm -f *~

.PHONY: build dvi ps pdf clean texclean
