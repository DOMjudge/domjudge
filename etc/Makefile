all: config

-include $(TOPDIR)/Makefile.global

EXTENSIONS = h sh php tex
TEMPLATES  = $(addprefix config.template.,$(EXTENSIONS))
CONFIGS    = $(addprefix config.,$(EXTENSIONS))

TEMPFILEDIR = ../bin

PASSWD_FILE = passwords.php

config: $(TEMPFILEDIR)/tempfile $(CONFIGS)

$(CONFIGS): config.%: config.template.% global.cfg $(TEMPFILEDIR)/tempfile
	./generate_config.sh $*

config.php: $(PASSWD_FILE)

$(PASSWD_FILE):
	@if [ "`find . -name $(PASSWD_FILE) -perm +0077`" ] ; then \
		echo "WARNING: '$(PASSWD_FILE)' found with wrong permissions!" ; \
		echo "Unsetting group/other permissions for '$(PASSWD_FILE)'." ; \
		chmod go= $(PASSWD_FILE) ; \
	fi

$(TEMPFILEDIR)/tempfile:
	$(MAKE) -C $(TEMPFILEDIR) tempfile

clean:
	-rm -f $(CONFIGS)
