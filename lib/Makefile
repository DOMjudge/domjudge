ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

OBJECTS = $(addsuffix $(OBJEXT),lib.error lib.misc)

build: $(OBJECTS)

$(OBJECTS): %$(OBJEXT): %.c %.h

clean-l:
	rm -f $(OBJECTS)

install-domserver:
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_libdir)    *.php
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_libwwwdir) www/*.php
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_libdir)    alert
# KLUDGE: how to install a tree of files with correct permissions?
	cp -R           -t $(DESTDIR)$(domserver_libvendordir) vendor/*
	chmod -R a+rX      $(DESTDIR)$(domserver_libvendordir)

install-judgehost:
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_libdir)    *.php *.sh
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libdir)    alert
# KLUDGE: how to install a tree of files with correct permissions?
	cp -R           -t $(DESTDIR)$(judgehost_libvendordir) vendor/*
	chmod -R a+rX      $(DESTDIR)$(judgehost_libvendordir)
