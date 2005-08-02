ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = team-manual team-manual-NL

TARGETSDVI = $(addsuffix .dvi,$(TARGETS))
TARGETSPS  = $(addsuffix .ps ,$(TARGETS))
TARGETSPDF = $(addsuffix .pdf,$(TARGETS))

LATEXOPTIONS = --interaction=errorstopmode

CONFIGDIR = $(TOPDIR)/etc

docs: ps pdf

dvi: $(TARGETSDVI)
# Clean-up generated tex-files afterwards:
	$(MAKE) texclean

ps:  $(TARGETSPS)
pdf: $(TARGETSPDF)

$(TARGETSDVI): %.dvi: %.tex $(CONFIGDIR)/config.tex \
                            example.* logos/DOMjudgelogo.* \
                            expdlist.sty svn.sty
	-latex $(LATEXOPTIONS) $< < /dev/null &> /dev/null
	-latex $(LATEXOPTIONS) $< < /dev/null &> /dev/null
	 latex $(LATEXOPTIONS) $< < /dev/null

$(TARGETSPS): %.ps: %.dvi
	dvips $<

$(TARGETSPDF): %.pdf: %.dvi
	dvipdf $<

$(CONFIGDIR)/config.tex:
	$(MAKE) -C $(CONFIGDIR) config.tex

texclean:
	-rm -f $(addsuffix .aux,$(TARGETS)) \
	       $(addsuffix .log,$(TARGETS)) \
	       $(addsuffix .toc,$(TARGETS)) \
	       systemconfig.aux

clean: texclean
	-rm -f $(TARGETSDVI) $(TARGETSPS) $(TARGETSPDF)

.PHONY: docs dvi ps pdf texclean dviclean
