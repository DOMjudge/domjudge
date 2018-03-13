ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

SUBMITCMD = $(TOPDIR)/submit/submit
SUBMITOPTS = -q -c demo -p $(PROBLEM)

SUBMIT = \
	echo -n "Submitting '$$i' " ; \
	if [ "$${i%.zip}" != "$$i" ]; then \
		tmp=`mktemp -d --tmpdir` ; \
		unzip -jnq -d "$$tmp" "$$i" ; \
		main=`grep -l '@EXPECTED_RESULTS@' "$$tmp/"*` ; \
		if [ "$${i%test-multifile-multipackage.java.zip}" != "$$i" ]; then \
			$(SUBMITCMD) $(SUBMITOPTS) -e zoo.goo.moo.b.B "$$main" "$$tmp/"* ; \
		elif [ "$${i%test-extra-file.java.zip}" != "$$i" ]; then \
			$(SUBMITCMD) $(SUBMITOPTS) -e Main "$$main" "$$tmp/"* ; \
		else \
			$(SUBMITCMD) $(SUBMITOPTS) "$$main" "$$tmp/"* ; \
		fi ; \
		rm -rf "$$tmp" ; \
	else \
		if [ "$${i%.java}" != "$$i" ]; then \
			entry=`grep "class .* {" "$$i" | sed -e 's/.*class //' | sed -e 's/ .*//'`; \
			$(SUBMITCMD) $(SUBMITOPTS) -e "$$entry" "$$i" ; \
		else \
			$(SUBMITCMD) $(SUBMITOPTS) "$$i" ; \
		fi ; \
	fi ; \
	echo

SUBST_FILES = scripted_submit

build: $(SUBST_FILES)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

check-syntax:
	@output=`./syntax` ; \
	if [ -n "$$output" ]; then \
		echo "$$output" ; exit 1 ; \
	fi

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

.PHONY: check check-syntax test-normal test-fltcmp test-stress
