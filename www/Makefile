ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

configure.php: configure.php.in $(TOPDIR)/paths.mk
	$(substconfigvars)

domserver: configure.php

install-domserver:
	for d in team ; do \
		$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)/$$d $$d/*.php ; \
	done
	$(INSTALL_DATA) -t $(DESTDIR)$(domserver_wwwdir)              *.php

distclean-l:
	-rm -f configure.php
