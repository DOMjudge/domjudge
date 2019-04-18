ifndef TOPDIR
TOPDIR=..
endif

REC_TARGETS = domserver

include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = app web bin

# Clear Symfony cache for maintainer-mode operation:
clear-cache:
	bin/console --env=dev cache:clear
	bin/console --env=prod cache:clear

install-domserver:
	$(INSTALL_DIR) $(DESTDIR)$(domserver_webappdir);
# KLUDGE: how to install a tree of files with correct permissions?
	for d in app bin src web tests ; do \
		cp -R           -t $(DESTDIR)$(domserver_webappdir) $$d ; \
		chmod -R a+rX      $(DESTDIR)$(domserver_webappdir)/$$d ; \
	done
# Change webapp/web/doc symlink
	rm $(DESTDIR)$(domserver_webappdir)/web/doc
	ln -s $(domjudge_docdir) $(DESTDIR)$(domserver_webappdir)/web/doc
# Now change all relative symlinks in webapp/web to their correct paths
	for link in $$(find $(DESTDIR)$(domserver_webappdir)/web/$$dir -maxdepth 2 -type l); do \
		target=$$(readlink $$link) ; \
		if echo $${target} | grep -q '\.\./\.\./lib/vendor' ; then \
			rm $$link ; \
			realtarget=$(domserver_libvendordir)$$(echo $${target} | sed 's!^.*\.\./\.\./lib/vendor!!') ; \
			ln -s $$realtarget $$link ; \
		fi \
	done
	-for d in cache logs sessions ; do \
		$(INSTALL_WEBSITE) -m 0775 -d $(DESTDIR)$(domserver_webappdir)/var/$$d ; \
	done
	-$(INSTALL_WEBSITE) -t $(DESTDIR)$(domserver_webappdir)/var var/*.php var/*.cache
	cp        -t $(DESTDIR)$(domserver_webappdir) phpunit.xml.dist
	chmod a+r    $(DESTDIR)$(domserver_webappdir)/phpunit.xml.dist

install-docs:
	$(INSTALL_DATA) -T web/images/countries/README    $(DESTDIR)$(domjudge_docdir)/README.country_flags
	$(INSTALL_DATA) -T web/images/countries/codes.txt $(DESTDIR)$(domjudge_docdir)/country_codes.txt

maintainer-clean-l:
	-for d in cache logs sessions ; do \
		for t in dev prod ; do \
			rm -rf var/$$d/$$t ; \
		done ; \
	done

.PHONY: clear-cache
