ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

# Note:
# Although dbpasswords.secret and restapi.secret are generated during
# building/installing, we only remove them with 'distclean', for in
# maintainer-install mode it's inconvenient to have these regenerated
# with different passwords from those in the database.
#
# These files should probably not be generated in the source tree
# anyway, but it is more convenient to be able to share the passwords
# between domserver and judgehost installs.

SUBST_CONFIGS = apache.conf nginx-conf nginx-conf-inner domjudge-fpm.conf domserver-static.php \
                judgehost-static.php runguard-config.h submit-config.h \
                sudoers-domjudge domjudge-judgehost.init

$(SUBST_CONFIGS): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

config: $(SUBST_CONFIGS)

domserver: apache.conf nginx-conf nginx-conf-inner dbpasswords.secret restapi.secret initial_admin_password.secret

judgehost:

dbpasswords.secret:
	echo "# Randomly generated on host `hostname`, `date`" > $@
	chmod go= $@
	./gendbpasswords >> $@

restapi.secret:
	echo "# Randomly generated on host `hostname`, `date`" > $@
	chmod go= $@
	./genrestapicredentials >> $@

initial_admin_password.secret:
	touch $@
	chmod go= $@
	./genadminpassword >> $@

install-domserver:
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_etcdir) \
		apache.conf nginx-conf nginx-conf-inner domserver-static.php domjudge-fpm.conf import-forwardfeed.yaml
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_etcdir) \
		gendbpasswords genrestapicredentials genadminpassword
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_etcdir) \
		domserver-config.php common-config.php verdicts.php
# Password files are installed from root Makefile.

install-judgehost:
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_etcdir) \
		judgehost-static.php sudoers-domjudge genrestapicredentials
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_etcdir) \
		judgehost-config.php common-config.php
# Password files are installed from root Makefile.

# Let only distclean (_not_ the local variant) depend on password
# removal, so these don't get removed with maintainer-clean.
distclean: clean-passwords

distclean-l:
	-rm -f $(SUBST_CONFIGS) config.h

clean-passwords:
	-rm -f dbpasswords.secret restapi.secret initial_admin_password.secret

maintainer-clean-l:
	-rm -f config.h.in

.PHONY: clean-passwords
