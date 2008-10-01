ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

submit=$(TOPDIR)/submit/submit -q -w

check: test-normal test-fltcmp

test-normal:
	@echo "Submitting normal test sources..." ; \
	for i in test-* ; do \
		echo "Submitting '$$i'" ; \
		$(submit) -p hello "$$i" ; \
		sleep 1 ; \
	done

test-fltcmp:
	@echo "Submitting fltcmp test sources..." ; \
		for i in fltcmp-test-* ; do \
		echo "Submitting '$$i'" ; \
		$(submit) -p fltcmp "$$i" ; \
		sleep 1 ; \
	done

test-stress:
	@echo "Submitting stress test sources..." ; \
	for i in stress-test-* ; do \
		echo "Submitting '$$i'" ; \
		$(submit) -p hello "$$i" ; \
		sleep 1 ; \
	done

.PHONY: test-normal test-fltcmp test-stress
