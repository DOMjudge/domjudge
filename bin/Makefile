ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = runguard

SUBST_FILES = save_sources2file restore_sources2db static_scoreboard \
              runssh_judgehosts runssh_teams balloons

config: $(SUBST_FILES)

build: $(TARGETS)

runguard: runguard.c $(TOPDIR)/etc/config.h $(TOPDIR)/etc/runguard-config.h -lm

$(SUBST_FILES): %: %.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

judgehost: build

install-domserver:
	$(INSTALL_SCRIPT) -t $(domserver_libdir) alert

install-judgehost:
	$(INSTALL_PROG) -t $(judgehost_libjudgedir) sh-static
	$(INSTALL_SCRIPT) -t $(judgehost_libdir) alert

install-perm-judgehost:
	$(INSTALL_PROG) -o root -m u=rwxs,go=rx -t $(judgehost_bindir) runguard

clean-l:
	rm -f $(TARGETS)

distclean-l:
	-rm -f save_sources2file restore_sources2db
