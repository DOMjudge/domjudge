ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

MANUALSTEX=team-manual.tex $(wildcard team-manual-??.tex)
MANUALSPDF=$(MANUALSTEX:%tex=%pdf)

config: gentexconfig

docs: $(MANUALSPDF)

install-docs:
	$(INSTALL_PROG) -t $(DESTDIR)$(domjudge_docdir)/team \
		gentexconfig genteammanuals
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/team \
		team-manual*.tex team-manual*.pdf
	$(INSTALL_DATA) -T README $(DESTDIR)$(domjudge_docdir)/README.teammanual

gentexconfig: gentexconfig.in $(TOPDIR)/paths.mk
	$(substconfigvars)
	chmod a+x $@

# 'genteammanual' (re-)generates all versions even if only one is not uptodate.
$(MANUALSPDF): %pdf: %tex team-manual-preamble.tex $(TOPDIR)/etc/domserver-config.php
	./genteammanuals

dist-l: $(MANUALSPDF)
	$(MAKE) clean-l

clean-l:
	-rm -f $(addprefix team-manual*.,aux log out)

distclean-l:
	-rm -f gentexconfig

maintainer-clean-l:
	-rm -f $(MANUALSPDF)
