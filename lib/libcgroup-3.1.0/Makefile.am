AUTOMAKE_OPTIONS = foreign
ACLOCAL_AMFLAGS= -I m4
DIST_SUBDIRS = dist doc include scripts src tests samples
SUBDIRS = $(DIST_SUBDIRS)

EXTRA_DIST = README_daemon libcgroup.doxyfile README_systemd CONTRIBUTING.md

AM_DISTCHECK_CONFIGURE_FLAGS = --sysconfdir=/etc --localstatedir=/var \
			       --enable-opaque-hierarchy="name=systemd" \
			       --enable-python

pkgconfigdir = $(libdir)/pkgconfig
pkgconfig_DATA = libcgroup.pc
