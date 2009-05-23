ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

SUBMIT = \
	echo -n "Submitting '$$i' " ; \
	$(TOPDIR)/submit/submit -q -p $(PROBLEM) "$$i" ; \
	echo ; sleep 1

config: check-judgings

build: $(TARGETS)

check-judgings: check-judgings.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

check: test-normal test-fltcmp

verify: check-judgings
	./check-judgings

distclean-l:
	-rm -f check-judgings

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
