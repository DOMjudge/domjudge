ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

config: gentexconfig

docs: team-manual.pdf

install-docs:
	$(INSTALL_SCRIPT) -t $(DESTDIR)$(domjudge_docdir)/team \
		gentexconfig genteammanuals
	$(INSTALL_DATA)   -t $(DESTDIR)$(domjudge_docdir)/team \
		team-manual*.tex team-manual*.pdf
	$(INSTALL_DATA) -T README $(DESTDIR)$(domjudge_docdir)/README.teammanual

gentexconfig: gentexconfig.in ../../paths.mk
	$(substconfigvars)
	chmod a+x $@

team-manual.pdf: team-manual.tex team-manual-preamble.tex ../../etc/domserver-config.php
	./genteammanuals

dist: team-manual.pdf
	$(MAKE) clean-l

clean-l:
	-rm -f $(addprefix team-manual*.,aux log out)

distclean-l:
	-rm -f gentexconfig

maintainer-clean-l:
	-rm -f team-manual*.pdf
