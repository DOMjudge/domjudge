ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

SUBST_FILES = import-event-feed

config: $(SUBST_FILES)

build:

domserver: $(SUBST_FILES)

$(SUBST_FILES): %: %.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

install-domserver:
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_bindir) \
		$(SUBST_FILES)

distclean-l:
	-rm -f $(SUBST_FILES)
