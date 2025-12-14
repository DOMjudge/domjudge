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

evict: evict.cc $(LIBOBJECTS)
	$(CXX) $(CXXFLAGS) $(LDFLAGS) -o $@ $< $(LIBOBJECTS)

runguard: runguard.cc $(LIBOBJECTS) $(TOPDIR)/etc/runguard-config.h
	$(CXX) $(CXXFLAGS) $(LDFLAGS) -o $@ $< $(LIBOBJECTS) $(LIBCGROUP)

runpipe: runpipe.cc $(LIBOBJECTS)
	$(CXX) $(CXXFLAGS) $(LDFLAGS) -static -o $@ $< $(LIBOBJECTS)

install-judgehost:
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_libjudgedir) \
		compile.sh build_executable.sh chroot-startstop.sh \
		check_diff.sh evict version_check.sh
	$(INSTALL_DATA) -t $(DESTDIR)$(judgehost_libjudgedir) \
		judgedaemon.main.php run-interactive.sh
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) \
		judgedaemon runguard runpipe create_cgroups

clean-l:
	-rm -f $(TARGETS) $(TARGETS:%=%$(OBJEXT))

distclean-l:
	-rm -f $(SUBST_FILES)
