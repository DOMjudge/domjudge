ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

check: test-normal test-fltcmp

test-normal:
	@echo "Submitting normal test sources..." ; \
	for i in test-* ; do \
		echo "Submitting '$$i'" ; \
		$(TOPDIR)/submit/submit -q -p hello "$$i" ; \
		sleep 1 ; \
	done

test-fltcmp:
	@echo "Submitting fltcmp test sources..." ; \
		for i in $^ ; do \
		echo "Submitting '$$i'" ; \
		$(TOPDIR)/submit/submit -q -p fltcmp "$$i" ; \
		sleep 1 ; \
	done

test-stress:
	@echo "Submitting stress test sources..." ; \
	for i in $^ ; do \
		echo "Submitting '$$i'" ; \
		$(TOPDIR)/submit/submit -q -p hello "$$i" ; \
		sleep 1 ; \
	done

.PHONY: test-normal test-fltcmp test-stress
