ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS =

check:
	bats submit_standalone.bats

check-full:
	bats submit_standalone.bats submit_online.bats

.PHONY: check check-full
