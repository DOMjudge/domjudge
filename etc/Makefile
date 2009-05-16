ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

SUBST_CONFIGS = apache.conf domserver-static.php domserver-static.h \
                judgehost-static.php runguard-config.h submit-config.h

$(SUBST_CONFIGS): %: %.in ../paths.mk
	@echo "Substituting configure variables in '$@'."
	@cat $< | $(substconfigvars) > $@

config: domserver-static.php domserver-static.h \
        judgehost-static.php runguard-config.h submit-config.h

domserver: apache.conf dbpasswords.secret htpasswd-jury

judgehost: dbpasswords.secret

dbpasswords.secret:
	echo "# Randomly generated on host `hostname`, `date`" > dbpasswords.secret
	chmod go= dbpasswords.secret
	./gendbpasswords >> dbpasswords.secret

htpasswd-jury: dbpasswords.secret
	touch htpasswd-jury
	chmod go= htpasswd-jury
	htpasswd -b htpasswd-jury domjudge_jury `grep '^jury:' dbpasswords.secret | cut -d : -f 5`

install-domserver:
	install -d $(domserver_etcdir)
	install -t $(domserver_etcdir) apache.conf domserver-static.php domserver-config.php common-config.php
	install -m u=rw,go= dbpasswords.secret htpasswd-jury $(domserver_etcdir)

install-judgehost:
	install -d $(judgehost_etcdir)
	install -t $(judgehost_etcdir) judgehost-static.php judgehost-config.php common-config.php
	install -m u=rw,go= dbpasswords.secret $(judgehost_etcdir)

permissions-domserver:
	ensurepermission group $(webservergroup) $(domserver_etcdir)/dbpasswords.secret
	ensurepermission perm g=r,o=             $(domserver_etcdir)/dbpasswords.secret

.PHONY: config
