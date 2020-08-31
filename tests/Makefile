ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

SUBMITCMD = $(TOPDIR)/submit/submit
SUBMITOPTS = -y -c demo -p $(PROBLEM)

SUBMIT = \
	echo -n "Submitting '$$i' " ; \
	if [ "$${i%.zip}" != "$$i" ]; then \
		tmp=`mktemp -d --tmpdir` ; \
		unzip -jnq -d "$$tmp" "$$i" ; \
		main=`grep -l '@EXPECTED_RESULTS@' "$$tmp/"*` ; \
		if $(SUBMITCMD) $(SUBMITOPTS) "$$main" "$$tmp/"* ; then \
			echo $(SUBMITCMD) $(SUBMITOPTS) "$$main" "$$tmp/": Succeeded as expected ; \
		else ; \
			echo $(SUBMITCMD) $(SUBMITOPTS) "$$main" "$$tmp/": Failed ; \
		fi ; \
		rm -rf "$$tmp" ; \
	else \
		if $(SUBMITCMD) $(SUBMITOPTS) "$$i" ; then \
			echo $(SUBMITCMD) $(SUBMITOPTS) "$$main" "$$tmp/": Succeeded as expected ; \
		else ; \
			echo $(SUBMITCMD) $(SUBMITOPTS) "$$main" "$$tmp/": Failed ; \
		fi ; \
	fi ; \
	echo

# Copy of SUBMIT, but checks that the submit command fails.
BAD_SUBMIT = \
	echo "Submitting '$$i' " ; \
	if [ "$${i%.zip}" != "$$i" ]; then \
		tmp=`mktemp -d --tmpdir` ; \
		unzip -jnq -d "$$tmp" "$$i" ; \
		main=`grep -l '@EXPECTED_RESULTS@' "$$tmp/"* | head -1` ; \
		echo $$main ; \
		if $(SUBMITCMD) $(SUBMITOPTS) "$$main" "$$tmp/"* ; then \
			echo $(SUBMITCMD) $(SUBMITOPTS) "$$main" "$$tmp/": Succeeded but expected failure. ; \
			exit 1 ; \
		else ; \
			echo $(SUBMITCMD) $(SUBMITOPTS) "$$main" "$$tmp/": Failed as expected ; \
		fi ; \
		rm -rf "$$tmp" ; \
	else \
		if $(SUBMITCMD) $(SUBMITOPTS) "$$i" ; then \
			exit 1; \
		else ; \
			echo $(SUBMITCMD) $(SUBMITOPTS) "$$main" "$$tmp/": Failed as expected ; \
		fi ; \
	fi ; \
	echo

SUBST_FILES = scripted_submit

build: $(SUBST_FILES)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

check-syntax:
	$(TOPDIR)/tests/syntax

check: test-normal test-fltcmp test-boolfind test-bad-expected-results

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

test-stress: filename-tests.tar.gz $(SUBMITCMD)
	tar xzf $<
	@echo "Submitting stress test sources..." ; \
	
test-bad-expected-results: 
	echo "Submitting bad expected result sources..." ; \
	for i in bad-expected-result-* ; do $(BAD_SUBMIT) ; done

$(SUBMITCMD):
	$(MAKE) -C $(TOPDIR)/submit submit

clean-l:
	rm -f test-file\ name*

distclean-l:
	-rm -f $(SUBST_FILES)

.PHONY: check check-syntax test-normal test-fltcmp test-stress test-bad-expected-results
