ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = submit submitdaemon

build: $(TARGETS)

install: build

$(TARGETS): %: %.cc submitcommon.h $(TOPDIR)/etc/config.h $(TOPDIR)/lib/*.h
	$(CXX) $(CXXFLAGS) $< -o $@

clean:
	-rm -f $(TARGETS)
