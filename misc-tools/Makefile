ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = checkinput checktestdata

SUBST_FILES = save_sources2file restore_sources2db static_scoreboard \
              runssh_judgehosts runssh_teams balloons

config: $(SUBST_FILES)

build: $(TARGETS)

$(SUBST_FILES): %: %.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

checktestdata: checktestdata.cpp yylex.cc parse.cc -lboost_regex

yylex.cc: checktestdata.l
	flex++ $<

parse.cc: checktestdata.y
	bisonc++ $<

checksucc = ./checktestdata $$prog $$data >/dev/null 2>&1 || \
		{ echo "Checking '$$prog' '$$data' did not succeed..." ; exit 1; }
checkfail = ./checktestdata $$prog $$data >/dev/null 2>&1 && \
		{ echo "Checking '$$prog' '$$data' did not fail..."    ; exit 1; }

check: checktestdata
	@for i in tests/testprog*.in ; do \
		n=$${i#tests/testprog} ; n=$${n%.in} ; \
		prog=$$i ; \
		for data in tests/testdata$$n.in*  ; do $(checksucc) ; done ; \
		for data in tests/testdata$$n.err* ; do $(checkfail) ; done ; \
		data=tests/testdata$$n.in ; \
		for prog in tests/testprog$$n.err* ; do $(checkfail) ; done ; \
	done || true


install-judgehost:
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) dj_make_chroot

dist-l: yylex.cc parse.cc

clean-l:
	-rm -f $(TARGETS)

distclean-l:
	-rm -f $(SUBST_FILES)

maintainer-clean-l:
	-rm -f parse.cc yylex.cc parser.ih parserbase.h
