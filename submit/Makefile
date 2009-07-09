ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

ifndef CYGWIN
ifeq ($(SUBMIT_ENABLE_CMD),1)
SUBMIT_PROGS += submitdaemon
domserver: submitdaemon
endif
endif

ifneq ($(SUBMIT_DEFAULT),0)
SUBMITCLIENT = submit$(EXEEXT)
SUBMIT_PROGS += $(SUBMITCLIENT)
ifneq ($(LIBMAGIC),)
$(SUBMITCLIENT): $(LIBMAGIC)
endif
ifneq ($(SUBMIT_ENABLE_WEB),0)
$(SUBMITCLIENT): $(CURL_PREFIX)/lib/libcurl.a
$(SUBMITCLIENT): LDFLAGS += $(CURL_LIBS)
$(SUBMITCLIENT): CXXFLAGS += $(CURL_CFLAGS)
endif
endif

SUBMITCOMMONOBJ = submitcommon$(OBJEXT)

TARGETS = $(SUBMIT_PROGS)

config: submit_db.php

build: $(TARGETS)

domserver: submit_db.php

submitclient: $(SUBMITCLIENT)

submit_db.php: submit_db.php.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

$(SUBMIT_PROGS): %$(EXEEXT): %.cc $(SUBMITCOMMONOBJ) $(LIBFILES) \
                             $(TOPDIR)/etc/config.h $(TOPDIR)/etc/submit-config.h

$(SUBMITCOMMONOBJ): %$(OBJEXT): %.cc %.h $(LIBHEADERS)

install-domserver:
ifeq ($(SUBMIT_ENABLE_CMD),1)
	$(INSTALL_PROG) -t $(domserver_bindir) submitdaemon
endif
	$(INSTALL_SCRIPT) -t $(domserver_libsubmitdir) submit_copy.sh submit_db.php

clean-l:
	-rm -f $(TARGETS) $(SUBMITCOMMONOBJ)

distclean-l:
	-rm -f submit_db.php

.PHONY: submitclient
