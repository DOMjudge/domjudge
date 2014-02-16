ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

config: dj-setup-database zipfiles

zipfiles:
	rm -f mysql_db_files_defaultdata.sql mysql_db_files_examples.sql
	find files -mindepth 2 -maxdepth 2 -type d -exec  zip -qjr {}.zip {} \;
	for i in `find files/defaultdata -mindepth 1 -maxdepth 1 -type d`; do \
		echo "UPDATE executable SET zipfile = LOAD_FILE('$(CURDIR)/$$i.zip'), md5sum = '`md5sum $$i.zip | cut -f1 -d\\ `' WHERE execid = '`basename $$i`';" >> mysql_db_files_defaultdata.sql; \
	done
	for i in `find files/examples -mindepth 1 -maxdepth 1 -type d`; do \
		echo "UPDATE executable SET zipfile = LOAD_FILE('$(CURDIR)/$$i.zip'), md5sum = '`md5sum $$i.zip | cut -f1 -d\\ `' WHERE execid = '`basename $$i`';" >> mysql_db_files_examples.sql; \
	done

dj-setup-database: %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)
	chmod a+x $@

install-domserver:
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_bindir) dj-setup-database
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_sqldir) \
		mysql_db_structure.sql mysql_db_defaultdata.sql mysql_db_examples.sql \
		mysql_db_files_examples.sql mysql_db_files_defaultdata.sql
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_sqldir)/upgrade \
	 	upgrade/upgrade_*.sql upgrade/README

distclean-l:
	-rm -f dj-setup-database
	-rm -f mysql_db_files_examples.sql mysql_db_files_defaultdata.sql

.PHONY: zipfiles
