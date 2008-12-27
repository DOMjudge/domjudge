ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

ifdef CYGWIN
EXECSUFFIX = .exe
OBJSUFFIX = .cygwin.o
else # no CYGWIN
OBJSUFFIX = .o
ifeq ($(SUBMIT_ENABLE_CMD),1)
TARGETS += submitdaemon
domserver: submitdaemon
endif
endif

ifneq ($(SUBMIT_DEFAULT),0)
SUBMITCLIENT = submit$(EXECSUFFIX)
TARGETS += $(SUBMITCLIENT)
endif

LIBSBASE = $(addprefix $(TOPDIR)/lib/,lib.error lib.misc lib.config)
LIBHEADERS = $(addsuffix .h,$(LIBSBASE))
LIBOBJECTS = $(addsuffix $(OBJSUFFIX),$(LIBSBASE))

SUBMITCOMMONOBJ = submitcommon$(OBJSUFFIX)

build: $(TARGETS)

$(TARGETS): %$(EXECSUFFIX): %.cc $(SUBMITCOMMONOBJ) $(TOPDIR)/etc/config.h \
                            $(LIBOBJECTS) $(LIBHEADERS)

$(LIBOBJECTS):
	$(MAKE) -C $(TOPDIR)/lib $(notdir $@)

$(SUBMITCOMMONOBJ): %$(OBJSUFFIX): %.cc %.h $(LIBHEADERS)

$(SUBMITCLIENT): $(CURL_PREFIX)/lib/libcurl.a $(LIBMAGIC)
$(SUBMITCLIENT): LDFLAGS += $(CURL_LIBS)
$(SUBMITCLIENT): CXXFLAGS += $(CURL_CFLAGS)

install-domserver:
ifeq ($(SUBMIT_ENABLE_CMD),1)
	install -m u=rwx,go=rx -t $(domserver_bindir) submitdaemon
endif
	install -m u=rwx,go=rx -t $(domserver_libdir) submit_copy.sh
	install -m u=rw,go=r   -t $(domserver_libdir) submit_db.php

clean-l:
	-rm -f $(TARGETS) $(SUBMITCOMMONOBJ)

.PHONY:
