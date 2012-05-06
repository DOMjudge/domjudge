ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

TARGETS = $(SUBMIT_PROGS)

SUBST_CONFIGS = import.php

config: $(SUBST_CONFIGS)

build:

domserver: import.php

$(SUBST_CONFIGS): %: %.in ../paths.mk
	$(substconfigvars)
	chmod a+x $@

install-domserver:
	$(INSTALL_PROG) -t $(DESTDIR)$(domserver_libsubmitdir) \
		import.php

distclean-l:
	-rm -f import.php
