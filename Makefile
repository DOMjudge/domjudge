# Main Makefile

# Define TOPDIR from shell command and _not_ $(PWD) because that gives
# pwd before changing to a 'make -C <dir>' <dir>:
export TOPDIR = $(shell pwd)

REC_TARGETS=build domserver install-domserver judgehost install-judgehost \
            docs install-docs inplace-install inplace-uninstall maintainer-conf \
            maintainer-install dependencies dependencies-dev dependencies-dist

# Global Makefile definitions
include $(TOPDIR)/Makefile.global

debpool := /etc/php/$(PHPVERSION)/fpm/pool.d
fedpool := /etc/php-fpm.d

default:
	@echo "No default target"
	@echo
	@echo "Try:"
	@echo " - make all         (all targets below)"
	@echo " - make build       (all except 'docs')"
	@echo " - make domserver"
	@echo " - make judgehost"
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

ifeq ($(BUILD_DOCS),yes)
all: docs
dist: distdocs
endif

# MAIN TARGETS
domserver: domserver-configure paths.mk config
judgehost: judgehost-configure paths.mk config
docs: paths.mk config
install-domserver: domserver domserver-create-dirs
install-judgehost: judgehost judgehost-create-dirs
install-docs: docs-create-dirs
dist: configure dependencies-dist

domserver-configure:
ifneq "$(DOMSERVER_BUILD_ENABLED)" "yes"
	@echo "The setup for domserver is not configured"
	@exit 1
endif

judgehost-configure:
ifneq "$(JUDGEHOST_BUILD_ENABLED)" "yes"
	@echo "The setup for judgehost is not configured"
	@exit 1
endif

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
build:                      SUBDIRS=        lib           misc-tools
domserver:                  SUBDIRS=etc         sql       misc-tools webapp
install-domserver:          SUBDIRS=etc     lib sql       misc-tools webapp example_problems example_scoring_problems
judgehost:                  SUBDIRS=etc             judge misc-tools
install-judgehost:          SUBDIRS=etc     lib     judge misc-tools
docs:                       SUBDIRS=    doc
install-docs:               SUBDIRS=    doc
maintainer-conf:            SUBDIRS=                                 webapp
maintainer-install:         SUBDIRS=                                 webapp
inplace-install:            SUBDIRS=    doc               misc-tools
inplace-uninstall:          SUBDIRS=    doc               misc-tools
dist:                       SUBDIRS=        lib sql       misc-tools
clean:                      SUBDIRS=etc doc lib sql judge misc-tools webapp
distclean:                  SUBDIRS=etc doc lib sql judge misc-tools webapp
maintainer-clean:           SUBDIRS=etc doc lib sql judge misc-tools webapp
dependencies:               SUBDIRS=                                 webapp
dependencies-dev:           SUBDIRS=                                 webapp
dependencies-dist:          SUBDIRS=                                 webapp

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
# Fix permissions for special directories:
	-$(INSTALL_USER)    -m 0700 -d $(DESTDIR)$(domserver_logdir)
	-$(INSTALL_USER)    -m 0700 -d $(DESTDIR)$(domserver_rundir)
	-for d in cache log ; do \
		$(INSTALL_WEBSITE) -m 0775 -d $(DESTDIR)$(domserver_webappdir)/var/$$d ; \
	done
# Make sure that domjudge user and webserver group can write to var/{cache,log}:
	-DESTDIR=$(DESTDIR) $(DESTDIR)$(domserver_bindir)/fix_permissions
# Special case create tmpdir here, only when FHS not enabled:
ifneq "$(FHS_ENABLED)" "yes"
	-$(INSTALL_WEBSITE) -m 0770 -d $(DESTDIR)$(domserver_tmpdir)
endif
# Fix permissions and ownership for password files:
	-$(INSTALL_USER) -m 0600 -t $(DESTDIR)$(domserver_etcdir) \
		etc/restapi.secret
	-$(INSTALL_USER) -m 0600 -t $(DESTDIR)$(domserver_etcdir) \
		etc/initial_admin_password.secret
	-$(INSTALL_WEBSITE) -m 0640 -t $(DESTDIR)$(domserver_etcdir) \
		etc/dbpasswords.secret etc/symfony_app.secret
	@echo ""
	@echo "Domserver install complete. Admin web interface password can be found in:"
	@echo "$(DESTDIR)$(domserver_etcdir)/initial_admin_password.secret"

install-judgehost-l:
	$(MAKE) check-root
# Fix permissions for special directories:
	-$(INSTALL_USER) -m 0700 -d $(DESTDIR)$(judgehost_logdir)
	-$(INSTALL_USER) -m 0700 -d $(DESTDIR)$(judgehost_rundir)
	-$(INSTALL_USER) -m 0711 -d $(DESTDIR)$(judgehost_judgedir)
# Special case create tmpdir here, only when FHS not enabled:
ifneq "$(FHS_ENABLED)" "yes"
	-$(INSTALL_USER) -m 0700 -d $(DESTDIR)$(judgehost_tmpdir)
endif
# Fix permissions and ownership for password files:
	-$(INSTALL_USER) -m 0600 -t $(DESTDIR)$(judgehost_etcdir) \
		etc/restapi.secret
	@echo ""
	@echo "========== Judgehost Install Completed =========="
	@echo ""
	@echo "Optionally:"
	@echo "    - Install the create-cgroup service to setup the secure judging restrictions:"
	@echo "        cp judge/create-cgroups.service /etc/systemd/system/"
	@echo "    - Install the judgehost service. The unit is named after this"
	@echo "      instance, since it refers to this installation's paths:"
	@echo "        cp judge/domjudge-judgedaemon@.service /etc/systemd/system/$(INSTANCE)-judgedaemon@.service"
	@echo "    - You can enable the judgehost on CPU core 1 with:"
	@echo "        systemctl enable $(INSTANCE)-judgedaemon@1"
	@echo ""

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

# Derive an instance name from the directory this source tree lives in, so
# that several checkouts or worktrees can be installed in place next to
# each other without overwriting each other's webserver configuration or
# sharing a database. A checkout named 'domjudge' keeps the historic
# defaults. Sanitizing is repeated by configure; it is done here as well
# because the base URL below is built from the result.
INPLACE_INSTANCE := $(shell echo '$(notdir $(CURDIR))' | tr 'A-Z' 'a-z' | \
	sed -e 's/[^a-z0-9-][^a-z0-9-]*/-/g' -e 's/--*/-/g' \
	    -e 's/^-//' -e 's/-$$//' | cut -c1-24 | sed -e 's/-$$//')
ifeq ($(INPLACE_INSTANCE),domjudge)
INPLACE_BASEURL := http://localhost/domjudge/
else
INPLACE_BASEURL := http://$(INPLACE_INSTANCE).localhost/
endif

# Configure for running in source tree, not meant for normal use:
maintainer-conf: inplace-conf-common dependencies-dev
inplace-conf: inplace-conf-common dependencies
inplace-conf-common: dist
# Without a derived name there is nothing to build the base URL from
# either: it would come out as 'http://.localhost/', which configure has no
# reason to reject. Both flags therefore have to be given by hand.
	@if [ -z '$(INPLACE_INSTANCE)' ] && \
	    ! { echo '$(CONFIGURE_FLAGS)' | grep -q -- '--with-instance-name=' && \
	        echo '$(CONFIGURE_FLAGS)' | grep -q -- '--with-baseurl='; }; then \
		echo "ERROR: cannot derive an instance name from directory '$(notdir $(CURDIR))'."; \
		echo "       Pass both the name and a matching base URL explicitly:"; \
		echo "         make maintainer-conf CONFIGURE_FLAGS=\"--with-instance-name=NAME --with-baseurl=http://NAME.localhost/\""; \
		exit 1; \
	fi
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
	            --with-domserver_databasedumpdir=$(CURDIR)/output/db-dumps \
	            --with-instance-name='$(INPLACE_INSTANCE)' \
	            --with-baseurl='$(INPLACE_BASEURL)' \
	            $(CONFIGURE_FLAGS)

# Install the system in place: don't really copy stuff, but create
# symlinks where necessary to let it work from the source tree.
# This stuff is a hack!
maintainer-install: inplace-install
inplace-install: build domserver-create-dirs judgehost-create-dirs
inplace-install-l:
# Replace libjudgedir with symlink to prevent lots of symlinks:
	-rmdir $(judgehost_libjudgedir)
	-rm -f $(judgehost_libjudgedir)
	ln -sf $(CURDIR)/judge  $(judgehost_libjudgedir)
# Add symlinks to binaries:
	$(MKDIR_P) $(judgehost_bindir) $(domserver_bindir)
	ln -sf $(CURDIR)/judge/judgedaemon $(judgehost_bindir)
	ln -sf $(CURDIR)/judge/runguard $(judgehost_bindir)
	ln -sf $(CURDIR)/judge/runpipe  $(judgehost_bindir)
	ln -sf $(CURDIR)/judge/create_cgroups  $(judgehost_bindir)
	ln -sf $(CURDIR)/sql/dj_setup_database $(domserver_bindir)
	ln -sf $(CURDIR)/webapp/bin/console $(domserver_bindir)/dj_console
# Create tmpdir and make tmpdir writable for webserver,
# because judgehost-create-dirs sets wrong permissions:
	$(MKDIR_P) $(domserver_tmpdir)
	chmod a+rwx $(domserver_tmpdir)
# Make sure we're running from a clean state:
	(cd webapp && composer auto-scripts)
	@echo ""
	@echo "========== Maintainer Install Completed =========="
	@echo ""
	@echo "Instance name: $(INSTANCE)"
	@echo "Base URL.....: $(BASEURL)"
	@echo ""
	@echo "Next:"
	@echo "    - Configure nginx"
	@echo "        sudo make inplace-postinstall-nginx"
	@echo "    - Configure apache2"
	@echo "        sudo make inplace-postinstall-apache"
	@echo "    - Configure judgedaemon"
	@echo "        sudo make inplace-postinstall-judgedaemon"
	@echo "    - Set up database"
	@echo "        ./sql/dj_setup_database -u root [-r|-p ROOT_PASS] install"
	@echo ""
	@echo "Or you can run these commands manually as root"
	@echo "    - Give the webserver access to things it needs"
	@echo "        setfacl    -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/etc/dbpasswords.secret"
	@echo "        setfacl    -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/etc/symfony_app.secret"
	@echo "        setfacl    -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/etc/domserver-static.php"
	@echo "        setfacl    -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/etc/verdicts.php"
	@echo "        setfacl -R -m d:u:$(WEBSERVER_GROUP):rx   $(CURDIR)/webapp"
	@echo "        setfacl -R -m   u:$(WEBSERVER_GROUP):rx   $(CURDIR)/webapp"
	@echo "        setfacl    -m d:u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc"
	@echo "        setfacl    -m   u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc"
	@echo "        setfacl    -m d:u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc/manual"
	@echo "        setfacl    -m   u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc/manual"
	@echo "        setfacl    -m d:u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc/manual/build"
	@echo "        setfacl    -m   u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc/manual/build"
	@echo "        setfacl -R -m d:u:$(WEBSERVER_GROUP):rx   $(CURDIR)/doc/manual/build/html"
	@echo "        setfacl -R -m   u:$(WEBSERVER_GROUP):rx   $(CURDIR)/doc/manual/build/html"
	@echo "        setfacl -R -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/doc/manual/build/domjudge-team-manual.pdf"
	@echo "        setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/var"
	@echo "        setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/var"
	@echo "        setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/countries"
	@echo "        setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/countries"
	@echo "        setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/teams"
	@echo "        setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/teams"
	@echo "        setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/banners"
	@echo "        setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/banners"
	@echo "        setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/affiliations"
	@echo "        setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/affiliations"
	@echo "        setfacl -R -m d:m::rwx                    $(CURDIR)/webapp/var"
	@echo "        setfacl -R -m   m::rwx                    $(CURDIR)/webapp/var"
	@echo "        setfacl -R -m mask::rwx                   $(CURDIR)"
	@echo "        # Also make sure you keep access"
	@echo "        setfacl -R -m d:u:$(DOMJUDGE_USER):rwx  $(CURDIR)/webapp/var"
	@echo "        setfacl -R -m   u:$(DOMJUDGE_USER):rwx  $(CURDIR)/webapp/var"
	@echo "        And manually make sure the webserver has traversal access to: $(CURDIR)"
	@echo "    - Configure webserver"
	@echo "        Nginx + PHP-FPM:"
	@echo "           ln -sf $(CURDIR)/etc/nginx-conf /etc/nginx/sites-enabled/$(INSTANCE).conf"
	@echo "           ln -sf $(CURDIR)/etc/domjudge-fpm.conf /etc/php/$(PHPVERSION)/fpm/pool.d/$(INSTANCE)-fpm.conf"
	@echo "           systemctl restart nginx"
	@echo "           systemctl restart php-fpm"
	@echo "        Apache 2:"
	@echo "           ln -sf $(CURDIR)/etc/apache.conf /etc/apache2/conf-available/$(INSTANCE).conf"
	@echo "           a2enconf $(INSTANCE)"
	@echo "           a2enmod rewrite headers"
	@echo "           systemctl restart apache2"
	@echo ""
	@echo "The admin password for the web interface is in etc/initial_admin_password.secret"

inplace-postinstall-permissions:
	setfacl    -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/etc/dbpasswords.secret
	setfacl    -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/etc/symfony_app.secret
	setfacl    -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/etc/domserver-static.php
	setfacl    -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/etc/verdicts.php
	setfacl -R -m d:u:$(WEBSERVER_GROUP):rx   $(CURDIR)/webapp
	setfacl -R -m   u:$(WEBSERVER_GROUP):rx   $(CURDIR)/webapp
	setfacl    -m d:u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc
	setfacl    -m   u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc
	setfacl    -m d:u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc/manual
	setfacl    -m   u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc/manual
ifneq ($(DOC_BUILD_ENABLED),no)
	setfacl    -m d:u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc/manual/build
	setfacl    -m   u:$(WEBSERVER_GROUP):x    $(CURDIR)/doc/manual/build
	setfacl -R -m d:u:$(WEBSERVER_GROUP):rx   $(CURDIR)/doc/manual/build/html
	setfacl -R -m   u:$(WEBSERVER_GROUP):rx   $(CURDIR)/doc/manual/build/html
	setfacl -R -m   u:$(WEBSERVER_GROUP):r    $(CURDIR)/doc/manual/build/domjudge-team-manual.pdf
endif
	setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/var
	setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/var
	setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/countries
	setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/countries
	setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/teams
	setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/teams
	setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/banners
	setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/banners
	setfacl -R -m d:u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/affiliations
	setfacl -R -m   u:$(WEBSERVER_GROUP):rwx  $(CURDIR)/webapp/public/images/affiliations
	setfacl -R -m d:u:$(DOMJUDGE_USER):rwx    $(CURDIR)/webapp/var
	setfacl -R -m   u:$(DOMJUDGE_USER):rwx    $(CURDIR)/webapp/var
	setfacl -R -m d:m::rwx                    $(CURDIR)/webapp/var
	setfacl -R -m   m::rwx                    $(CURDIR)/webapp/var
	setfacl -R -m mask::rwx                   $(CURDIR)
	if command -v sestatus >/dev/null 2>&1; then \
		chcon -R -t httpd_sys_content_t $(CURDIR)/webapp; \
		chcon -R -t httpd_config_t $(CURDIR)/etc; \
		chcon -R -t httpd_log_t $(CURDIR)/webapp/var/log; \
		chcon -R -t httpd_sys_rw_content_t $(CURDIR)/webapp/var/cache; \
		chcon -R -t httpd_sys_rw_content_t $(CURDIR)/webapp/public/images; \
		chcon    -t httpd_exec_t $(CURDIR)/lib/alert; \
	fi
	@sandbox_err=0; \
	for service in apache2 nginx php$(PHPVERSION)-fpm php-fpm; do \
		$(CURDIR)/misc-tools/check-systemd-sandbox $$service $(CURDIR)/webapp/var $(domserver_tmpdir) || sandbox_err=1; \
	done; \
	if [ $$sandbox_err -ne 0 ]; then \
		echo "ERROR: Fix the above systemd sandboxing issue(s) before continuing."; \
		exit 1; \
	fi

# The installed file names are derived from the instance name so that
# several in-place installs can coexist on one host. Refuse to take over a
# file that belongs to a different source tree: two trees whose directory
# names reduce to the same instance name would otherwise silently steal
# each other's webserver configuration.
define check_instance_clash
@target='$(1)'; \
if [ -L "$$target" ]; then \
	current=`readlink -f "$$target" 2>/dev/null`; \
	case "$$current" in $(CURDIR)/*) ;; *) \
		echo "ERROR: '$$target' already belongs to another DOMjudge tree:"; \
		echo "           $${current:-<unresolvable>}"; \
		echo "       This tree is configured as instance '$(INSTANCE)'."; \
		echo "       Reconfigure it with a different --with-instance-name=NAME."; \
		exit 1 ;; \
	esac; \
elif [ -e "$$target" ]; then \
	echo "ERROR: '$$target' exists and is not a symlink, so it was not"; \
	echo "       created by an in-place install; refusing to overwrite it."; \
	echo "       This tree is configured as instance '$(INSTANCE)'."; \
	echo "       Reconfigure it with a different --with-instance-name=NAME."; \
	exit 1; \
fi
endef

inplace-postinstall-apache: inplace-postinstall-permissions
	@if [ ! -d "/etc/apache2/conf-enabled" ]; then echo "Couldn't find directory /etc/apache2/conf-enabled. Is apache installed?"; false; fi
	$(call check_instance_clash,/etc/apache2/conf-available/$(INSTANCE).conf)
	$(call check_instance_clash,/etc/apache2/conf-enabled/$(INSTANCE).conf)
	ln -sf $(CURDIR)/etc/apache.conf /etc/apache2/conf-available/$(INSTANCE).conf
	a2enconf $(INSTANCE)
	a2enmod rewrite headers
	systemctl restart apache2

inplace-postinstall-nginx: inplace-postinstall-permissions
	@if [ ! -d "/etc/nginx/" ]; then echo "Couldn't find directory /etc/nginx/. Is nginx installed?"; false; fi
	@if [ ! -d "$(debpool)" ] && [ ! -d "$(fedpool)" ]; then \
		echo "Couldn't find directory $(debpool) or $(fedpool). Is php-fpm installed?"; false; \
	fi
	$(call check_instance_clash,/etc/nginx/sites-enabled/$(INSTANCE).conf)
	$(call check_instance_clash,/etc/nginx/conf.d/$(INSTANCE).conf)
	$(call check_instance_clash,$(debpool)/$(INSTANCE)-fpm.conf)
	$(call check_instance_clash,$(fedpool)/$(INSTANCE)-fpm.conf)
	@cmd="ln -sf $(CURDIR)/etc/nginx-conf /etc/nginx/conf.d/$(INSTANCE).conf"; \
	if [ -d "/etc/nginx/sites-enabled/" ]; then \
		cmd="ln -sf $(CURDIR)/etc/nginx-conf /etc/nginx/sites-enabled/$(INSTANCE).conf"; \
	fi; echo $$cmd; $$cmd
	systemctl restart nginx
	@service="php-fpm"; phppool="$(fedpool)"; \
	if [ -d "$(debpool)" ]; then \
		phppool="$(debpool)"; \
		service="php$(PHPVERSION)-fpm"; \
	fi; \
	service="systemctl restart $$service"; \
	ln="ln -sf $(CURDIR)/etc/domjudge-fpm.conf $$phppool/$(INSTANCE)-fpm.conf"; \
	echo $$ln; echo $$service; $$ln; $$service

# This file is copied rather than symlinked, so check_instance_clash cannot
# tell it apart from another tree's; compare the contents instead, which
# differ because they name this tree's judgehost paths.
inplace-postinstall-judgedaemon:
	@target=/etc/sudoers.d/$(INSTANCE); \
	if [ -e "$$target" ] && ! cmp -s $(CURDIR)/etc/sudoers-domjudge "$$target"; then \
		echo "ERROR: '$$target' exists with different contents, so it"; \
		echo "       probably belongs to another DOMjudge tree."; \
		echo "       This tree is configured as instance '$(INSTANCE)'."; \
		echo "       Reconfigure it with a different --with-instance-name=NAME."; \
		exit 1; \
	fi
	cp $(CURDIR)/etc/sudoers-domjudge /etc/sudoers.d/$(INSTANCE)
	chown root:root /etc/sudoers.d/$(INSTANCE)
	chmod 0600 /etc/sudoers.d/$(INSTANCE)

# Removes created symlinks; generated logs, submissions, etc. remain in output subdir.
inplace-uninstall-l:
	rm -rf $(judgehost_libjudgedir)
	rm -rf $(judgehost_bindir)

# Rules to configure and build for a Coverity scan.
coverity-conf:
	$(MAKE) inplace-conf

coverity-build: paths.mk
	$(MAKE) build build-scripts
# Secondly, delete all upstream PHP libraries to not analyze those:
	-rm -rf webapp/vendor/*
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

maintainer-clean: inplace-uninstall
maintainer-clean-l:
	-rm -f configure aclocal.m4

clean-autoconf:
	-rm -rf config.status config.cache config.log autom4te.cache

.PHONY: $(addsuffix -create-dirs,domserver judgehost docs) check-root \
        $(addprefix inplace-,conf conf-common install uninstall) \
        $(addprefix maintainer-,conf install) clean-autoconf config distdocs \
        $(addprefix dependencies-,dev dist) dependencies coverity-conf coverity-build
