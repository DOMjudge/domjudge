ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

# DOMjudge database name:
DBNAME=domjudge
define subst_db
sed 's/DOMJUDGE_DBNAME/$(DBNAME)/'
endef

# User to access the MySQL database for install/remove (needs root privileges):
MYSQL_INST_USER=root

# User to access the MySQL database for upgrades (needs GRANT privileges):
MYSQL_UPGR_USER=root


build:

install:
# Install all sql files at once, so we have to ask the MySQL root
# password only once:
	($(subst_db) mysql_create.sql ; \
	 echo 'use $(DBNAME);' ;        \
	 cat mysql_db_structure.sql     \
	     mysql_privileges.sql       \
	     mysql_db_examples.sql ; ) | mysql -p -u $(MYSQL_INST_USER)

clean:

distclean:
# Remove the whole database and everything associated with it:
	($(subst_db) mysql_clean.sql ;    \
	 $(subst_db) mysql_remove.sql ; ) | mysql -p -u $(MYSQL_INST_USER) $(DBNAME)

upgrade:
# Upgrades the DB structure and permissions to current version.
# Preserves data by first dumping that and importing after upgrade.
	@echo "Upgrading database..."
	mysqldump -p -u $(MYSQL_UPGR_USER) -cntQ $(DBNAME) > mysql_upgrade_dump.sql
	($(subst_db) mysql_clean.sql ;      \
	 cat         mysql_db_structure.sql \
	             mysql_privileges.sql   \
	             mysql_upgrade_dump.sql | mysql -p -u $(MYSQL_UPGR_USER) $(DBNAME)
	rm -f mysql_upgrade_dump.sql

.PHONY: upgrade
