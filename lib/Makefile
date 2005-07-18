ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

OBJECTS = lib.error.o

build: $(OBJECTS)

$(OBJECTS): %.o: %.c %.h
	$(CC) -c $(CFLAGS) $< -o $@

install:

clean:
	rm -rf $(OBJECTS)
