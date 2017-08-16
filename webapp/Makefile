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

# Don't try to create dbpasswords.secret here if it doesn't exist:
$(TOPDIR)/etc/dbpasswords.secret:

domserver: $(PARAMS) $(SUBST_CONFIGS)

install-domserver:
	$(INSTALL_DIR) $(DESTDIR)$(domserver_webappdir);
# KLUDGE: how to install a tree of files with correct permissions?
	for d in app bin src web ; do \
		cp -R           -t $(DESTDIR)$(domserver_webappdir) $$d ; \
		chmod -R a+rX      $(DESTDIR)$(domserver_webappdir)/$$d ; \
	done
	-for d in cache logs sessions ; do \
		$(INSTALL_WEBSITE) -m 0775 -d $(DESTDIR)$(domserver_webappdir)/var/$$d ; \
	done
	-$(INSTALL_WEBSITE) -t $(DESTDIR)$(domserver_webappdir)/var var/*.php var/*.cache

install-docs:
	$(INSTALL_DATA) -T web/images/countries/README    $(DESTDIR)$(domjudge_docdir)/README.country_flags
	$(INSTALL_DATA) -T web/images/countries/codes.txt $(DESTDIR)$(domjudge_docdir)/country_codes.txt

distclean-l:
	-rm -f $(SUBST_CONFIGS) $(PARAMS)
