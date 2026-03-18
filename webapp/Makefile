ifndef TOPDIR
TOPDIR=..
endif

REC_TARGETS = domserver

include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = config

domserver: composer-dump-autoload

dependencies: composer-dependencies node-dependencies
dependencies-dist: composer-dependencies node-dependencies-dist

# Install PHP dependencies
composer-dependencies:
ifeq (, $(shell command -v composer 2> /dev/null))
	$(error "'composer' command not found in $(PATH), install it via your package manager or https://getcomposer.org/download/")
endif
# We use --no-scripts here because at this point the autoload.php file is
# not generated yet, which is needed to run the post-install scripts.
	composer $(subst 1,-q,$(QUIET)) install --prefer-dist -o -a --no-scripts --no-plugins

dependencies-dev: composer-dependencies-dev node-dependencies

composer-dependencies-dev:
	composer $(subst 1,-q,$(QUIET)) install --prefer-dist --no-scripts --no-plugins

# Dump autoload dependencies (including plugins)
# This is needed since symfony/runtime is a Composer plugin that runs while dumping
# the autoload file.
# We skip it if autoload_runtime.php already exists, to avoid running composer
# as root during `sudo make install-domserver`.
composer-dump-autoload: vendor/autoload_runtime.php

vendor/autoload_runtime.php:
	composer $(subst 1,-q,$(QUIET)) dump-autoload -o -a

composer-dump-autoload-dev:
	composer $(subst 1,-q,$(QUIET)) dump-autoload

define detect_yarn
YARN_CMD=$$(command -v yarn || command -v yarnpkg); \
if [ -z "$$YARN_CMD" ]; then \
  echo "Error: neither 'yarnpkg' nor 'yarn' found in PATH"; \
  exit 1; \
fi
endef

define yarn_licenses_warn
if ! $$YARN_CMD licenses generate-disclaimer > LICENSES.txt 2>/dev/null; then \
  echo "Warning: 'yarn licenses' not available; skipping LICENSES.txt generation."; \
fi
endef

define yarn_licenses_strict
if ! $$YARN_CMD licenses generate-disclaimer > LICENSES.txt 2>/dev/null; then \
  echo "Error: 'yarn licenses' not available but required"; \
  exit 1; \
fi
endef

node-dependencies:
	${ \
		$(detect_yarn); \
		$$YARN_CMD install; \
		$(yarn_licenses_warn); \
	}

node-dependencies-dist:
	${ \
		$(detect_yarn); \
		$$YARN_CMD install; \
		$(yarn_licenses_strict); \
	}

# Run Symfony in dev mode (for maintainer-mode):
.env.local:
	@echo "Creating file '$@'..."
	@echo "# This file was automatically created by 'make maintainer-conf' to run" > $@
	@echo "# the DOMjudge Symfony application in developer mode. Adjust as needed." >> $@
	@echo "APP_ENV=dev" >> $@

copy-bundle-assets:
# We can not use bin/console here, as when using a fakeroot,
# the include paths are broken. We just copy in the data we need
	-rm -rf public/bundles/nelmioapidoc
	mkdir -p public/bundles/nelmioapidoc
	cp -R vendor/nelmio/api-doc-bundle/public/* public/bundles/nelmioapidoc/

clean-l:
	-rm -rf public/bundles/nelmioapidoc
	-rm -f vendor/autoload_runtime.php

install-domserver:
# This must be done first to install with the rest.
	$(MAKE) copy-bundle-assets
	$(INSTALL_DIR) $(DESTDIR)$(domserver_webappdir);
	for d in bin config migrations public resources src templates tests vendor node_modules; do \
		$(call install_tree,$(DESTDIR)$(domserver_webappdir),$$d) ; \
	done
# Add Symlink to Symfony console that is in the standard path
	ln -s $(domserver_webappdir)/bin/console $(DESTDIR)$(domserver_bindir)/dj_console
# Change webapp/public/doc symlink
	ln -sf $(domjudge_docdir) $(DESTDIR)$(domserver_webappdir)/public/doc
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_webappdir) phpunit.xml.dist .env

maintainer-conf: .env.local

maintainer-install: composer-dump-autoload-dev

maintainer-clean-l:
	-for d in cache log ; do \
		for t in dev prod ; do \
			rm -rf var/$$d/$$t ; \
		done ; \
	done

.PHONY: composer-dump-autoload composer-dump-autoload-dev copy-bundle-assets \
        composer-dependencies composer-dependencies-dev node-dependencies
