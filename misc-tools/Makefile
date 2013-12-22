ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

CXXFLAGS += -std=c++11

TARGETS = checkinput
OBJECTS =

PARSER_GEN = lex.cc scannerbase.h parse.cc parserbase.h

ifeq ($(CHECKTESTDATA_ENABLED),yes)
TARGETS += checktestdata
CHKOBJS = $(addsuffix $(OBJEXT),libchecktestdata parse lex parsetype)
OBJECTS += $(CHKOBJS)
endif

SUBST_FILES = save_sources2file restore_sources2db static_scoreboard \
              combined_scoreboard runssh_judgehosts runssh_teams balloons \
              simulate_contest create_cgroups

config: $(SUBST_FILES)

build: $(TARGETS)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)
	chmod a+x $@

lex.cc scannerbase.h: checktestdata.l scanner.h scanner.ih
	flexc++ $<

parse.cc parserbase.h: checktestdata.y parser.h parser.ih parsetype.h
	bisonc++ $<

checksucc = ./checktestdata $$opts $$prog $$data >/dev/null 2>&1 || \
		{ echo "Running './checktestdata $$opts $$prog $$data' did not succeed..." ; exit 1; }
checkfail = ./checktestdata $$opts $$prog $$data >/dev/null 2>&1 && \
		{ echo "Running './checktestdata $$opts $$prog $$data' did not fail..."    ; exit 1; }

ifeq ($(CHECKTESTDATA_ENABLED),yes)

libchecktestdata.o: %.o: %.cc %.h $(PARSER_GEN)

checktestdata: CPPFLAGS += $(BOOST_CPPFLAGS)
checktestdata: LDFLAGS  += $(BOOST_LDFLAGS) -Wl,-Bstatic $(LIBGMPXX) $(BOOST_REGEX_LIB) -Wl,-Bdynamic
checktestdata: LDFLAGS := $(filter-out -pie,$(LDFLAGS))
checktestdata: checktestdata.cc $(CHKOBJS)
	$(CXX) $(CXXFLAGS) -o $@ $^ $(LDFLAGS)

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
# Test if generating testdata works and complies with the script:
	@TMP=`mktemp --tmpdir dj_gendata.XXXXXX` || exit 1 ; data=$$TMP ; \
	for i in tests/testprog*.in ; do \
		grep 'IGNORE GENERATE TESTING' $$i >/dev/null && continue ; \
		n=$${i#tests/testprog} ; n=$${n%.in} ; \
		prog=$$i ; \
		for i in seq 10 ; do opts=-g ; $(checksucc) ; opts='' ; $(checksucc) ; done ; \
	done ; \
	rm -f $$TMP

else

checktestdata:
	@echo "checktestdata was disabled by configure!"
	@exit 1

endif # CHECKTESTDATA_ENABLED

install-domserver:
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_bindir) balloons

install-judgehost:
	$(INSTALL_PROG) -t $(DESTDIR)$(judgehost_bindir) dj_make_*chroot

install-docs:
ifeq ($(CHECKTESTDATA_ENABLED),yes)
	$(INSTALL_DATA) -t $(DESTDIR)$(domjudge_docdir)/examples \
		checktestdata.fltcmp checktestdata.hello
endif

dist-l: $(PARSER_GEN)

clean-l:
	-rm -f $(TARGETS) $(OBJECTS)

distclean-l:
	-rm -f $(SUBST_FILES)

maintainer-clean-l:
	-rm -f $(PARSER_GEN)
