ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = check_float compare judgedaemon

judgehost: build

build: $(TARGETS)

judgedaemon: judgedaemon.in ../paths.mk
	cat $< | \
	sed -e "s|@configure_input@|Generated from '$<', do not edit by hand.|g" \
	    -e "s,@judgehost_etcdir@,$(judgehost_etcdir),g" > $@
	chmod a+x $@

compare: $(LIBFILES)

check_float: CFLAGS += -std=c99

install-judgehost:
	cp -P -t $(judgehost_libjudgedir) \
		compile_*.sh parse_result.xslt test_solution.sh run \
		compare check_diff.sh check_float chroot-startstop.sh \
		judgedaemon.main.php
	install -t $(judgehost_bindir) judgedaemon

clean-l:
	-rm -f $(TARGETS)
