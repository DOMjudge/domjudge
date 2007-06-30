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

default: build

build: $(OBJECTS)

$(OBJECTS): %$(OBJSUFFIX): %.c %.h
	$(CC) -c $(CFLAGS) $< -o $@

clean:
	rm -f $(OBJECTS)
