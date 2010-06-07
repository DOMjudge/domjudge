ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

SUBMITCMD = $(TOPDIR)/submit/submit

SUBMIT = \
	echo -n "Submitting '$$i' " ; \
	$(SUBMITCMD) -q -w1 -p $(PROBLEM) "$$i" ; \
	echo ; sleep 1

config: check-judgings

build: $(TARGETS)

check-judgings: check-judgings.in $(TOPDIR)/paths.mk
	$(substconfigvars)
	chmod a+x $@

check-syntax:
	./syntax

check: test-normal test-fltcmp

verify: check-judgings
	./check-judgings

distclean-l:
	-rm -f check-judgings

PROBLEM=hello
test-fltcmp: PROBLEM=fltcmp

test-normal: $(SUBMITCMD)
	@echo "Submitting normal test sources..." ; \
	for i in test-* ; do $(SUBMIT) ; done

test-fltcmp: $(SUBMITCMD)
	@echo "Submitting fltcmp test sources..." ; \
	for i in fltcmp-test-* ; do $(SUBMIT) ; done

test-stress: $(SUBMITCMD)
	@echo "Submitting stress test sources..." ; \
	for i in stress-test-* ; do $(SUBMIT) ;	done

$(SUBMITCMD):
	$(MAKE) -C $(TOPDIR)/submit submit

.PHONY: test-normal test-fltcmp test-stress verify
