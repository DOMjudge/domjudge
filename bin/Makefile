CFLAGS=-O2 -Wall
TARGETS=runguard beep mkstemps tempfile
DOMAIN=`dnsdomainname`
CSINSTALLDIR=/private/nkpjury/bin

all: $(TARGETS) install-setuid

.PHONY: clean install-setuid

install-setuid: runguard beep
	case $(DOMAIN) in \
	'localdomain') \
		su root 'chown root.root runguard beep && chmod u+s runguard beep' || exit 1 ;; \
	'students.cs.uu.nl') \
		mkdir -p $(CSINSTALLDIR) ; \
		mv -f runguard beep $(CSINSTALLDIR) ; \
		rm -f runguard beep ; \
		sudo -u root /bin/bash -c 'cd $(CSINSTALLDIR) && chown root.root runguard beep && chmod u+s runguard beep' || exit 1 ; \
		ln -s $(CSINSTALLDIR)/runguard . ; \
		ln -s $(CSINSTALLDIR)/beep . ;; \
	*) \
		echo "Don't know how to install setuid in domain '$(DOMAIN)'" ; exit 1 ;; \
	esac

clean:
	-rm -f $(TARGETS) $(addsuffix .o,$(TARGETS))
