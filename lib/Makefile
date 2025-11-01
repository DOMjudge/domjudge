ifndef TOPDIR
TOPDIR=..
endif

include $(TOPDIR)/Makefile.global

OBJECTS = lib.error$(OBJEXT) lib.misc$(OBJEXT)

build: $(OBJECTS)

lib.error$(OBJEXT): lib.error.c lib.error.h
lib.misc$(OBJEXT): lib.misc.cc lib.misc.h

clean-l:
	rm -f $(OBJECTS)

install-domserver:
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_libdir)    *.php
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_libdir)    alert

install-judgehost:
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_libdir)    *.php *.sh
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libdir)    alert
