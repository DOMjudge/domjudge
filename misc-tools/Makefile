ifndef TOPDIR
TOPDIR=..
endif

REC_TARGETS = inplace-install inplace-uninstall

include $(TOPDIR)/Makefile.global

TARGETS =
OBJECTS =

SUBST_DOMSERVER = fix_permissions configure-domjudge import-contest

SUBST_JUDGEHOST = dj_make_chroot dj_run_chroot dj_make_chroot_docker \
                  dj_judgehost_cleanup

SUBST_FILES = $(SUBST_DOMSERVER) $(SUBST_JUDGEHOST)

build: $(TARGETS) $(SUBST_FILES)

domserver: $(SUBST_DOMSERVER)

judgehost: $(SUBST_JUDGEHOST)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

install-domserver:
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_bindir) $(SUBST_DOMSERVER)
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_libdir) dj_utils.py

install-judgehost:
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) $(SUBST_JUDGEHOST)

inplace-install-l:
	for i in $(SUBST_DOMSERVER) ; do \
		ln -sf $(CURDIR)/$$i $(domserver_bindir) ; \
	done
	for i in $(SUBST_JUDGEHOST) ; do \
		ln -sf $(CURDIR)/$$i $(judgehost_bindir) ; \
	done
	ln -sf $(CURDIR)/dj_utils.py $(domserver_libdir)

inplace-uninstall-l:
# bindir gets removed from the main Makefile
	rm -f $(domserver_libdir)/dj_utils.py

clean-l:
	-rm -f $(TARGETS) $(OBJECTS)

distclean-l:
	-rm -f $(SUBST_FILES)
