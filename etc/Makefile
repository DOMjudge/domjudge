-include Makefile.global

EXTENSIONS = h sh php tex
TEMPLATES  = $(addprefix config.template.,$(EXTENSIONS))
CONFIGS    = $(addprefix config.,$(EXTENSIONS))

TEMPFILEDIR = ../bin

all: config

config: $(CONFIGS)
	@if [ "`find . -name passwords.php -perm +0077`" ] ; then \
		echo "WARNING: 'passwords.php' found with wrong permissions!" ; \
		echo "Unsetting group/other permissions for 'passwords.php'." ; \
		chmod go= passwords.php ; \
	fi

$(CONFIGS): config.%: config.template.% global.cfg $(TEMPFILEDIR)/tempfile
	./generate_config.sh $*

config.php: passwords.php

$(TEMPFILEDIR)/tempfile:
	$(MAKE) -C $(TEMPFILEDIR) tempfile

clean:
	-rm -f $(CONFIGS)
