ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

EXECDIRS=$(shell find files -mindepth 2 -maxdepth 2 -type d)
ZIPFILES=$(EXECDIRS:=.zip)

domserver: dj_setup_database

build-scripts:
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

dj_setup_database: %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

install-domserver: $(ZIPFILES) $(TOPDIR)/doc/examples/.pdf-generated
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_bindir) dj_setup_database

	$(INSTALL_DIR) $(addprefix $(DESTDIR)$(domserver_sqldir)/files/,defaultdata)
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_sqldir)/files/defaultdata \
		files/defaultdata/*.zip

dist-l: $(ZIPFILES)

distclean-l:
	-rm -f dj_setup_database
	-rm -f files/defaultdata/compare/run \
	       files/defaultdata/java_javac_detect/DetectMain.class \
	       files/defaultdata/kt/DetectMain.class

maintainer-clean-l:
	-rm -f $(ZIPFILES)
