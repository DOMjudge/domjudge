ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = check_float compare

LIBSBASE = $(addprefix $(TOPDIR)/lib/,lib.error lib.misc)
LIBHEADERS = $(addsuffix .h,$(LIBSBASE))
LIBOBJECTS = $(addsuffix .o,$(LIBSBASE))

default: build

build: $(TARGETS)

$(LIBOBJECTS):
	$(MAKE) -C $(TOPDIR)/lib $(notdir $@)

compare: %: %.c $(LIBOBJECTS) $(LIBHEADERS)
	$(CC) $(CFLAGS) $(patsubst %.h,,$^) -o $@
	$(STRIP) $@

check_float: %: %.c
	$(CC) -std=c99 $(CFLAGS) $^ -o $@
	$(STRIP) $@

clean:
	-rm -f $(TARGETS)
