ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

docs: gentexconfig

install-docs:
	cp -a -t $(domjudge_docdir) gentexconfig genteammanuals
	cp -t $(domjudge_docdir) team-manual*.tex
	cp README $(domjudge_docdir)/README.teammanual

gentexconfig: gentexconfig.in ../../paths.mk
	$(substconfigvars)
	chmod a+x $@

dist:

.PHONY:
