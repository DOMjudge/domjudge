ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = submit submitdaemon

LIBS = lib.error.o
LIBOBJECTS = $(addprefix $(TOPDIR)/lib/,$(LIBS))
LIBHEADERS = $(LIBOBJECTS:.o=.h)

build: $(TARGETS)

install: build

$(TARGETS): %: %.cc submitcommon.o $(TOPDIR)/etc/config.h $(LIBOBJECTS) $(LIBHEADERS)
	$(CXX) $(CXXFLAGS) $< submitcommon.o $(LIBOBJECTS) -o $@

$(LIBOBJECTS):
	$(MAKE) -C $(TOPDIR)/lib $(notdir $@)

submitcommon.o: %.o: %.cc %.h $(LIBHEADERS)
	$(CXX) -c $(CXXFLAGS) $< -o $@

clean:
	-rm -f $(TARGETS) submitcommon.o
