ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = check-judgings

SUBMIT = \
	echo -n "Submitting '$$i' " ; \
	$(TOPDIR)/submit/submit -q -p $(PROBLEM) "$$i" ; \
	echo ; sleep 1

build: $(TARGETS)

check-judgings: check-judgings.in Makefile
	cat $< | \
	sed -e "s|@configure_input@|Generated from '$<', do not edit by hand.|g" \
	    -e "s,@domserver_etcdir@,$(domserver_etcdir),g" > $@
	chmod a+x $@

check: test-normal test-fltcmp

verify: check-judgings
	./check-judgings

PROBLEM=hello
test-fltcmp: PROBLEM=fltcmp

test-normal:
	@echo "Submitting normal test sources..." ; \
	for i in test-* ; do $(SUBMIT) ; done

test-fltcmp:
	@echo "Submitting fltcmp test sources..." ; \
	for i in fltcmp-test-* ; do $(SUBMIT) ; done

test-stress:
	@echo "Submitting stress test sources..." ; \
	for i in stress-test-* ; do $(SUBMIT) ;	done

.PHONY: test-normal test-fltcmp test-stress verify
