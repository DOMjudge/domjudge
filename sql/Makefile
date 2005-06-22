ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

build:

install:
	mysql -p -u root < mysql_privileges.sql
	mysql -p -u root < mysql_db_structure.sql

clean:
