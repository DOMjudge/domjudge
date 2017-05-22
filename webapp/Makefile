ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

install-domserver:
		$(INSTALL_DATA) -m 0755 -d $(DESTDIR)$(domserver_webappdir);
		for d in app bin src web ; do \
			# KLUDGE: how to install a tree of files with correct permissions? \
			cp -R           -t $(DESTDIR)$(domserver_webappdir) $$d ; \
			chmod -R a+rX      $(DESTDIR)$(domserver_webappdir)/$$d ; \
		done
		for d in cache logs sessions ; do \
			$(INSTALL_DATA) -o $(DOMJUDGE_USER) -g $(WEBSERVER_GROUP) -m 0775 -d $(DESTDIR)$(domserver_webappdir)/var/$$d ; \
		done
		$(INSTALL_DATA) -o $(DOMJUDGE_USER) -g $(WEBSERVER_GROUP) -t $(DESTDIR)$(domserver_webappdir)/var              var/*.php var/*.cache


install-docs:
	$(INSTALL_DATA) -T web/images/countries/README    $(DESTDIR)$(domjudge_docdir)/README.country_flags
	$(INSTALL_DATA) -T web/images/countries/codes.txt $(DESTDIR)$(domjudge_docdir)/country_codes.txt
