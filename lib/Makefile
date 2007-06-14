ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

OBJECTS = lib.error.o
OBJECTSCYGWIN = $(OBJECTS:.o=.cygwin.o)

build: $(OBJECTS)

$(OBJECTS): %.o: %.c %.h
	$(CC) -c $(CFLAGS) $< -o $@

$(OBJECTSCYGWIN): %.cygwin.o: %.c %.h
	$(CC) -c $(CFLAGS) $< -o $@

clean:
	rm -rf $(OBJECTS) $(OBJECTSCYGWIN)
