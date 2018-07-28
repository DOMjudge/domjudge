ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

PARAMS = app/config/parameters.yml

SUBST_CONFIGS = $(PARAMS).dist

$(SUBST_CONFIGS): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

$(PARAMS): %: %.dist $(TOPDIR)/etc/dbpasswords.secret
	@[ -n "$(QUIET)" ] || echo "Generating '$@'..."
	$(TOPDIR)/etc/gensymfonyparams $^ > $@

# Rule to generate $(PARAMS) file from a stub during 'make dist':
params-from-stub: $(PARAMS).stub
	if [ ! -r $(PARAMS) ]; then \
		cp $< $(PARAMS) ; \
		touch -d '1971-01-01 00:00:00' $(PARAMS) ; \
	fi

# Clear Symfony cache for maintainer-mode operation:
clear-cache:
	bin/console --env=dev cache:clear
	bin/console --env=prod cache:clear

# Don't try to create dbpasswords.secret here if it doesn't exist:
$(TOPDIR)/etc/dbpasswords.secret:

domserver: $(PARAMS) $(SUBST_CONFIGS)

install-domserver:
	$(INSTALL_DIR) $(DESTDIR)$(domserver_webappdir);
# KLUDGE: how to install a tree of files with correct permissions?
	for d in app bin src web tests ; do \
		cp -R           -t $(DESTDIR)$(domserver_webappdir) $$d ; \
		chmod -R a+rX      $(DESTDIR)$(domserver_webappdir)/$$d ; \
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

distclean-l:
	-rm -f $(SUBST_CONFIGS) $(PARAMS)

maintainer-clean-l:
	-for d in cache logs sessions ; do \
		for t in dev prod ; do \
			rm -rf var/$$d/$$t ; \
		done ; \
	done

.PHONY: params-from-stub clear-cache
