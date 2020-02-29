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

SUBST_FILES = scripted_submit

build: $(SUBST_FILES)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

check-syntax:
	$(TOPDIR)/tests/syntax

check: test-normal test-fltcmp test-boolfind

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
	for i in stress-test-* ; do $(SUBMIT) ;	done

$(SUBMITCMD):
	$(MAKE) -C $(TOPDIR)/submit submit

clean-l:
	rm -f test-file\ name*

distclean-l:
	-rm -f $(SUBST_FILES)

.PHONY: check check-syntax test-normal test-fltcmp test-stress
