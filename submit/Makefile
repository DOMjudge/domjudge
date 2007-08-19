ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

ifdef CYGWIN
EXECSUFFIX = .exe
OBJSUFFIX = .cygwin.o
TARGETS = submit$(EXECSUFFIX)
else
OBJSUFFIX = .o
TARGETS = submit submitdaemon
endif

LIBSBASE = $(addprefix $(TOPDIR)/lib/,lib.error lib.misc)
LIBHEADERS = $(addsuffix .h,$(LIBSBASE))
LIBOBJECTS = $(addsuffix $(OBJSUFFIX),$(LIBSBASE))

COMMONOBJ = submitcommon$(OBJSUFFIX) getaddrinfo$(OBJSUFFIX)

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

$(TARGETS): %$(EXECSUFFIX): %.cc $(COMMONOBJ) $(TOPDIR)/etc/config.h \
                            $(LIBOBJECTS) $(LIBHEADERS)
	$(CXX) $(CXXFLAGS) $(patsubst %.h,,$^) -o $@ $(CURLFLAGS)

$(LIBOBJECTS):
	$(MAKE) -C $(TOPDIR)/lib $(notdir $@)

$(COMMONOBJ): %$(OBJSUFFIX): %.cc %.h $(LIBHEADERS)
	$(CXX) -c $(CXXFLAGS) $< -o $@

submit$(EXECSUFFIX): $(CURLSTATIC)

clean:
	-rm -f $(TARGETS) $(COMMONOBJ)

.PHONY:
