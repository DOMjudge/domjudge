ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

OBJECTS = $(addsuffix $(OBJEXT),lib.error lib.misc)

build: $(OBJECTS)

$(OBJECTS): %$(OBJEXT): %.c %.h

SQL=../sql/mysql_db_structure.sql
relations.php: %: %.in $(SQL)
	TMPKEYS=`mktemp --tmpdir domjudge-keys-sql.XXXXXX` ; \
	TMPRELS=`mktemp --tmpdir domjudge-relations-sql.XXXXXX` ; \
	grep -E '(^CREATE TABLE| PRIMARY KEY)' $(SQL) \
	| sed -e '/($$/N;s/CREATE TABLE */\t/;s/)$$/),/' \
	      -e 's/(\n *PRIMARY KEY */=> array/;s/`/'"'/g" > $$TMPKEYS ; \
	grep -E '(^CREATE TABLE| CONSTRAINT|^\) ENGINE)' $(SQL) \
	| sed -e 's/CREATE TABLE *\(.*\) (/\t\1 => array(/' \
	      -e 's/^) *ENGINE.*$$/\t),\n/;s/^ *CONSTRAINT .* KEY (/\t\t/' \
	      -e 's/) REFERENCES \(`[^`]*\)` (`\([^`]*\)`)/ => \1.\2/' \
	      -e 's/ *ON DELETE */\&/;s/SET NULL/SETNULL/' \
	      -e 's/^\(\t\t.*\),$$/\1/;s/^\t\t[^&]*$$/&\&RESTRICT/' \
	      -e 's/^\t\t.*/&`,/;s/`/'"'/g" > $$TMPRELS ; \
	cat $< | sed \
		-e "s|@configure_input[@]|Generated from '$<' on `date`.|g" \
		-e "/@KEYS[@]/r $$TMPKEYS" \
		-e "/@RELATIONS[@]/r $$TMPRELS" \
		> $@ ; \
	rm -f $$TMPKEYS $$TMPRELS

dist-l: relations.php

clean-l:
	rm -f $(OBJECTS)

maintainer-clean-l:
	rm -f relations.php

install-domserver: relations.php
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_libdir)    *.php
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_libwwwdir) www/*.php www/*.phtml
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_libdir)    alert
# KLUDGE: how to install a tree of files with correct permissions?
	cp -R           -t $(DESTDIR)$(domserver_libvendordir) vendor/*
	chmod -R a+rX      $(DESTDIR)$(domserver_libvendordir)

install-judgehost: relations.php
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_libdir)    *.php *.sh
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libdir)    alert
# KLUDGE: how to install a tree of files with correct permissions?
	cp -R           -t $(DESTDIR)$(judgehost_libvendordir) vendor/*
	chmod -R a+rX      $(DESTDIR)$(judgehost_libvendordir)
