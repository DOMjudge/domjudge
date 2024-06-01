@CODE_COVERAGE_RULES@

BUILT_SOURCES = parse.c parse.h

DIST_SUBDIRS = . daemon pam tools python
SUBDIRS = . daemon pam tools
if ENABLE_PYTHON
SUBDIRS += python
endif
EXTRA_DIST = libcgroup_systemd_idle_thread

# generate parse.h  from parse.y
AM_YFLAGS = -d

CLEANFILES = lex.c parse.c parse.h

AM_CPPFLAGS = -I$(top_srcdir)/include -I$(top_builddir)/include

VERSION_NUMBER = $(LIBRARY_VERSION_MAJOR):$(LIBRARY_VERSION_MINOR):$(LIBRARY_VERSION_RELEASE)
TESTING_MAP_FILE = $(top_srcdir)/tests/gunit/libcgroup_unittest.map

if WITH_SYSTEMD
libcgroup_systemd_idle_thread_SOURCES = libcgroup_systemd_idle_thread.c
bin_PROGRAMS = libcgroup_systemd_idle_thread
endif

lib_LTLIBRARIES = libcgroup.la
libcgroup_la_SOURCES = parse.h parse.y lex.l api.c config.c libcgroup-internal.h libcgroup.map \
		       wrapper.c log.c abstraction-common.c abstraction-common.h \
		       abstraction-map.c abstraction-map.h abstraction-cpu.c abstraction-cpuset.c \
		       tools/cgxget.c tools/cgxset.c
if WITH_SYSTEMD
libcgroup_la_SOURCES += systemd.c
endif

libcgroup_la_LIBADD = -lpthread $(CODE_COVERAGE_LIBS)
libcgroup_la_CFLAGS = $(CODE_COVERAGE_CFLAGS) -DSTATIC=static -DLIBCG_LIB -fPIC

libcgroup_la_LDFLAGS = -Wl,--version-script,$(srcdir)/libcgroup.map \
		       -version-number $(VERSION_NUMBER)
if WITH_SYSTEMD
libcgroup_la_LDFLAGS += -lsystemd
libcgroup_la_CFLAGS += -DWITH_SYSTEMD
endif

noinst_LTLIBRARIES = libcgroupfortesting.la
libcgroupfortesting_la_SOURCES = parse.h parse.y lex.l api.c config.c libcgroup-internal.h \
				 libcgroup.map wrapper.c log.c abstraction-common.c \
				 abstraction-common.h abstraction-map.c abstraction-map.h \
				 abstraction-cpu.c abstraction-cpuset.c
if WITH_SYSTEMD
libcgroupfortesting_la_SOURCES += systemd.c
endif

libcgroupfortesting_la_LIBADD = -lpthread $(CODE_COVERAGE_LIBS)
libcgroupfortesting_la_CFLAGS = $(CODE_COVERAGE_CFLAGS) -DSTATIC= -DUNIT_TEST

libcgroupfortesting_la_LDFLAGS = -Wl,--version-script,$(TESTING_MAP_FILE) \
				 -version-number $(VERSION_NUMBER)
if WITH_SYSTEMD
libcgroupfortesting_la_LDFLAGS += -lsystemd
endif
