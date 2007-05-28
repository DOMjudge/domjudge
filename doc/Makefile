ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

LATEXFLAGS = --interaction=errorstopmode

LATEXPACKAGES = svn expdlist
LATEXPKGFILES = $(addsuffix .sty,$(LATEXPACKAGES))

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = admin team judge
REC_TARGETS = docs clean distclean

$(LATEXPKGFILES): %.sty:
	@if [ ! -f $*.zip ]; then \
		wget -N -nv ftp://tug.ctan.org/pub/tex-archive/macros/latex/contrib/$*.zip || \
		(echo "Couldn't download TeX package '$*': download manually." && exit 1) ; \
	fi
	unzip -q $*.zip
	cd $* && latex $(LATEXFLAGS) $*.ins >/dev/null
	ln -s $*/$*.sty .

cleanlatexpackages:
	for i in $(LATEXPACKAGES) ; do rm -rf $$i{,.sty,.zip} ; done

distclean: cleanlatexpackages

$(REC_TARGETS): %:
	for dir in $(SUBDIRS) ; do $(MAKE) -C $$dir $@ || exit 1 ; done

.PHONY: docs cleanlatexpackages
