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
	$(INSTALL_DATA) -t $(domserver_libdir) *.php
	$(INSTALL_DATA) -t $(domserver_libwwwdir) www/*.php 
	$(INSTALL_SCRIPT) -t $(domserver_libdir) alert

install-judgehost:
	$(INSTALL_DATA) -t $(judgehost_libdir) *.php *.sh 
	$(INSTALL_SCRIPT) -t $(judgehost_libdir) alert
