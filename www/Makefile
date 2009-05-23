ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

configure.php: configure.php.in ../paths.mk
	$(substconfigvars)

config: configure.php

domserver: configure.php

install-domserver:
# FIXME: DOMjudgelogo.png symlink wordt niet goed gekopieerd.
	for d in . affiliations countries ; do \
		$(INSTALL_DATA) -t $(domserver_wwwdir)/images/$$d images/$$d/*.png ; \
	done
	for d in public team jury plugin ; do \
		$(INSTALL_DATA) -t $(domserver_wwwdir)/$$d $$d/*.php ; \
	done
	$(INSTALL_DATA) -t $(domserver_wwwdir)/images/teams images/teams/*.jpg
	$(INSTALL_DATA) -t $(domserver_wwwdir)/plugin       plugin/*.xsd
	$(INSTALL_DATA) -t $(domserver_wwwdir)/jury         jury/*.css
	$(INSTALL_DATA) -t $(domserver_wwwdir)              *.js *.css *.php
	ln -sfT $(domjudge_docdir) $(domserver_wwwdir)/jury/doc

install-docs:
	$(INSTALL_DATA) -T images/countries/README    $(domjudge_docdir)/README.country_flags
	$(INSTALL_DATA) -T images/countries/codes.txt $(domjudge_docdir)/country_codes.txt

distclean-l:
	-rm -f configure.php
