ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

# User to access the MySQL database (needs root privileges):
MYSQLUSER=root

define cleandb
mysql -p -u $(MYSQLUSER) < mysql_clean.sql
endef

build:

install:
# Install all sql files at once, so we have to ask the MySQL root
# password only once:
	cat mysql_privileges.sql   \
	    mysql_db_structure.sql \
	    mysql_db_examples.sql | mysql -p -u $(MYSQLUSER)

distclean:
	$(cleandb)

upgrade:
# Upgrades the DB structure and permissions to current version.
# Preserves data by first dumping that and importing after upgrade.
	@echo "Upgrading database..."
	mysqldump -p -u $(MYSQLUSER) -cntQ domjudge > mysql_upgrade_dump.sql
	$(cleandb)
	cat mysql_privileges.sql   \
	    mysql_db_structure.sql \
	    mysql_upgrade_dump.sql | mysql -p -u $(MYSQLUSER)
	rm -f mysql_upgrade_dump.sql

.PHONY: upgrade
