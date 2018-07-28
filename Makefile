# Main Makefile

# Define TOPDIR from shell command and _not_ $(PWD) because that gives
# pwd before changing to a 'make -C <dir>' <dir>:
export TOPDIR = $(shell pwd)

REC_TARGETS=build domserver install-domserver judgehost install-judgehost \
            docs install-docs

# Global Makefile definitions
include $(TOPDIR)/Makefile.global

default:
	@echo "No default target"
	@echo
	@echo "Try:"
	@echo " - make all         (all targets below)"
	@echo " - make build       (all except 'docs')"
	@echo " - make domserver"
	@echo " - make judgehost"
	@echo " - make submitclient"
	@echo " - make docs"
	@echo or
	@echo " - make install-domserver"
	@echo " - make install-judgehost"
	@echo " - make install-docs"
	@echo or
	@echo " - make clean"
	@echo " - make distclean"
	@exit 1

install:
	@echo "Try:"
	@echo " - make install-domserver"
	@echo " - make install-judgehost"
	@echo " - make install-docs"
	@exit 1

all: build
build: domserver judgehost

ifeq ($(SUBMITCLIENT_ENABLED),yes)
build: submitclient
endif

ifneq ($(DOC_BUILD_ENABLED),no)
all: docs
dist: distdocs
endif

# MAIN TARGETS
domserver judgehost docs submitclient: paths.mk config
submitclient:
	$(MAKE) -C submit submitclient
install-domserver: domserver domserver-create-dirs
install-judgehost: judgehost judgehost-create-dirs
install-docs: docs-create-dirs
dist: configure composer-dependencies

# Install PHP dependencies
composer-dependencies:
ifeq (, $(shell which composer))
	$(error "'composer' command not found in $(PATH), install it via your package manager or https://getcomposer.org/download/")
endif
# To install Symfony we need a parameters.yml file, but to generate
# that properly, we need a configured system with dbpasswords.secret
# generated. To circumvent this, we install a stub parameters.yml
# file, and set its modification time in the past so that it will get
# updated later during build with 'make domserver'.
	$(MAKE) -C webapp params-from-stub
	composer $(subst 1,-q,$(QUIET)) install

# Generate documentation for distribution. Remove this dependency from
# dist above for quicker building from git sources.
distdocs:
	$(MAKE) -C doc distdocs

# Generate configuration files from configure settings:
config:
	$(MAKE) -C etc config

# Special rule to build any compile/run/compare scripts. This is
# useful for testing, e.g. when submitting a Coverity scan.
build-scripts:
	$(MAKE) -C sql build-scripts

# List of SUBDIRS for recursive targets:
build:             SUBDIRS=        lib                      import tests misc-tools
domserver:         SUBDIRS=etc         sql www              import       misc-tools webapp
install-domserver: SUBDIRS=etc     lib sql www              import       misc-tools webapp
judgehost:         SUBDIRS=etc                 judge              misc-tools
install-judgehost: SUBDIRS=etc     lib         judge              misc-tools
docs:              SUBDIRS=    doc
install-docs:      SUBDIRS=    doc                                           webapp
dist:              SUBDIRS=        lib sql                        misc-tools
clean:             SUBDIRS=etc doc lib sql www judge submit tests misc-tools webapp
distclean:         SUBDIRS=etc doc lib sql www judge submit import tests misc-tools webapp
maintainer-clean:  SUBDIRS=etc doc lib sql www judge submit import tests misc-tools webapp

domserver-create-dirs:
	$(INSTALL_DIR) $(addprefix $(DESTDIR),$(domserver_dirs))

judgehost-create-dirs:
	$(INSTALL_DIR) $(addprefix $(DESTDIR),$(judgehost_dirs))

docs-create-dirs:
	$(INSTALL_DIR) $(addprefix $(DESTDIR),$(docs_dirs))

install-docs-l:
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir) README.md ChangeLog COPYING*

# As final step try set ownership and permissions of a few special
# files/directories. Print a warning and fail gracefully if this
# doesn't work because we're not root.
install-domserver-l:
	$(MAKE) check-root
# Fix permissions for special directories (don't touch tmpdir when FHS enabled):
	-$(INSTALL_USER)    -m 0700 -d $(DESTDIR)$(domserver_logdir)
	-$(INSTALL_USER)    -m 0700 -d $(DESTDIR)$(domserver_rundir)
	-$(INSTALL_WEBSITE) -m 0770 -d $(DESTDIR)$(domserver_submitdir)
ifneq "$(FHS_ENABLED)" "yes"
	-$(INSTALL_WEBSITE) -m 0770 -d $(DESTDIR)$(domserver_tmpdir)
endif
# Fix permissions and ownership for password files:
# FIXME: installing restapi.secret with website group is a quick hack
# to fix eventlog() to query the REST API, see issue #283.
	-$(INSTALL_WEBSITE) -m 0640 -t $(DESTDIR)$(domserver_etcdir) \
		etc/restapi.secret
	-$(INSTALL_WEBSITE) -m 0640 -t $(DESTDIR)$(domserver_etcdir) \
		etc/dbpasswords.secret

install-judgehost-l:
	$(MAKE) check-root
# Fix permissions for special directories (don't touch tmpdir when FHS enabled):
	-$(INSTALL_USER) -m 0700 -d $(DESTDIR)$(judgehost_logdir)
	-$(INSTALL_USER) -m 0700 -d $(DESTDIR)$(judgehost_rundir)
	-$(INSTALL_USER) -m 0711 -d $(DESTDIR)$(judgehost_judgedir)
ifneq "$(FHS_ENABLED)" "yes"
	-$(INSTALL_USER) -m 0700 -d $(DESTDIR)$(judgehost_tmpdir)
endif
# Fix permissions and ownership for password files:
	-$(INSTALL_USER) -m 0600 -t $(DESTDIR)$(judgehost_etcdir) \
		etc/restapi.secret

check-root:
	@if [ `id -u` -ne 0 -a -z "$(QUIET)" ]; then \
		echo "**************************************************************" ; \
		echo "***  You do not seem to have the required root privileges. ***" ; \
		echo "***       Perform any failed commands below manually.      ***" ; \
		echo "**************************************************************" ; \
	fi

dist-l:
	$(MAKE) clean-autoconf

# Run aclocal separately from autoreconf, which doesn't pass -I option.
aclocal.m4: configure.ac $(wildcard m4/*.m4)
	aclocal -I m4

configure: configure.ac aclocal.m4
	autoheader
	autoconf

paths.mk:
	@echo "The file 'paths.mk' is not available. Probably you"
	@echo "have not run './configure' yet, aborting..."
	@exit 1

# Configure for running in source tree, not meant for normal use:
MAINT_CXFLAGS=-g -O1 -Wall -fstack-protector -D_FORTIFY_SOURCE=2 \
              -fPIE -Wformat -Wformat-security -ansi -pedantic
MAINT_LDFLAGS=-fPIE -pie -Wl,-z,relro -Wl,-z,now
maintainer-conf: dist
	./configure $(subst 1,-q,$(QUIET)) --prefix=$(CURDIR) \
	            --with-domserver_root=$(CURDIR) \
	            --with-judgehost_root=$(CURDIR) \
	            --with-domjudge_docdir=$(CURDIR)/doc \
	            --with-domserver_logdir=$(CURDIR)/output/log \
	            --with-judgehost_logdir=$(CURDIR)/output/log \
	            --with-domserver_rundir=$(CURDIR)/output/run \
	            --with-judgehost_rundir=$(CURDIR)/output/run \
	            --with-domserver_tmpdir=$(CURDIR)/output/tmp \
	            --with-judgehost_tmpdir=$(CURDIR)/output/tmp \
	            --with-judgehost_judgedir=$(CURDIR)/output/judgings \
	            --with-domserver_submitdir=$(CURDIR)/output/submissions \
	            --with-baseurl='http://localhost/domjudge/' \
	            CFLAGS='$(MAINT_CXFLAGS)' \
	            CXXFLAGS='$(MAINT_CXFLAGS)' \
	            LDFLAGS='$(MAINT_LDFLAGS)' \
	            $(CONFIGURE_FLAGS)

# Install the system in place: don't really copy stuff, but create
# symlinks where necessary to let it work from the source tree.
# This stuff is a hack!
maintainer-install: build domserver-create-dirs judgehost-create-dirs
# Replace lib{judge,submit}dir with symlink to prevent lots of symlinks:
	-rmdir $(judgehost_libjudgedir) $(domserver_libsubmitdir)
	-rm -f $(judgehost_libjudgedir) $(domserver_libsubmitdir)
	ln -sf $(CURDIR)/judge  $(judgehost_libjudgedir)
	ln -sf $(CURDIR)/submit $(domserver_libsubmitdir)
	ln -sfn $(CURDIR)/doc $(domserver_wwwdir)/jury/doc
# Add symlinks to binaries:
	$(MKDIR_P) $(judgehost_bindir) $(domserver_bindir)
	ln -sf $(CURDIR)/judge/judgedaemon $(judgehost_bindir)
	ln -sf $(CURDIR)/judge/runguard $(judgehost_bindir)
	ln -sf $(CURDIR)/judge/runpipe  $(judgehost_bindir)
	ln -sf $(CURDIR)/sql/dj_setup_database $(domserver_bindir)
	$(MAKE) -C misc-tools maintainer-install
# Make tmpdir, submitdir writable for webserver, because
# judgehost-create-dirs sets wrong permissions:
	chmod a+rwx $(domserver_tmpdir) $(domserver_submitdir)
# Make sure we're running from a clean state:
	$(MAKE) -C webapp clear-cache
	@echo ""
	@echo "========== Maintainer Install Completed =========="
	@echo ""
	@echo "Next:"
	@echo "    - Set up database"
	@echo "        ./sql/dj_setup_database -u root [-r|-p ROOT_PASS] install"
	@echo "    - Configure apache2"
	@echo "        sudo make maintainer-postinstall-apache"
	@echo "    - Configure nginx"
	@echo "        sudo make maintainer-postinstall-nginx"
	@echo ""
	@echo "Or you can run these commands manually as root"
	@echo "    - Give the webserver access to things it needs"
	@echo "        setfacl    -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/etc/dbpasswords.secret"
	@echo "        setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/var"
	@echo "        setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/var"
	@echo "        setfacl -R -m d:m::rwx          $(CURDIR)/webapp/var"
	@echo "        setfacl -R -m   m::rwx          $(CURDIR)/webapp/var"
	@echo "        # Also make sure you keep access"
	@echo "        setfacl -R -m d:u:$(DOMJUDGE_USER):rwx  $(CURDIR)/webapp/var"
	@echo "        setfacl -R -m   u:$(DOMJUDGE_USER):rwx  $(CURDIR)/webapp/var"
	@echo "    - Configure webserver"
	@echo "        Apache 2:"
	@echo "           ln -sf $(CURDIR)/etc/apache.conf /etc/apache2/conf-enabled/domjudge.conf"
	@echo "           a2enmod rewrite"
	@echo "           systemctl restart apache2"
	@echo "        Nginx + PHP-FPM:"
	@echo "           ln -sf $(CURDIR)/etc/nginx-conf /etc/nginx/sites-enabled/"
	@echo "           ln -sf $(CURDIR)/etc/domjudge-fpm /etc/php/7.0/fpm/pool.d/domjudge.conf"
	@echo "           systemctl restart nginx"
	@echo "           systemctl restart php-fpm"

maintainer-postinstall-permissions:
	setfacl    -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/etc/dbpasswords.secret
# FIXME: this is a quick hack to fix eventlog() to query the REST API,
# see issue #283.
	setfacl    -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/etc/restapi.secret
	setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/var
	setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/var
	setfacl -R -m d:u:$(DOMJUDGE_USER):rwx    $(CURDIR)/webapp/var
	setfacl -R -m   u:$(DOMJUDGE_USER):rwx    $(CURDIR)/webapp/var
	setfacl -R -m d:m::rwx                    $(CURDIR)/webapp/var
	setfacl -R -m   m::rwx                    $(CURDIR)/webapp/var

maintainer-postinstall-apache: maintainer-postinstall-permissions
	@if [ ! -d "/etc/apache2/conf-enabled" ]; then echo "Couldn't find directory /etc/apache2/conf-enabled. Is apache installed?"; false; fi
	ln -sf $(CURDIR)/etc/apache.conf /etc/apache2/conf-enabled/domjudge.conf
	a2enmod rewrite
	systemctl restart apache2

maintainer-postinstall-nginx: maintainer-postinstall-permissions
	@if [ ! -d "/etc/nginx/sites-enabled/" ]; then echo "Couldn't find directory /etc/nginx/sites-enabled/. Is nginx installed?"; false; fi
	@if [ ! -d "/etc/php/7.0/fpm/pool.d/" ]; then echo "Couldn't find directory /etc/php/7.0/fpm/pool.d/. Is php-fpm installed?"; false; fi
	ln -sf $(CURDIR)/etc/nginx-conf /etc/nginx/sites-enabled/domjudge.conf
	ln -sf $(CURDIR)/etc/domjudge-fpm.conf /etc/php/7.0/fpm/pool.d/domjudge-fpm.conf
	systemctl restart nginx
	systemctl restart php7.0-fpm

# Removes created symlinks; generated logs, submissions, etc. remain in output subdir.
maintainer-uninstall:
	rm -f $(judgehost_libjudgedir) $(domserver_libsubmitdir)
	rm -f $(domserver_wwwdir)/jury/doc
	rm -rf $(judgehost_bindir)

# Rules to configure and build for a Coverity scan.
coverity-conf:
	$(MAKE) maintainer-conf

coverity-build: paths.mk
# First delete some files to keep Coverity scan happy:
	-rm -f tests/test-compile-error.*
	$(MAKE) build build-scripts
	@VERSION=` grep '^VERSION ='   paths.mk | sed 's/^VERSION = *//'` ; \
	PUBLISHED=`grep '^PUBLISHED =' paths.mk | sed 's/^PUBLISHED = *//'` ; \
	if [ "$$PUBLISHED" = release ]; then DESC="release" ; \
	elif [ -n "$$PUBLISHED" ];      then DESC="snapshot $$PUBLISHED" ; \
	elif [ ! -d .git ];             then DESC="unknown source on `date`" ; fi ; \
	echo "VERSION=$$VERSION" > cov-submit-data-version.sh ; \
	if [ -n "$$DESC" ]; then echo "DESC=$$DESC" >> cov-submit-data-version.sh ; fi

clean-l:
# Remove Coverity scan data:
	-rm -rf cov-int domjudge-scan.t* coverity-scan.tar.xz cov-submit-data-version.sh

distclean-l: clean-autoconf
	-rm -f paths.mk

maintainer-clean-l:
	-rm -f configure aclocal.m4

clean-autoconf:
	-rm -rf config.status config.cache config.log autom4te.cache

.PHONY: $(addsuffix -create-dirs,domserver judgehost docs) check-root \
        clean-autoconf $(addprefix maintainer-,conf install uninstall) \
        config submitclient distdocs composer-dependencies \
        coverity-conf coverity-build
