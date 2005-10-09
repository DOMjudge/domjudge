ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

check:
	for i in test-* ; do \
		echo "Submitting '$$i'" ; \
		$(TOPDIR)/submit/submit -q -p hello "$$i" ; \
		sleep 1 ; \
	done
