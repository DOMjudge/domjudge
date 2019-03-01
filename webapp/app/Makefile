ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

TARGETS =
OBJECTS =

SUBST_DOMSERVER = autoload.php

SUBST_FILES = $(SUBST_DOMSERVER)

build: $(TARGETS) $(SUBST_FILES)

domserver: $(SUBST_DOMSERVER)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

clean-l:
	-rm -f $(TARGETS) $(OBJECTS)

distclean-l:
	-rm -f $(SUBST_FILES)
