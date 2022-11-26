ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = runguard runpipe evict

SUBST_FILES = judgedaemon chroot-startstop.sh create_cgroups \
              create-cgroups.service domjudge-judgedaemon@.service

judgehost: $(TARGETS) $(SUBST_FILES)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

runguard: LDFLAGS := $(filter-out -pie,$(LDFLAGS))

runguard: -lm $(LIBCGROUP)
runguard: CFLAGS += -std=c99
runguard$(OBJEXT): $(TOPDIR)/etc/runguard-config.h

evict: evict.c $(LIBHEADERS) $(LIBSOURCES)
	$(CC) $(CFLAGS) -o $@ $< $(LIBSOURCES)

runpipe: runpipe.cc $(LIBHEADERS) $(LIBSOURCES)
	$(CXX) $(CXXFLAGS) -static -o $@ $< $(LIBSOURCES)

install-judgehost:
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libjudgedir) \
		compile.sh build_executable.sh testcase_run.sh chroot-startstop.sh \
		check_diff.sh evict
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_libjudgedir) \
		judgedaemon.main.php run-interactive.sh
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) \
		judgedaemon runguard runpipe create_cgroups
ifneq ($(systemd_unitdir),)
	$(INSTALL_DIR)     $(DESTDIR)$(systemd_unitdir)
	$(INSTALL_DATA) -t $(DESTDIR)$(systemd_unitdir) \
		create-cgroups.service domjudge-judgehost.target domjudge-judgedaemon@.service
endif

clean-l:
	-rm -f $(TARGETS) $(TARGETS:%=%$(OBJEXT))

distclean-l:
	-rm -f $(SUBST_FILES)
