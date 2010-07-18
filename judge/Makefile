ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = check_float check_boolfind compare runguard runpipe

judgehost: build

config: judgedaemon

build: $(TARGETS)

judgedaemon: judgedaemon.in $(TOPDIR)/paths.mk
	$(substconfigvars)
	chmod a+x $@

compare: $(LIBFILES)

check_float: -lm

runguard: runguard.c $(TOPDIR)/etc/config.h $(TOPDIR)/etc/runguard-config.h -lm

# FIXME: compile with diet libc to produce a static binary which is
# not 0.6 MB (!) in size?
runpipe: runpipe.c $(TOPDIR)/etc/config.h $(LIBHEADERS) $(LIBSOURCES)
	$(CC) $(CFLAGS) -static -o $@ $< $(LIBSOURCES)

runjury_boolfind: runjury_boolfind.c
	$(CC) $(CFLAGS) -static -o $@ $<

install-judgehost:
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libjudgedir) \
		compile*.sh testcase_run.sh chroot-startstop.sh \
		run run_wrapper run_boolfind runjury_boolfind \
		compare compare_wrapper compare_float compare_boolfind \
		check_diff.sh check_float check_boolfind sh-static
	-$(INSTALL_PROG) -o root -m 4755 -t $(DESTDIR)$(judgehost_bindir) \
		runguard

clean-l:
	-rm -f $(TARGETS)

distclean-l:
	-rm -f judgedaemon
