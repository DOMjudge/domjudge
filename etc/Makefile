ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

EXTENSIONS = h sh php tex
CONFIGS    = $(addprefix config.,$(EXTENSIONS))

default: config

config: $(CONFIGS) apache.conf

$(CONFIGS): config.%: config.template.% global.cfg generate_config.sh
	./generate_config.sh $*

apache.conf: apache.template.conf global.cfg config.sh generate_apacheconf.sh
	./generate_apacheconf.sh

check:
	./check_config

clean:

.PHONY: config
