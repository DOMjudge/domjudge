ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = check_float compare

judgehost: build

config: judgedaemon

build: $(TARGETS)

judgedaemon: judgedaemon.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

compare: $(LIBFILES)

check_float: CFLAGS += -std=c99

install-judgehost:
	$(INSTALL_SCRIPT) -t $(judgehost_libjudgedir) \
		compile_*.sh test_solution.sh run \
		compare check_diff.sh check_float chroot-startstop.sh \
		judgedaemon.main.php
	$(INSTALL_DATA)   -t $(judgehost_libjudgedir) parse_result.xslt
	$(INSTALL_SCRIPT) -t $(judgehost_bindir) judgedaemon

clean-l:
	-rm -f $(TARGETS)

distclean-l:
	-rm -f judgedaemon
