ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

EXTENSIONS = h sh php tex
CONFIGS    = $(addprefix config.,$(EXTENSIONS))

default: config

config: $(CONFIGS)

$(CONFIGS): config.%: config.template.% global.cfg generate_config.sh
	./generate_config.sh $*

check:
	./check_config

clean:

.PHONY: config
