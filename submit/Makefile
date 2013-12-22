ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

ifndef CYGWIN
ifeq ($(SUBMIT_ENABLE_CMD),1)
SUBMIT_PROGS += submitdaemon.main
domserver: submitdaemon submitdaemon.main
endif
endif

# Check if we have to build the submit client and which libraries
# need to be linked:
ifneq ($(SUBMIT_DEFAULT),0)
SUBMITCLIENT = submit$(EXEEXT)
SUBMIT_PROGS += $(SUBMITCLIENT)
# Statically link against libmagic and libJSONcpp to prevent
# dependency on team workstations (this might be GCC specific):
ifneq ($(LIBMAGIC),)
$(SUBMITCLIENT): LDFLAGS += -Wl,-Bstatic $(LIBMAGIC) -Wl,-Bdynamic
endif
ifneq ($(LIBJSONCPP),)
$(SUBMITCLIENT): LDFLAGS += -Wl,-Bstatic $(LIBJSONCPP) -Wl,-Bdynamic
endif
ifneq ($(SUBMIT_ENABLE_WEB),0)
$(SUBMITCLIENT): CXXFLAGS += $(CURL_CFLAGS)
# Try to link statically against libcURL to prevent dependency
# on team workstations:
ifneq ($(CURL_STATIC),)
$(SUBMITCLIENT): LDFLAGS += $(CURL_STATIC)
else
$(SUBMITCLIENT): LDFLAGS += $(CURL_LIBS)
endif
endif
$(SUBMITCLIENT): LDFLAGS := $(filter-out -pie,$(LDFLAGS))
endif

SUBMITHEADERS = submitcommon.hxx $(TOPDIR)/etc/submit-config.h

SUBMITCOMMONOBJ = submitcommon$(OBJEXT)

TARGETS = $(SUBMIT_PROGS)

SUBST_CONFIGS = submitdaemon submit_db.php

config: $(SUBST_CONFIGS)

build: $(TARGETS)

domserver: submit_db.php

submitclient: $(SUBMITCLIENT)

$(SUBST_CONFIGS): %: %.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

# Explicitly link with C++ compiler, as without any C++ extensions
# this are recognized as plain C objects.
$(SUBMIT_PROGS): %$(EXEEXT): %$(OBJEXT) $(SUBMITCOMMONOBJ) $(LIBOBJECTS)
	$(CXX) $^ -o $@ $(LDFLAGS)

$(SUBMIT_PROGS:%=%$(OBJEXT)): %$(OBJEXT): %.cc $(SUBMITHEADERS) $(LIBHEADERS)

$(SUBMITCOMMONOBJ): %$(OBJEXT): %.cc %.hxx $(LIBHEADERS)

install-domserver:
ifeq ($(SUBMIT_ENABLE_CMD),1)
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_bindir) submitdaemon
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_libsubmitdir) submitdaemon.main
endif
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_libsubmitdir) \
		submit_copy.sh submit_db.php

clean-l:
	-rm -f $(TARGETS) $(TARGETS:%=%$(OBJEXT)) $(SUBMITCOMMONOBJ)

distclean-l:
	-rm -f submit_db.php submitdaemon

.PHONY: submitclient
