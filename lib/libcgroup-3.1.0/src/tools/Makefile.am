@CODE_COVERAGE_RULES@

AM_CPPFLAGS = -I$(top_srcdir)/src -I$(top_srcdir)/include -I$(top_builddir)/include
LDADD = $(top_builddir)/src/libcgroup.la -lpthread

if WITH_TOOLS

if WITH_SYSTEMD
EXTRA_CFLAGS = -DWITH_SYSTEMD
endif

bin_PROGRAMS = cgexec cgclassify cgcreate cgset cgxset cgget cgxget cgdelete \
	       lssubsys lscgroup cgsnapshot

sbin_PROGRAMS = cgconfigparser

noinst_LTLIBRARIES = libcgset.la

cgexec_SOURCES = cgexec.c tools-common.c tools-common.h
cgexec_LIBS = $(CODE_COVERAGE_LIBS)
cgexec_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS)

cgclassify_SOURCES = cgclassify.c tools-common.c tools-common.h
cgclassify_LIBS = $(CODE_COVERAGE_LIBS)
cgclassify_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS)

cgcreate_SOURCES = cgcreate.c tools-common.c tools-common.h
cgcreate_LIBS = $(CODE_COVERAGE_LIBS)
cgcreate_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS)

libcgset_la_SOURCES = cgset.c tools-common.c tools-common.h
libcgset_la_LIBADD = $(CODE_COVERAGE_LIBS)
libcgset_la_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS) -DSTATIC= -DUNIT_TEST
libcgset_la_LDFLAGS = -Wl,--no-undefined $(LDADD)

cgset_SOURCES = cgset.c tools-common.c tools-common.h
cgset_LIBS = $(CODE_COVERAGE_LIBS)
cgset_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS) -DSTATIC=static

cgxset_SOURCES = cgxset.c tools-common.c tools-common.h
cgxset_LIBS = $(CODE_COVERAGE_LIBS)
cgxset_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS) -DSTATIC=static

cgget_SOURCES = cgget.c tools-common.c tools-common.h
cgget_LIBS = $(CODE_COVERAGE_LIBS)
cgget_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS)

cgxget_SOURCES = cgxget.c tools-common.c tools-common.h
cgxget_LIBS = $(CODE_COVERAGE_LIBS)
cgxget_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS)

cgconfigparser_SOURCES = cgconfig.c tools-common.c tools-common.h
cgconfigparser_LIBS = $(CODE_COVERAGE_LIBS)
cgconfigparser_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS)

cgdelete_SOURCES = cgdelete.c tools-common.c tools-common.h
cgdelete_LIBS = $(CODE_COVERAGE_LIBS)
cgdelete_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS)

lssubsys_SOURCES = lssubsys.c
lssubsys_LIBS = $(CODE_COVERAGE_LIBS)
lssubsys_CFLAGS = $(CODE_COVERAGE_CFLAGS)

lscgroup_SOURCES = tools-common.c lscgroup.c
lscgroup_LIBS = $(CODE_COVERAGE_LIBS)
lscgroup_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS)

cgsnapshot_SOURCES = cgsnapshot.c
cgsnapshot_LIBS = $(CODE_COVERAGE_LIBS)
cgsnapshot_CFLAGS = $(CODE_COVERAGE_CFLAGS) $(EXTRA_CFLAGS)

install-exec-hook:
	chmod u+s $(DESTDIR)$(bindir)/cgexec

endif
