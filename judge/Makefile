ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = check_float compare runguard

judgehost: build

config: judgedaemon

build: $(TARGETS)

judgedaemon: judgedaemon.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

compare: $(LIBFILES)

check_float: -lm

runguard: runguard.c $(TOPDIR)/etc/config.h $(TOPDIR)/etc/runguard-config.h -lm

install-judgehost:
	$(INSTALL_SCRIPT) -t $(judgehost_libjudgedir) \
		compile_*.sh test_solution.sh run compare_program.sh \
		compare check_diff.sh check_float chroot-startstop.sh
	$(INSTALL_DATA)   -t $(judgehost_libjudgedir) judgedaemon.main.php \
		parse_result.xslt
	$(INSTALL_SCRIPT) -t $(judgehost_bindir) judgedaemon
	$(INSTALL_PROG) -t $(judgehost_libjudgedir) sh-static
	-$(INSTALL_PROG) -o root -m u=rwxs,go=rx -t $(judgehost_bindir) runguard

clean-l:
	-rm -f $(TARGETS)

distclean-l:
	-rm -f judgedaemon
