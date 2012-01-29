ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

config: dj-setup-database

dj-setup-database: %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)
	chmod a+x $@

install-domserver:
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_bindir) dj-setup-database
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_sqldir) \
		mysql_db_structure.sql mysql_db_examples.sql
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_sqldir)/upgrade \
	 	upgrade/upgrade_*.sql upgrade/README

distclean-l:
	-rm -f dj-setup-database
