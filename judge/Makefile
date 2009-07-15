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
	$(INSTALL_SCRIPT) -t $(DESTDIR)$(judgehost_libjudgedir) \
		compile_*.sh test_solution.sh run compare_program.sh \
		compare check_diff.sh check_float chroot-startstop.sh
	$(INSTALL_DATA)   -t $(DESTDIR)$(judgehost_libjudgedir) \
		judgedaemon.main.php parse_result.xslt
	$(INSTALL_SCRIPT) -t $(DESTDIR)$(judgehost_bindir) judgedaemon
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libjudgedir) sh-static
	-$(INSTALL_PROG) -o root -m 4755 -t $(DESTDIR)$(judgehost_bindir) \
		runguard

clean-l:
	-rm -f $(TARGETS)

distclean-l:
	-rm -f judgedaemon
