all: documentation

-include $(TOPDIR)/Makefile.global

TARGETS = team-manual team-manual-NL

TARGETSDVI = $(addsuffix .dvi,$(TARGETS))
TARGETSPS  = $(addsuffix .ps ,$(TARGETS))
TARGETSPDF = $(addsuffix .pdf,$(TARGETS))

LATEXOPTIONS = --interaction=errorstopmode

CONFIGDIR = ../etc

documentation: ps pdf

dvi: $(TARGETSDVI)
# Clean-up generated tex-files afterwards:
	$(MAKE) texclean

ps:  $(TARGETSPS)
pdf: $(TARGETSPDF)

$(TARGETSDVI): %.dvi: %.tex $(CONFIGDIR)/global.cfg \
                            $(CONFIGDIR)/config.template.tex \
                            $(CONFIGDIR)/config.tex \
                            example.* logos/DOMjudgelogo.eps \
                            expdlist.sty svn.sty 
	latex $(LATEXOPTIONS) $<
	latex $(LATEXOPTIONS) $< &> /dev/null
	latex $(LATEXOPTIONS) $< &> /dev/null

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

dviclean:
	-rm -f $(TARGETSDVI)

clean: texclean
	-rm -f $(TARGETSPS) $(TARGETSPDF)

.PHONY: documentation dvi ps pdf texclean dviclean
