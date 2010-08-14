ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = checkinput

ifeq ($(CHECKTESTDATA_ENABLED),yes)
TARGETS += checktestdata
endif

SUBST_FILES = save_sources2file restore_sources2db static_scoreboard \
              combined_scoreboard runssh_judgehosts runssh_teams balloons

config: $(SUBST_FILES)

build: $(TARGETS)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)
	chmod a+x $@

yylex.cc: checktestdata.l parsetype.h
	flex++ $<
# Fix strict ANSI C++ bug in generated code, see Debian bug #488274
	sed -i '/ isatty/d' $@

parse.cc: checktestdata.y parsetype.h
	bisonc++ $<

checksucc = ./checktestdata $$opts $$prog $$data >/dev/null 2>&1 || \
		{ echo "Running './checktestdata $$opts $$prog $$data' did not succeed..." ; exit 1; }
checkfail = ./checktestdata $$opts $$prog $$data >/dev/null 2>&1 && \
		{ echo "Running './checktestdata $$opts $$prog $$data' did not fail..."    ; exit 1; }

ifeq ($(CHECKTESTDATA_ENABLED),yes)

checktestdata: CPPFLAGS += $(BOOST_CPPFLAGS)
checktestdata: LDFLAGS  += $(BOOST_LDFLAGS)
checktestdata: checktestdata.cpp yylex.cc parse.cc $(LIBGMPXX) $(BOOST_REGEX_LIB)

check: checktestdata
	@for i in tests/testprog*.in ; do \
		n=$${i#tests/testprog} ; n=$${n%.in} ; \
		prog=$$i ; \
		for data in tests/testdata$$n.in*  ; do $(checksucc) ; done ; \
		for data in tests/testdata$$n.err* ; do $(checkfail) ; done ; \
		data=tests/testdata$$n.in ; \
		for prog in tests/testprog$$n.err* ; do $(checkfail) ; done ; \
	done || true
# Some additional tests with --whitespace-ok option enabled:
	@opts=-w ; \
	for i in tests/testwsprog*.in ; do \
		n=$${i#tests/testwsprog} ; n=$${n%.in} ; \
		prog=$$i ; \
		for data in tests/testwsdata$$n.in*  ; do $(checksucc) ; done ; \
		for data in tests/testwsdata$$n.err* ; do $(checkfail) ; done ; \
		data=tests/testwsdata$$n.in ; \
		for prog in tests/testwsprog$$n.err* ; do $(checkfail) ; done ; \
	done || true

else

checktestdata:
	@echo "checktestdata was disabled by configure!"
	@exit 1

endif # CHECKTESTDATA_ENABLED

install-judgehost:
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) dj_make_chroot

dist-l: yylex.cc parse.cc

clean-l:
	-rm -f $(TARGETS)

distclean-l:
	-rm -f $(SUBST_FILES)

maintainer-clean-l:
	-rm -f parse.cc yylex.cc parser.ih parserbase.h
