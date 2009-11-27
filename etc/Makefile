ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

# Note:
# Although dbpasswords.secret and htpasswd-jury are generated during
# building/installing, we only remove them with 'distclean', for in
# maintainer-install mode it's inconvenient to have these regenerated
# with different passwords from those in the database.
#
# These files should probably not be generated in the source tree
# anyways, but it is more convenient to be able to share the passwords
# between domserver and judgehost installs.

SUBST_CONFIGS = apache.conf domserver-static.php domserver-static.h \
                judgehost-static.php runguard-config.h submit-config.h

$(SUBST_CONFIGS): %: %.in ../paths.mk
	$(substconfigvars)

config: $(SUBST_CONFIGS)

domserver: apache.conf dbpasswords.secret htpasswd-jury

judgehost: dbpasswords.secret

dbpasswords.secret:
	echo "# Randomly generated on host `hostname`, `date`" > $@
	chmod go= $@
	./gendbpasswords >> $@

htpasswd-jury: dbpasswords.secret
	touch $@
	chmod go= $@
	htpasswd -b $@ domjudge_jury `grep '^jury:' $< | cut -d : -f 5`

install-domserver:
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_etcdir) \
		apache.conf domserver-static.php
	$(INSTALL_SCRIPT) -t $(DESTDIR)$(domserver_etcdir) \
		gendbpasswords
	-$(INSTALL_USER) -t $(DESTDIR)$(domserver_etcdir) \
		domserver-config.php common-config.php
	-$(INSTALL_WEBSITE) -m 0640 -t $(DESTDIR)$(domserver_etcdir) \
		dbpasswords.secret htpasswd-jury

install-judgehost:
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_etcdir) \
		judgehost-static.php
	-$(INSTALL_USER) -t $(DESTDIR)$(judgehost_etcdir) \
		judgehost-config.php common-config.php
	-$(INSTALL_USER) -m 0600 -t $(DESTDIR)$(judgehost_etcdir) \
		dbpasswords.secret

distclean-l:
	-rm -f $(SUBST_CONFIGS) config.h \
		dbpasswords.secret htpasswd-jury

maintainer-clean-l:
	-rm -f etc/config.h.in
