ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

LIBS = lib.error.o lib.misc.o
LIBOBJECTS = $(addprefix $(TOPDIR)/lib/,$(LIBS))
LIBHEADERS = $(LIBOBJECTS:.o=.h)

SUBMITCOMMONBASE = submitcommon

ifdef CYGWIN
EXECSUFFIX = .exe
TARGETS = submit$(EXECSUFFIX)
LIBOBJECTS = $(LIBOBJECTS:.o=.cygwin.o)
SUBMITCOMMONOBJ = $(SUBMITCOMMONBASE).cygwin.o
else
TARGETS = submit submitdaemon
SUBMITCOMMONOBJ = $(SUBMITCOMMONBASE).o
endif

ifneq ($(shell which curl-config),)
LIBCURL     = $(shell curl-config --version | cut -d ' ' -f 2)
CURLLIBS    = $(shell curl-config --libs)
CURLPREFIX  = $(shell curl-config --prefix)
CURLINCLUDE = $(CURLPREFIX)/include
CURLSTATIC  = $(CURLPREFIX)/lib/libcurl.a
CURLDEF     = -DLIBCURL=$(LIBCURL)
CURLFLAGS   = $(CURLDEF) -I$(CURLINCLUDE) $(CURLLIBS)
endif

default: build

build: $(TARGETS)

$(TARGETS): %$(EXECSUFFIX): %.cc $(SUBMITCOMMONOBJ) $(TOPDIR)/etc/config.h \
                            $(LIBOBJECTS) $(LIBHEADERS)
	$(CXX) $(CXXFLAGS) $(patsubst %.h,,$^) -o $@ $(CURLFLAGS)

$(LIBOBJECTS):
	$(MAKE) -C $(TOPDIR)/lib $(notdir $@)

$(SUBMITCOMMONOBJ): $(addprefix $(SUBMITCOMMONBASE),.cc .h) $(LIBHEADERS)
	$(CXX) -c $(CXXFLAGS) $< -o $@

submit$(EXECSUFFIX): $(CURLSTATIC)

clean:
	-rm -f $(TARGETS) $(SUBMITCOMMONOBJ)

.PHONY:
