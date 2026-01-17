ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

install-domserver:
	find . -type d -exec $(INSTALL_DIR) -m 0775 -o $(DOMJUDGE_USER) -g $(WEBSERVER_GROUP) -d "{}" $(DESTDIR)$(domserver_scoringexampleprobdir)/{} \;
	find -L . -type f -exec $(INSTALL_DATA) -D "{}" $(DESTDIR)$(domserver_scoringexampleprobdir)/{} \;
	$(INSTALL_PROG) generate-contest-yaml $(DESTDIR)$(domserver_scoringexampleprobdir)/generate-contest-yaml

.PHONY: install-domserver
