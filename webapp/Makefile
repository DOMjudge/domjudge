ifndef TOPDIR
TOPDIR=..
endif

REC_TARGETS = domserver

include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = config

copy-bundle-assets:
# We can not use bin/console here, as when using a fakeroot,
# the include paths are broken. We just copy in the data we need
	-rm -rf public/bundles/nelmioapidoc
	mkdir -p public/bundles/nelmioapidoc
	cp -R ../lib/vendor/nelmio/api-doc-bundle/Resources/public/* public/bundles/nelmioapidoc/

clean-l:
	-rm -rf public/bundles/nelmioapidoc

install-domserver:
# This must be done first to install with the rest.
	$(MAKE) copy-bundle-assets
	$(INSTALL_DIR) $(DESTDIR)$(domserver_webappdir);
	for d in bin config public src templates tests ; do \
		$(call install_tree,$(DESTDIR)$(domserver_webappdir),$$d) ; \
	done
# Change webapp/public/doc symlink
	rm $(DESTDIR)$(domserver_webappdir)/public/doc
	ln -s $(domjudge_docdir) $(DESTDIR)$(domserver_webappdir)/public/doc
# Now change all relative symlinks in webapp/public to their correct paths
	for link in $$(find $(DESTDIR)$(domserver_webappdir)/public/$$dir -maxdepth 2 -type l); do \
		target=$$(readlink $$link) ; \
		if echo $${target} | grep -q '\.\./\.\./lib/vendor' ; then \
			rm $$link ; \
			realtarget=$(domserver_libvendordir)$$(echo $${target} | sed 's!^.*\.\./\.\./lib/vendor!!') ; \
			ln -s $$realtarget $$link ; \
		fi \
	done
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_webappdir) phpunit.xml.dist .env

install-docs:
	$(INSTALL_DATA) -T public/images/countries/README    $(DESTDIR)$(domjudge_docdir)/README.country_flags
	$(INSTALL_DATA) -T public/images/countries/codes.txt $(DESTDIR)$(domjudge_docdir)/country_codes.txt

maintainer-clean-l:
	-for d in cache log ; do \
		for t in dev prod ; do \
			rm -rf var/$$d/$$t ; \
		done ; \
	done

.PHONY: copy-bundle-assets
