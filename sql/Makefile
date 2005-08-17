ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

build:

install:
# Install all sql files at once, so we have to ask the MySQL root
# password only once:
	cat mysql_privileges.sql   \
	    mysql_db_structure.sql \
	    mysql_db_examples.sql | mysql -p -u root

distclean:
	mysql -p -u root < mysql_clean.sql
