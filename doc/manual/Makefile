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
docs:
	$(MAKE) html team

distdocs:
	$(MAKE) html team
# Run make clean here to get a cleaner tarball and make sure
# that make distclean returns to the original tarball state.
	$(MAKE) clean
endif

install-docs: docs
	$(call install_tree,$(DESTDIR)$(domjudge_docdir)/manual,build/html)
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/manual build/domjudge-team-manual.pdf

maintainer-install: docs
	ln -sf build/html
	ln -sf build/team

clean-l:
	rm -rf build/doctrees build/team/.doctrees
	-$(MAKE) -C build/team clean

maintainer-clean-l:
	rm -rf build conf_ref.rst

distclean-l:
	-rm -f $(SUBST_CONFIGS) html team

conf_ref.rst: gen_conf_ref.py
	./gen_conf_ref.py

html: conf_ref.rst
	sphinx-build -M $@ . build $(subst 1,-Q,$(QUIET))

team:
	sphinx-build -b latex . build/team $(subst 1,-Q,$(QUIET))
	$(MAKE) -C build/team domjudge-team-manual.pdf
	cp build/team/domjudge-team-manual.pdf build

.PHONY: docs distdocs install-docs maintainer-install html team
