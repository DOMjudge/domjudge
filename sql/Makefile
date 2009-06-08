ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

config: dj-setup-database

dj-setup-database: %: %.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

install-domserver:
	$(INSTALL_PROG) -t $(domserver_bindir) dj-setup-database
	$(INSTALL_DATA) -t $(domserver_sqldir) \
		mysql_db_structure.sql mysql_db_examples.sql \
		mysql_privileges.sql mysql_clean.sql

distclean-l:
	-rm -f dj-setup-database
