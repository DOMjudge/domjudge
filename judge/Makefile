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

evict: evict.cc $(LIBHEADERS) $(LIBSOURCES)
	$(CXX) $(CXXFLAGS) -o $@ $< $(LIBSOURCES)

runguard: runguard.cc $(LIBHEADERS) $(LIBSOURCES) $(TOPDIR)/etc/runguard-config.h
	$(CXX) $(CXXFLAGS) -o $@ $< $(LIBSOURCES) $(LIBCGROUP)

runpipe: runpipe.cc $(LIBHEADERS) $(LIBSOURCES)
	$(CXX) $(CXXFLAGS) -static -o $@ $< $(LIBSOURCES)

install-judgehost:
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libjudgedir) \
		compile.sh build_executable.sh testcase_run.sh chroot-startstop.sh \
		check_diff.sh evict version_check.sh
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_libjudgedir) \
		judgedaemon.main.php run-interactive.sh
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) \
		judgedaemon runguard runpipe create_cgroups

clean-l:
	-rm -f $(TARGETS) $(TARGETS:%=%$(OBJEXT))

distclean-l:
	-rm -f $(SUBST_FILES)
