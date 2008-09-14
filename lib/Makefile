ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

ifdef CYGWIN
OBJSUFFIX = .cygwin.o
else
OBJSUFFIX = .o
endif

LIBSBASE = lib.error lib.misc

OBJECTS = $(addsuffix $(OBJSUFFIX),$(LIBSBASE))

build: $(OBJECTS)

$(OBJECTS): %$(OBJSUFFIX): %.c %.h
	$(CC) -c $(CFLAGS) $< -o $@

clean:
	rm -f $(OBJECTS)

install-domserver:
	cp -a *.php $(domserver_libdir)
	cp -a www/*.php $(domserver_libwwwdir)

install-judgehost:
	cp -a *.php *.sh $(judgehost_libdir)
