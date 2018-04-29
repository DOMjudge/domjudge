ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

configure.php: configure.php.in $(TOPDIR)/paths.mk
	$(substconfigvars)

domserver: configure.php

install-domserver:
	for d in public team jury auth feed ; do \
		$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/$$d $$d/*.php ; \
	done
	for version in v3 v4; do \
		$(INSTALL_DATA) -m 0755 -d $(DESTDIR)$(domserver_wwwdir)/api/$$version ; \
		$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/api/$$version api/$$version/*.php ; \
	done
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/jury         jury/*.phtml
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)              *.php
	rm -f $(DESTDIR)$(domserver_wwwdir)/jury/doc
	$(LN_S) $(domjudge_docdir) $(DESTDIR)$(domserver_wwwdir)/jury/doc

distclean-l:
	-rm -f configure.php
