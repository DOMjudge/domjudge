ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

OBJECTS = $(addsuffix $(OBJEXT),lib.error lib.misc lib.config)

build: $(OBJECTS)

$(OBJECTS): %$(OBJEXT): %.c %.h

clean-l:
	rm -f $(OBJECTS)

install-domserver:
	$(INSTALL_DATA)   -t $(DESTDIR)$(domserver_libdir)    *.php
	$(INSTALL_DATA)   -t $(DESTDIR)$(domserver_libwwwdir) www/*.php 
	$(INSTALL_SCRIPT) -t $(DESTDIR)$(domserver_libdir)    alert

install-judgehost:
	$(INSTALL_DATA)   -t $(DESTDIR)$(judgehost_libdir)    *.php *.sh 
	$(INSTALL_SCRIPT) -t $(DESTDIR)$(judgehost_libdir)    alert
