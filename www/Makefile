ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

install-domserver:
	cp -a *.js *.css *.php images $(domserver_wwwdir)
	cp -a public team jury plugin $(domserver_wwwdir)
	install -t $(domserver_wwwdir) configure.php
	ln -s $(domjudge_docdir) $(domserver_wwwdir)/jury/doc
