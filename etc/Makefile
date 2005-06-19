ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

EXTENSIONS = h sh php tex
TEMPLATES  = $(addprefix config.template.,$(EXTENSIONS))
CONFIGS    = $(addprefix config.,$(EXTENSIONS))

TEMPFILEDIR = $(TOPDIR)//bin

PASSWD_FILE = passwords.php

.PHONY: config passwd_perms

config: $(TEMPFILEDIR)/tempfile $(CONFIGS)

$(CONFIGS): config.%: config.template.% global.cfg $(TEMPFILEDIR)/tempfile
	./generate_config.sh $*

config.php: $(PASSWD_FILE)

$(PASSWD_FILE): | passwd_perms

passwd_perms:
	@if [ "`find . -name $(PASSWD_FILE) -perm +0077`" ] ; then \
		echo "WARNING: '$(PASSWD_FILE)' found with group/other permissions!" ; \
		echo "WARNING: Check that permissions are set correctly!" ; \
	fi

$(TEMPFILEDIR)/tempfile:
	$(MAKE) -C $(TEMPFILEDIR) tempfile

clean:
