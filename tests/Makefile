ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

SUBMITCMD = $(TOPDIR)/submit/submit

SUBMIT = \
	echo -n "Submitting '$$i' " ; \
	if [ "$${i%.zip}" != "$$i" ]; then \
		tmp=`mktemp -d --tmpdir` ; \
		unzip -jnq -d "$$tmp" "$$i" ; \
		main=`grep -l '@EXPECTED_RESULTS@' "$$tmp/"*` ; \
		$(SUBMITCMD) -q -w1 -p $(PROBLEM) "$$main" "$$tmp/"* ; \
		rm -rf "$$tmp" ; \
	else \
		$(SUBMITCMD) -q -w1 -p $(PROBLEM) "$$i" ; \
	fi ; \
	echo ; sleep 1

build: $(TARGETS)

check-syntax:
	./syntax

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

test-stress: $(SUBMITCMD)
	@echo "Submitting stress test sources..." ; \
	for i in stress-test-* ; do $(SUBMIT) ;	done

$(SUBMITCMD):
	$(MAKE) -C $(TOPDIR)/submit submit

.PHONY: test-normal test-fltcmp test-stress
