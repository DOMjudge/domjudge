ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS =

SUBMITCLIENT = submit$(EXEEXT)
# Statically link against libmagic and libJSONcpp to prevent
# dependency on team workstations (this might be GCC specific):
ifneq ($(LIBMAGIC),)
$(SUBMITCLIENT): LDFLAGS += $(STATIC_LINK_START) $(LIBMAGIC) $(STATIC_LINK_END)
endif
ifneq ($(LIBJSONCPP),)
$(SUBMITCLIENT): LDFLAGS += $(STATIC_LINK_START) $(LIBJSONCPP) $(STATIC_LINK_END)
endif
$(SUBMITCLIENT): CXXFLAGS += $(CURL_CFLAGS)
# Try to link statically against libcURL to prevent dependency
# on team workstations:
ifneq ($(CURL_STATIC),)
$(SUBMITCLIENT): LDFLAGS += $(CURL_STATIC) -lpthread
else
$(SUBMITCLIENT): LDFLAGS += $(CURL_LIBS)
endif
$(SUBMITCLIENT): LDFLAGS := $(filter-out -pie,$(LDFLAGS))

SUBMITHEADERS = $(TOPDIR)/etc/submit-config.h

ifeq ($(SUBMITCLIENT_ENABLED),yes)
submitclient: $(SUBMITCLIENT)
TARGETS += $(SUBMITCLIENT)
else
submitclient:
	@echo "Submit client is disabled."
	@exit 1
endif

# Explicitly link with C++ compiler, as without any C++ extensions
# these are recognized as plain C objects.
$(SUBMITCLIENT): %$(EXEEXT): %$(OBJEXT) $(LIBOBJECTS)
	$(CXX) $^ -o $@ $(LDFLAGS)

$(SUBMITCLIENT:%=%$(OBJEXT)): %$(OBJEXT): %.cc $(SUBMITHEADERS) $(LIBHEADERS)

clean-l:
	-rm -f $(TARGETS) $(TARGETS:%=%$(OBJEXT))

.PHONY: submitclient
