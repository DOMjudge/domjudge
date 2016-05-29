ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = runguard runpipe evict

SUBST_FILES = judgedaemon chroot-startstop.sh

judgehost: $(TARGETS) $(SUBST_FILES)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

runguard: LDFLAGS := $(filter-out -pie,$(LDFLAGS))

runguard: -lm $(LIBCGROUP)
runguard: CFLAGS += -std=c99
runguard$(OBJEXT): $(TOPDIR)/etc/runguard-config.h

evict: evict.c $(LIBHEADERS) $(LIBSOURCES)
	$(CC) $(CFLAGS) -o $@ $< $(LIBSOURCES)

# FIXME: compile with diet libc to produce a static binary which is
# not 0.6 MB (!) in size?
runpipe: runpipe.c $(LIBHEADERS) $(LIBSOURCES)
	$(CC) $(CFLAGS) -static -o $@ $< $(LIBSOURCES)

install-judgehost:
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libjudgedir) \
		compile*.sh testcase_run.sh chroot-startstop.sh \
		check_diff.sh sh-static evict
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_libjudgedir) \
		judgedaemon.main.php
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) judgedaemon
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) runguard runpipe

clean-l:
	-rm -f $(TARGETS) $(TARGETS:%=%$(OBJEXT))

distclean-l:
	-rm -f $(SUBST_FILES)
