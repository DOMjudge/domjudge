ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

EXECDIRS=$(shell find files -mindepth 2 -maxdepth 2 -type d)
ZIPFILES=$(EXECDIRS:=.zip)
CONFSTUB=files/config.h

domserver: dj_setup_database

build-scripts: $(CONFSTUB)
	@for d in $(EXECDIRS) ; do \
		[ -x $$d/build ] && ( cd $$d && ./build ) ; \
	done

# Use secondary expansion to use stem $* in dependencies:
.SECONDEXPANSION:
$(ZIPFILES): %.zip: $$(wildcard $$*/*)
	@rm -f $@
	zip -qjr $@ $*

$(TOPDIR)/doc/examples/.pdf-generated:
	$(MAKE) -C $(TOPDIR)/doc/examples .pdf-generated

# Extract DOMjudge version from README here as a hack, because the
# system isn't configured yet.
$(CONFSTUB): %: %.in $(TOPDIR)/README.md
	VERSION=`sed -n '1s/^.*version //p' $(TOPDIR)/README.md | tr -d '\n'` ; \
	sed "s/@VERSION@/$$VERSION/" $< > $@

dj_setup_database: %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

install-domserver: $(ZIPFILES) $(TOPDIR)/doc/examples/.pdf-generated
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_bindir) dj_setup_database

	$(INSTALL_DIR) $(addprefix $(DESTDIR)$(domserver_sqldir)/files/,defaultdata examples)
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_sqldir)/files/defaultdata \
		files/defaultdata/*.zip
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_sqldir)/files/examples \
		files/examples/*.zip

# Make $(CONFSTUB) a dependency, and the other stuff sub-rules to make
# sure that config.h already exists when creating the zipfiles.
dist-l: $(CONFSTUB)
	$(MAKE) $(ZIPFILES)

distclean-l:
	-rm -f dj_setup_database
	-rm -f files/defaultdata/compare/run \
	       files/defaultdata/java_javac_detect/DetectMain.class \
	       files/defaultdata/kt/DetectMain.class \
	       files/examples/boolfind_cmp/check_boolfind \
	       files/examples/boolfind_run/runjury

maintainer-clean-l:
	-rm -f $(ZIPFILES) $(CONFSTUB)
