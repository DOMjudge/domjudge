ifndef TOPDIR
TOPDIR=../..
endif

REC_TARGETS = inplace-install inplace-uninstall

include $(TOPDIR)/Makefile.global

ifneq ($(QUIET),)
export LATEXMKOPTS=-quiet
QUIET_REDIRECT = >/dev/null 2>&1
endif

SUBST_CONFIGS = version.py substitutions.py

$(SUBST_CONFIGS): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

config: $(SUBST_CONFIGS)

docs: config
# KLUDGE: call make instead of a dependency so that the html target
# will run after config from inside the doc target.
docs:
	$(MAKE) html

distdocs:
	$(MAKE) html
# Run make clean here to get a cleaner tarball and make sure
# that make distclean returns to the original tarball state.
	$(MAKE) clean

install-docs: docs
	$(call install_tree,$(DESTDIR)$(domjudge_docdir)/manual,build/html)

inplace-install: docs
inplace-install-l:
	ln -sf build/html

inplace-uninstall-l:
	-rm -f html

clean-l:
	rm -rf build/doctrees

maintainer-clean-l:
	rm -rf build conf_ref.rst

distclean-l:
	-rm -f $(SUBST_CONFIGS) html

conf_ref.rst: gen_conf_ref.py
	./gen_conf_ref.py

html: conf_ref.rst
	sphinx-build -M $@ . build $(subst 1,-Q,$(QUIET))

.PHONY: docs distdocs install-docs inplace-install html
