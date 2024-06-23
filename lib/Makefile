ifndef TOPDIR
TOPDIR=..
endif

REC_TARGETS = domserver

include $(TOPDIR)/Makefile.global

OBJECTS = $(addsuffix $(OBJEXT),lib.error lib.misc)

build: $(OBJECTS)

$(OBJECTS): %$(OBJEXT): %.c %.h

clean-l:
	rm -f $(OBJECTS)

install-domserver:
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_libdir)    *.php
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_libdir)    alert

install-judgehost:
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_libdir)    *.php *.sh
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libdir)    alert

domserver:         SUBDIRS=vendor
