ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = team-manual team-manual-NL

TARGETSPDF = $(addsuffix .pdf,$(TARGETS))

LATEX = pdflatex
LATEXFLAGS = --interaction=errorstopmode

CONFIGDIR = $(TOPDIR)/etc

LATEXPACKAGES = $(addsuffix .sty,svn expdlist)

docs: pdf

pdf: $(TARGETSPDF)
# Clean-up generated tex-files afterwards:
	$(MAKE) texclean

$(TARGETSPDF): %.pdf: %.tex $(CONFIGDIR)/config.tex team-manual-preamble.tex \
                            examples/example.* logos/DOMjudgelogo.* $(LATEXPACKAGES)
	-$(LATEX) $(LATEXFLAGS) $< < /dev/null &> /dev/null
	-$(LATEX) $(LATEXFLAGS) $< < /dev/null &> /dev/null
	 $(LATEX) $(LATEXFLAGS) $< < /dev/null

$(CONFIGDIR)/config.tex:
	$(MAKE) -C $(CONFIGDIR) config.tex

$(LATEXPACKAGES): %.sty:
	if [ ! -f $*.zip ]; then \
		wget -N ftp://tug.ctan.org/pub/tex-archive/macros/latex/contrib/$*.zip || \
		(echo "Couldn't download TeX package '$*': download manually." && exit 1) ; \
	fi
	unzip $*.zip
	cd $* && latex $*.ins
	ln -s $*/$*.sty .

texclean:
	-rm -f $(addsuffix .aux,$(TARGETS)) \
	       $(addsuffix .log,$(TARGETS)) \
	       $(addsuffix .toc,$(TARGETS)) \
	       systemconfig.aux

clean: texclean
	-rm -f $(TARGETSPDF)

distclean:
	for i in $(basename $(LATEXPACKAGES)) ; do \
		rm -rf $$i{,.sty,.zip} ; \
	done

.PHONY: docs pdf texclean
