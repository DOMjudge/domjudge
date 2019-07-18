ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

SUBST_FILES = autoload.php static.yaml

TARGETS =
OBJECTS =

build: $(TARGETS)

$(SUBST_FILES): %: %.in $(TOPDIR)/paths.mk
	$(substconfigvars)

domserver: $(SUBST_FILES)

distclean-l:
	-rm -f $(SUBST_FILES)

clean-l:
	-rm -f $(TARGETS) $(OBJECTS)
