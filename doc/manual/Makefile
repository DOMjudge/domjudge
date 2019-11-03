ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

SUBST_CONFIGS = version.py

$(SUBST_CONFIGS): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

config: $(SUBST_CONFIGS)

ifeq ($(BUILD_DOCS),yes)
docs: config
# KLUDGE: call make instead of a dependency so that the html target
# will run after config from inside the doc target.
docs distdocs:
	$(MAKE) html
endif

install-docs: docs
	$(call install_tree,$(DESTDIR)$(domjudge_docdir)/manual,build/html)

clean-l:
	rm -rf build

distclean-l:
	-rm -f $(SUBST_CONFIGS)

html:
	sphinx-build -M $@ . build

.PHONY: docs distdocs install-docs html
