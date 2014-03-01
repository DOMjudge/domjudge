ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

# Note:
# Although dbpasswords.secret and restapi.secret is generated during
# building/installing, we only remove them with 'distclean', for in
# maintainer-install mode it's inconvenient to have these regenerated
# with different passwords from those in the database.
#
# These files should probably not be generated in the source tree
# anyways, but it is more convenient to be able to share the passwords
# between domserver and judgehost installs.

SUBST_CONFIGS = apache.conf domserver-static.php domserver-static.h \
                judgehost-static.php runguard-config.h submit-config.h \
                sudoers-domjudge cgroup-domjudge.conf

$(SUBST_CONFIGS): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

config: $(SUBST_CONFIGS)

domserver: apache.conf dbpasswords.secret restapi.secret

judgehost:

dbpasswords.secret:
	echo "# Randomly generated on host `hostname`, `date`" > $@
	chmod go= $@
	./gendbpasswords >> $@

restapi.secret:
	echo "# Randomly generated on host `hostname`, `date`" > $@
	chmod go= $@
	./genrestapicredentials >> $@

install-domserver:
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_etcdir) \
		apache.conf domserver-static.php
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_etcdir) \
		gendbpasswords
	-$(INSTALL_USER) -t $(DESTDIR)$(domserver_etcdir) \
		domserver-config.php common-config.php
	-$(INSTALL_WEBSITE) -m 0640 -t $(DESTDIR)$(domserver_etcdir) \
		dbpasswords.secret

install-judgehost:
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_etcdir) \
		judgehost-static.php sudoers-domjudge
	-$(INSTALL_USER) -t $(DESTDIR)$(judgehost_etcdir) \
		judgehost-config.php common-config.php

# Let only distclean (_not_ the local variant) depend on password
# removal, so these don't get removed with maintainer-clean.
distclean: clean-passwords

distclean-l:
	-rm -f $(SUBST_CONFIGS) config.h

clean-passwords:
	-rm -f dbpasswords.secret restapi.secret

maintainer-clean-l:
	-rm -f config.h.in

.PHONY: clean-passwords
