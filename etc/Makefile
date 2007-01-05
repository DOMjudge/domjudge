ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

EXTENSIONS = h sh php tex
TEMPLATES  = $(addprefix config.template.,$(EXTENSIONS))
CONFIGS    = $(addprefix config.,$(EXTENSIONS))

.PHONY: config

config: $(CONFIGS)

$(CONFIGS): config.%: config.template.% global.cfg generate_config.sh $(TEMPFILE)
	./generate_config.sh $*

check:
	./check_config.php

clean:
