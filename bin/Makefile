ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = runguard

config: save_sources2file restore_sources2db

build: $(TARGETS)

runguard: runguard.c $(TOPDIR)/etc/config.h $(TOPDIR)/etc/runguard-config.h
	$(CC) $(CFLAGS) $< -o $@

save_sources2file restore_sources2db: %: %.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

judgehost: build

install-judgehost:
	$(INSTALL_PROG) -t $(judgehost_libjudgedir) sh-static

install-perm-judgehost:
	$(INSTALL_PROG) -o root -m u=rwxs,go=rx -t $(judgehost_bindir) runguard

clean-l:
	rm -f $(TARGETS)

distclean-l:
	-rm -f save_sources2file restore_sources2db
