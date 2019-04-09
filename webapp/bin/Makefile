ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

SUBST_FILES = console

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

domserver: $(SUBST_FILES)

distclean-l:
	-rm -f $(SUBST_FILES)

.PHONY: params-from-stub static-from-stub
