ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = runguard save_sources2file restore_sources2db

build: $(TARGETS)

runguard: $(TOPDIR)/etc/config.h $(TOPDIR)/etc/runguard-config.h

save_sources2file restore_sources2db: %: %.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

judgehost: build

install-judgehost:
	install -t $(judgehost_libjudgedir) sh-static
#	install -o root -m u=rwxs,go=rx -t $(judgehost_bindir) runguard
	install -t $(judgehost_bindir) runguard

clean-l:
	rm -f $(TARGETS)
