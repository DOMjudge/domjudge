ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

build: $(SUBST_FILES)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

check-syntax:
	$(TOPDIR)/tests/syntax

clean-l:
	rm -f test-file\ name*

distclean-l:
	-rm -f $(SUBST_FILES)

.PHONY: check-syntax
