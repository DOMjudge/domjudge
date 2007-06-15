ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

LATEX = latex
LATEXFLAGS = --interaction=errorstopmode

LATEXPACKAGES = svn expdlist
LATEXZIPFILES = $(addsuffix .zip,$(LATEXPACKAGES))
LATEXSTYFILES = $(addsuffix .sty,$(LATEXPACKAGES))

# Comprehensive TeX Archive Network path to packages:
CTANPATH=ftp://tug.ctan.org/pub/tex-archive/macros/latex/contrib

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = admin team judge
REC_TARGETS = docs clean distclean

all: docs

$(LATEXZIPFILES): %.zip:
	@echo "Downloading LaTeX package '$*' from CTAN.org..." ; \
	if which wget >/dev/null ; then \
		wget -N -nv -T 60 $(CTANPATH)/$@ ; \
	else if which curl >/dev/null ; then \
		curl -f -O -m 120 $(CTANPATH)/$@ ; \
	else \
		echo -e "No wget or curl found, download manually from:\n\t$(CTANPATH)/$@"; \
		exit 1 ; \
	fi ; fi ; \
	if [ ! -f $@ ]; then \
		echo -e "Downloading failed, download manually from:\n\t$(CTANPATH)/$@"; \
		exit 1 ; \
	fi

$(LATEXSTYFILES): %.sty: %.zip
	unzip -oq $*.zip
	cd $* && $(LATEX) $(LATEXFLAGS) $*.ins >/dev/null
	ln -s $*/$*.sty .

cleanlatexpackages:
	rm -rf $(LATEXPACKAGES) $(LATEXZIPFILES) $(LATEXSTYFILES)

distclean: cleanlatexpackages

$(REC_TARGETS): %:
	for dir in $(SUBDIRS) ; do $(MAKE) -C $$dir $@ || exit 1 ; done

.PHONY: docs cleanlatexpackages
