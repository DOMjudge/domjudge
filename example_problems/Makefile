ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

install-domserver:
	find . -type d -exec $(INSTALL_DIR) -m 0775 -o $(DOMJUDGE_USER) -g $(WEBSERVER_GROUP) -d "{}" $(DESTDIR)$(domserver_exampleprobdir)/{} \;
	find -L . -type f -exec $(INSTALL_DATA) -D "{}" $(DESTDIR)$(domserver_exampleprobdir)/{} \;
	$(INSTALL_PROG) generate-contest-yaml $(DESTDIR)$(domserver_exampleprobdir)/generate-contest-yaml

create-python-from-bash:
	for fn in ./*/submissions/*/*.sh ; do \
		new_fn="$${fn}.py23" ; \
		echo "import os" > $$new_fn ; \
		echo "command='''" >> $$new_fn ; \
		cat $$fn >> $$new_fn ; \
		echo "'''" >> $$new_fn ; \
		echo "os.system(command)" >> $$new_fn ; \
	done

.PHONY: install-domserver
