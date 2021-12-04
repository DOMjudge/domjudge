ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

SUBMITCMD = $(TOPDIR)/submit/submit
SUBMITOPTS = $(subst 1,-q,$(QUIET)) -y -c demo -p $(PROBLEM)

SUBMIT = \
	echo -n "Submitting '$$i' " ; \
	if [ "$${i%.zip}" != "$$i" ]; then \
		tmp=`mktemp -d --tmpdir` ; \
		unzip -jnq -d "$$tmp" "$$i" ; \
		main=`grep -l '@EXPECTED_RESULTS@' "$$tmp/"*` ; \
		$(SUBMITCMD) $(SUBMITOPTS) "$$main" "$$tmp/"* ; \
		rm -rf "$$tmp" ; \
	else \
		$(SUBMITCMD) $(SUBMITOPTS) "$$i" ; \
	fi ; \
	echo

# Copy of SUBMIT, but checks that the submit command fails.
# Does not do main file detection since there may be multiple matches.
# Always uses the lexicographically first file.
BAD_SUBMIT = \
	echo "Submitting '$$i' " ; \
	if [ "$${i%.zip}" != "$$i" ]; then \
		tmp=`mktemp -d --tmpdir` ; \
		unzip -jnq -d "$$tmp" "$$i" ; \
		if $(SUBMITCMD) $(SUBMITOPTS) "$$tmp/"* ; then \
			echo $(SUBMITCMD) $(SUBMITOPTS) "$$tmp/*": Succeeded but expected failure. ; \
			exit 1 ; \
		fi ; \
		rm -rf "$$tmp" ; \
	else \
		if $(SUBMITCMD) $(SUBMITOPTS) "$$i" ; then \
			echo $(SUBMITCMD) $(SUBMITOPTS) "$$tmp/*": Succeeded but expected failure. ; \
			exit 1 ; \
		fi ; \
	fi ; \
	echo

SUBST_FILES = scripted_submit

build: $(SUBST_FILES)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

check-syntax:
	$(TOPDIR)/tests/syntax

check-problems: test-normal test-fltcmp test-boolfind

check: check-problems test-bad-expected-results

PROBLEM=hello
test-fltcmp: PROBLEM=fltcmp
test-boolfind: PROBLEM=boolfind

test-normal: $(SUBMITCMD)
	@echo "Submitting normal test sources..." ; \
	for i in test-* ; do $(SUBMIT) ; done

test-fltcmp: $(SUBMITCMD)
	@echo "Submitting fltcmp test sources..." ; \
	for i in fltcmp-test-* ; do $(SUBMIT) ; done

test-boolfind: $(SUBMITCMD)
	@echo "Submitting boolfind test sources..." ; \
	for i in boolfind-test-* ; do $(SUBMIT) ; done

test-bad-expected-results: $(SUBMITCMD)
	echo "Submitting bad expected result sources..." ; \
	for i in bad-expected-result-* ; do $(BAD_SUBMIT) ; done

test-stress: filename-tests.tar.gz $(SUBMITCMD)
	tar xzf $<
	@echo "Submitting stress test sources..." ; \
	for i in stress-test-* ; do $(SUBMIT) ;	done

$(SUBMITCMD):
	$(MAKE) -C $(TOPDIR)/submit submit

clean-l:
	rm -f test-file\ name*

distclean-l:
	-rm -f $(SUBST_FILES)

.PHONY: check check-problems check-syntax test-normal test-fltcmp test-stress test-bad-expected-results
