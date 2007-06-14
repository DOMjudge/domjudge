ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = submit submitdaemon

LIBS = lib.error.o
LIBOBJECTS = $(addprefix $(TOPDIR)/lib/,$(LIBS))
LIBHEADERS = $(LIBOBJECTS:.o=.h)
LIBOBJECTSCYGWIN = $(LIBOBJECTS:.o=.cygwin.o)

ifneq ($(shell which curl-config),)
LIBCURL     = $(shell curl-config --version | cut -d ' ' -f 2)
CURLLIBS    = $(shell curl-config --libs)
CURLPREFIX  = $(shell curl-config --prefix)
CURLINCLUDE = $(CURLPREFIX)/include
CURLSTATIC  = $(CURLPREFIX)/lib/libcurl.a
CURLDEF     = -DLIBCURL=$(LIBCURL)
CURLFLAGS   = $(CURLDEF) -I$(CURLINCLUDE) $(CURLLIBS)
endif

build: $(TARGETS)

$(TARGETS): %: %.cc submitcommon.o $(TOPDIR)/etc/config.h $(LIBOBJECTS) $(LIBHEADERS)
	$(CXX) $(CXXFLAGS) $(patsubst %.h,,$^) -o $@ $(CURLFLAGS)

submit.exe: %.exe: %.cc submitcommon.cygwin.o $(TOPDIR)/etc/config.h $(LIBOBJECTSCYGWIN) $(LIBHEADERS)
	$(CXX) $(CXXFLAGS) $(patsubst %.h,,$^) -o $@ $(CURLFLAGS)

$(LIBOBJECTS) $(LIBOBJECTSCYGWIN):
	$(MAKE) -C $(TOPDIR)/lib $(notdir $@)

submitcommon.o: %.o: %.cc %.h $(LIBHEADERS)
	$(CXX) -c $(CXXFLAGS) $< -o $@

submitcommon.cygwin.o: %.cygwin.o: %.cc %.h $(LIBHEADERS)
	$(CXX) -c $(CXXFLAGS) $< -o $@

submit submit.exe: $(CURLSTATIC)

clean:
	-rm -f $(TARGETS) submit.exe submitcommon.o submitcommon.cygwin.o

.PHONY:
