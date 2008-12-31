ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = check_float compare judgedaemon

LIBSBASE = $(addprefix $(TOPDIR)/lib/,lib.error lib.misc)
LIBHEADERS = $(addsuffix .h,$(LIBSBASE))
LIBOBJECTS = $(addsuffix .o,$(LIBSBASE))

judgehost: build

build: $(TARGETS)

$(LIBOBJECTS):
	$(MAKE) -C $(TOPDIR)/lib $(notdir $@)

judgedaemon: judgedaemon.in ../paths.mk
	cat $< | \
	sed -e "s|@configure_input@|Generated from '$<', do not edit by hand.|g" \
	    -e "s,@judgehost_etcdir@,$(judgehost_etcdir),g" > $@
	chmod a+x $@

compare: $(LIBOBJECTS) $(LIBHEADERS)

check_float: CFLAGS += -std=c99

install-judgehost:
	cp -P -t $(judgehost_libjudgedir) \
		compile_*.sh parse_result.xslt test_solution.sh run \
		compare check_diff.sh check_float chroot-startstop.sh
	install -t $(judgehost_bindir) judgedaemon

clean-l:
	-rm -f $(TARGETS)
