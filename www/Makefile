ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

configure.php: configure.php.in $(TOPDIR)/paths.mk
	$(substconfigvars)

domserver: configure.php

install-domserver:
	for d in public team jury auth ; do \
		$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/$$d $$d/*.php ; \
	done
	$(INSTALL_DATA) -m 0755 -d $(DESTDIR)$(domserver_wwwdir)/api/v3 ;
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/api/v3 api/v3/*.php ;
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/jury         jury/*.phtml
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)              *.php
	rm -f $(DESTDIR)$(domserver_wwwdir)/jury/doc
	$(LN_S) $(domjudge_docdir) $(DESTDIR)$(domserver_wwwdir)/jury/doc

distclean-l:
	-rm -f configure.php
