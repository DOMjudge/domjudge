ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

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
	$(INSTALL_DIR)     $(domserver_etcdir)
	$(INSTALL_DATA) -t $(domserver_etcdir) apache.conf domserver-static.php domserver-config.php common-config.php

install-perm-domserver:
	$(INSTALL_PRIVATE) -t $(domserver_etcdir) dbpasswords.secret htpasswd-jury
	$(INSTALL_PRIVATE) -m 0770 -d $(domserver_submitdir)

install-judgehost:
	$(INSTALL_DIR)     $(judgehost_etcdir)
	$(INSTALL_DATA) -t $(judgehost_etcdir) judgehost-static.php judgehost-config.php common-config.php

install-perm-judgehost:
	$(INSTALL_PRIVATE) -t $(judgehost_etcdir) dbpasswords.secret

distclean-l:
	-rm -f $(SUBST_CONFIGS)
