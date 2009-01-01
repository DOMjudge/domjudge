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
	cp -a *.php $(domserver_libdir)
	cp -a www/*.php $(domserver_libwwwdir)

install-judgehost:
	cp -a *.php *.sh $(judgehost_libdir)
