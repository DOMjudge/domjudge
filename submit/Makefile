ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

ifndef CYGWIN
ifeq ($(SUBMIT_ENABLE_CMD),1)
TARGETS += submitdaemon
domserver: submitdaemon
endif
endif

ifneq ($(SUBMIT_DEFAULT),0)
SUBMITCLIENT = submit$(EXEEXT)
TARGETS += $(SUBMITCLIENT)
endif

SUBMITCOMMONOBJ = submitcommon$(OBJEXT)

build: $(TARGETS)

submitclient: $(SUBMITCLIENT)

$(TARGETS): %$(EXEEXT): %.cc $(SUBMITCOMMONOBJ) $(TOPDIR)/etc/config.h $(LIBFILES)

$(SUBMITCOMMONOBJ): %$(OBJEXT): %.cc %.h $(LIBHEADERS)

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

.PHONY: submitclient
