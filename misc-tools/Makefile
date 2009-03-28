ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = checkinput checktestdata

build: $(TARGETS)

checktestdata: checktestdata.cpp -lboost_regex

install:

clean-l:
	-rm -f $(TARGETS)
