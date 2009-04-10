ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

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

install-docs:
	mkdir -p $(domjudge_docdir)
	ln -sf $(domjudge_docdir) $(domserver_wwwdir)/jury/doc
	cp -a images/countries/README    $(domjudge_docdir)/README.country_flags
	cp -a images/countries/codes.txt $(domjudge_docdir)/country_codes.txt
