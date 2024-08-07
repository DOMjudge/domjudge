ifndef TOPDIR
TOPDIR=..
endif

REC_TARGETS = domserver

include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = config vendor

maintainer-conf: .env.local

# Run Symfony in dev mode (for maintainer-mode):
.env.local:
	@echo "Creating file '$@'..."
	@echo "# This file was automatically created by 'make maintainer-conf' to run" > $@
	@echo "# the DOMjudge Symfony application in developer mode. Adjust as needed." >> $@
	@echo "APP_ENV=dev" >> $@

composer-dump-autoload-dev:
	composer $(subst 1,-q,$(QUIET)) dump-autoload

copy-bundle-assets:
# We can not use bin/console here, as when using a fakeroot,
# the include paths are broken. We just copy in the data we need
	-rm -rf public/bundles/nelmioapidoc
	mkdir -p public/bundles/nelmioapidoc
	cp -R vendor/nelmio/api-doc-bundle/public/* public/bundles/nelmioapidoc/

clean-l:
	-rm -rf public/bundles/nelmioapidoc

install-domserver:
# This must be done first to install with the rest.
	$(MAKE) copy-bundle-assets
	$(INSTALL_DIR) $(DESTDIR)$(domserver_webappdir);
	for d in bin config migrations public resources src templates tests vendor; do \
		$(call install_tree,$(DESTDIR)$(domserver_webappdir),$$d) ; \
	done
# Change webapp/public/doc symlink
	rm $(DESTDIR)$(domserver_webappdir)/public/doc
	ln -s $(domjudge_docdir) $(DESTDIR)$(domserver_webappdir)/public/doc
# Now change all relative symlinks in webapp/public to their correct paths
	for link in $$(find $(DESTDIR)$(domserver_webappdir)/public/$$dir -maxdepth 2 -type l); do \
		target=$$(readlink $$link) ; \
		case $${target} in *../vendor*) \
			rm $$link ; \
			realtarget=$(domserver_webappdir)/vendor$${target#*../vendor} ; \
			ln -s $$realtarget $$link ; \
		esac \
	done
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_webappdir) phpunit.xml.dist .env

inplace-install: composer-autoclean
maintainer-install: composer-dump-autoload-dev

# Install PHP dependencies
composer-dependencies:
ifeq (, $(shell command -v composer 2> /dev/null))
	$(error "'composer' command not found in $(PATH), install it via your package manager or https://getcomposer.org/download/")
endif
# We use --no-scripts here because at this point the autoload.php file is
# not generated yet, which is needed to run the post-install scripts.
	composer $(subst 1,-q,$(QUIET)) install --prefer-dist -o -a --no-scripts --no-plugins

composer-dependencies-dev:
	composer $(subst 1,-q,$(QUIET)) install --prefer-dist --no-scripts --no-plugins

composer-autoclean:
# Make sure we're running from a clean state:
	composer auto-scripts

maintainer-clean-l:
	-for d in cache log ; do \
		for t in dev prod ; do \
			rm -rf var/$$d/$$t ; \
		done ; \
	done

.PHONY: copy-bundle-assets
