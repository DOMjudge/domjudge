ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

SUBST_CONFIGS = version.py

$(SUBST_CONFIGS): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

config: $(SUBST_CONFIGS)

ifeq ($(BUILD_DOCS),yes)
docs distdocs: html
endif

install-docs: docs

clean-l:
	rm -rf build

distclean-l:
	-rm -f $(SUBST_CONFIGS)

html: config
	sphinx-build -M $@ . build

.PHONY: docs distdocs install-docs html
