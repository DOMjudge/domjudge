ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

SUBST_CONFIGS = version.py substitutions.py

$(SUBST_CONFIGS): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

config: $(SUBST_CONFIGS)

ifeq ($(BUILD_DOCS),yes)
docs: config
# KLUDGE: call make instead of a dependency so that the html target
# will run after config from inside the doc target.
docs distdocs:
	$(MAKE) html team
endif

install-docs: docs
	$(call install_tree,$(DESTDIR)$(domjudge_docdir)/manual,build/html)
	$(call install_tree,$(DESTDIR)$(domjudge_docdir)/manual,build/team)

maintainer-install: docs
	ln -sf build/html
	ln -sf build/team

clean-l:
	rm -rf build/doctrees build/team/.doctrees conf_ref.rst

maintainer-clean-l:
	rm -rf build

distclean-l:
	-rm -f $(SUBST_CONFIGS) html team

conf_ref.rst: gen_conf_ref.py
	./gen_conf_ref.py

html: conf_ref.rst
	sphinx-build -M $@ . build $(subst 1,-Q,$(QUIET))

team:
	sphinx-build -b pdf . build/team $(subst 1,-Q,$(QUIET))

.PHONY: docs distdocs install-docs maintainer-install html team
