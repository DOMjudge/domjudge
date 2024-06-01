AM_CPPFLAGS = -I $(top_srcdir)/include -I$(top_builddir)/include

if WITH_PAM

pamlib_LTLIBRARIES = pam_cgroup.la
pam_cgroup_la_SOURCES = pam_cgroup.c
pam_cgroup_la_LDFLAGS = -module -avoid-version
pam_cgroup_la_LIBADD = $(top_builddir)/src/libcgroup.la -lpam

endif
