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

# Check if we have to build the submit client and which libraries
# need to be linked:
ifneq ($(SUBMIT_DEFAULT),0)
SUBMITCLIENT = submit$(EXEEXT)
SUBMIT_PROGS += $(SUBMITCLIENT)
ifneq ($(LIBMAGIC),)
$(SUBMITCLIENT): $(LIBMAGIC)
endif
ifneq ($(SUBMIT_ENABLE_WEB),0)
$(SUBMITCLIENT): CXXFLAGS += $(CURL_CFLAGS)
# Try to link statically against libcURL to prevent dependency
# on team workstations:
ifneq ($(CURL_STATIC),)
$(SUBMITCLIENT): $(CURL_STATIC)
else
$(SUBMITCLIENT): LDFLAGS += $(CURL_LIBS)
endif
endif
endif

SUBMITCOMMONOBJ = submitcommon$(OBJEXT)

TARGETS = $(SUBMIT_PROGS)

config: submit_db.php

build: $(TARGETS)

domserver: submit_db.php

submitclient: $(SUBMITCLIENT)

submit_db.php: submit_db.php.in $(TOPDIR)/paths.mk
	$(substconfigvars)
	chmod a+x $@

$(SUBMIT_PROGS): %$(EXEEXT): %.cc $(SUBMITCOMMONOBJ) $(LIBFILES) \
                             $(TOPDIR)/etc/config.h $(TOPDIR)/etc/submit-config.h

$(SUBMITCOMMONOBJ): %$(OBJEXT): %.cc %.h $(LIBFILES)

install-domserver:
ifeq ($(SUBMIT_ENABLE_CMD),1)
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_bindir) submitdaemon
endif
	$(INSTALL_SCRIPT) -t $(DESTDIR)$(domserver_libsubmitdir) \
		submit_copy.sh submit_db.php

clean-l:
	-rm -f $(TARGETS) $(SUBMITCOMMONOBJ)

distclean-l:
	-rm -f submit_db.php

.PHONY: submitclient
