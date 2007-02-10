ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = submit submitdaemon

LIBS = lib.error.o
LIBOBJECTS = $(addprefix $(TOPDIR)/lib/,$(LIBS))
LIBHEADERS = $(LIBOBJECTS:.o=.h)

LIBCURL    = $(shell curl-config --version | cut -d ' ' -f 2)
CURLLIBS   = $(shell curl-config --libs)
CURLSTATIC = $(shell curl-config --prefix)/lib/libcurl.a
ifdef LIBCURL
CURLDEF = -DLIBCURL=$(LIBCURL)
endif

build: $(TARGETS)

install: build

$(TARGETS): %: %.cc submitcommon.o $(TOPDIR)/etc/config.h $(LIBOBJECTS) $(LIBHEADERS)
	$(CXX) $(CXXFLAGS) $(patsubst %.h,,$^) -o $@ $(CURLDEF) $(CURLLIBS)

$(LIBOBJECTS):
	$(MAKE) -C $(TOPDIR)/lib $(notdir $@)

submitcommon.o: %.o: %.cc %.h $(LIBHEADERS)
	$(CXX) -c $(CXXFLAGS) $< -o $@

submit: $(CURLSTATIC)

clean:
	-rm -f $(TARGETS) submitcommon.o

.PHONY: checklibcurl
