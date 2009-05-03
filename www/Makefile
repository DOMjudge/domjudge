ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

configure.php: configure.php.in ../paths.mk
	cat $< | \
	sed -e "s|@configure_input@|Generated from '$<', do not edit by hand.|g" \
	    -e "s,@domserver_etcdir@,$(domserver_etcdir),g" > $@

domserver: configure.php

install-domserver:
	for d in affiliations countries ; do \
		mkdir -p               $(domserver_wwwdir)/images/$$d ; \
		cp -a images/$$d/*.png $(domserver_wwwdir)/images/$$d ; \
	done
	for d in public team jury plugin ; do \
		mkdir -p        $(domserver_wwwdir)/$$d ; \
		cp -a $$d/*.php $(domserver_wwwdir)/$$d ; \
	done
	mkdir -p $(domserver_wwwdir)/images/teams
	cp -a images/teams/*.jpg $(domserver_wwwdir)/images/teams
	cp -a plugin/*.xsd $(domserver_wwwdir)/plugin
	cp -a jury/*.css   $(domserver_wwwdir)/jury
	cp -a *.js *.css *.php $(domserver_wwwdir)
	ln -sfT $(domjudge_docdir) $(domserver_wwwdir)/jury/doc

install-docs:
	mkdir -p $(domjudge_docdir)
	cp -a images/countries/README    $(domjudge_docdir)/README.country_flags
	cp -a images/countries/codes.txt $(domjudge_docdir)/country_codes.txt
