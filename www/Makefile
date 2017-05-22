ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

configure.php: configure.php.in $(TOPDIR)/paths.mk
	$(substconfigvars)

domserver: configure.php

install-domserver:
	for d in . countries ; do \
		$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/images/$$d images/$$d/*.png ; \
	done
	for d in public team jury auth ; do \
		$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/$$d $$d/*.php ; \
	done
	$(INSTALL_DATA) -d $(DESTDIR)$(domserver_wwwdir)/api/v3
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/api/v3 api/v3/*.php
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/images/teams images/teams/*.jpg
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/jury         jury/*.css
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/jury         jury/*.phtml
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/js           js/*.js js/*.gif js/*.png
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/js/flot      js/flot/*.js
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/js/ace       js/ace/*.js
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/css/octicons css/octicons/octicons.css
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/css/octicons css/octicons/octicons.eot
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/css/octicons css/octicons/octicons.woff
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/css/octicons css/octicons/octicons.ttf
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/css/octicons css/octicons/octicons.svg
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)              *.php *.css
	rm -f $(DESTDIR)$(domserver_wwwdir)/jury/doc
	$(LN_S) $(domjudge_docdir) $(DESTDIR)$(domserver_wwwdir)/jury/doc

install-docs:
	$(INSTALL_DATA) -T images/countries/README    $(DESTDIR)$(domjudge_docdir)/README.country_flags
	$(INSTALL_DATA) -T images/countries/codes.txt $(DESTDIR)$(domjudge_docdir)/country_codes.txt

distclean-l:
	-rm -f configure.php
