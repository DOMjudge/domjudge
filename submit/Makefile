ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

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
$(SUBMITCLIENT): LDFLAGS += $(CURL_STATIC) -lpthread
else
$(SUBMITCLIENT): LDFLAGS += $(CURL_LIBS)
endif
endif
$(SUBMITCLIENT): LDFLAGS := $(filter-out -pie,$(LDFLAGS))
endif

SUBMITHEADERS = $(TOPDIR)/etc/submit-config.h

TARGETS = $(SUBMIT_PROGS)

build: $(TARGETS)

submitclient: $(SUBMITCLIENT)

$(SUBST_CONFIGS): %: %.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

# Explicitly link with C++ compiler, as without any C++ extensions
# this are recognized as plain C objects.
$(SUBMIT_PROGS): %$(EXEEXT): %$(OBJEXT) $(LIBOBJECTS)
	$(CXX) $^ -o $@ $(LDFLAGS)

$(SUBMIT_PROGS:%=%$(OBJEXT)): %$(OBJEXT): %.cc $(SUBMITHEADERS) $(LIBHEADERS)

clean-l:
	-rm -f $(TARGETS) $(TARGETS:%=%$(OBJEXT))

.PHONY: submitclient
