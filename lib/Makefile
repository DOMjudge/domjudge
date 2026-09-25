ifndef TOPDIR
TOPDIR=..
endif

include $(TOPDIR)/Makefile.global

OBJECTS = $(addsuffix $(OBJEXT),lib.error lib.misc)

build: $(OBJECTS)

# paths.mk holds the compiler and flags chosen by configure, so a
# reconfigure with a different compiler must rebuild these.
lib.error$(OBJEXT): lib.error.cc lib.error.hpp $(TOPDIR)/paths.mk
lib.misc$(OBJEXT): lib.misc.cc lib.misc.h $(TOPDIR)/paths.mk

clean-l:
	rm -f $(OBJECTS)

install-domserver:
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_libdir)    *.php
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_libdir)    alert

install-judgehost:
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_libdir)    *.php *.sh
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libdir)    alert
