ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = check_float runguard runpipe

judgehost: build

config: judgedaemon

build: $(TARGETS)

judgedaemon: judgedaemon.in $(TOPDIR)/paths.mk
	$(substconfigvars)
	chmod a+x $@

check_float runguard: LDFLAGS := $(filter-out -pie,$(LDFLAGS))

check_float: -lm $(LIBOBJECTS)

runguard: -lm $(LIBCGROUP)
runguard: CFLAGS += -std=c99
runguard$(OBJEXT): $(TOPDIR)/etc/runguard-config.h

# FIXME: compile with diet libc to produce a static binary which is
# not 0.6 MB (!) in size?
runpipe: runpipe.c $(LIBHEADERS) $(LIBSOURCES)
	$(CC) $(CFLAGS) -static -o $@ $< $(LIBSOURCES)

install-judgehost:
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libjudgedir) \
		compile*.sh testcase_run.sh chroot-startstop.sh \
		run run_wrapper \
		compare_wrapper compare_float \
		check_diff.sh check_float sh-static
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_libjudgedir) \
		judgedaemon.main.php
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) judgedaemon
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) runguard runpipe

clean-l:
	-rm -f $(TARGETS) $(TARGETS:%=%$(OBJEXT))

distclean-l:
	-rm -f judgedaemon
