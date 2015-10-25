ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS =
OBJECTS =

SUBST_DOMSERVER = save_sources2file restore_sources2db static_scoreboard \
                  combined_scoreboard judging_statistics balloons \
                  simulate_contest create_accounts

SUBST_FILES = $(SUBST_DOMSERVER) create_accounts

build: $(TARGETS) $(SUBST_FILES)

domserver: $(SUBST_DOMSERVER)

judgehost: create_cgroups

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)
	chmod a+x $@

install-domserver:
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_bindir) $(SUBST_DOMSERVER)

install-judgehost:
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) dj_make_*chroot create_cgroups

clean-l:
	-rm -f $(TARGETS) $(OBJECTS)

distclean-l:
	-rm -f $(SUBST_FILES)
