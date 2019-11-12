ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

MANUALSTEX=team-manual.tex $(wildcard team-manual-??.tex)
MANUALSPDF=$(MANUALSTEX:%tex=%pdf)

ifeq ($(BUILD_DOCS),yes)
docs: gentexconfig $(MANUALSPDF)

# For distdocs we cannot generate gentexconfig, since paths.mk is not
# yet available:
distdocs: $(MANUALSPDF)
endif

install-docs:
	$(INSTALL_PROG) -t $(DESTDIR)$(domjudge_docdir)/team \
		gentexconfig genteammanuals
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/team \
		team-manual*.tex team-manual*.pdf
	$(INSTALL_DATA) -T README $(DESTDIR)$(domjudge_docdir)/README.teammanual

gentexconfig: gentexconfig.in $(TOPDIR)/paths.mk
	$(substconfigvars)

# 'genteammanual' (re-)generates all versions even if only one is not uptodate.
$(MANUALSPDF): %pdf: %tex team-manual-preamble.tex $(TOPDIR)/etc/domserver-config.php
	./genteammanuals

# Cleanup LaTeX logs, etc. after generating documentation for distribution.
distdocs-l:
	$(MAKE) clean

clean-l:
	-rm -f $(addprefix team-manual*.,aux log out)
	-rm -rf _minted-team-manual*

distclean-l:
	-rm -f gentexconfig

maintainer-clean-l:
	-rm -f $(MANUALSPDF)

.PHONY: docs distdocs install-docs
