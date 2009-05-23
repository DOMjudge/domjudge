ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

config: gentexconfig

install-docs:
	$(INSTALL_SCRIPT) -t $(domjudge_docdir)/team gentexconfig genteammanuals
	$(INSTALL_DATA)   -t $(domjudge_docdir)/team team-manual*.tex
	$(INSTALL_DATA)   -T README $(domjudge_docdir)/README.teammanual

gentexconfig: gentexconfig.in ../../paths.mk
	$(substconfigvars)
	chmod a+x $@

dist:

distclean-l:
	-rm -f gentexconfig
