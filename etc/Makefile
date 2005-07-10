ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

EXTENSIONS = h sh php tex
TEMPLATES  = $(addprefix config.template.,$(EXTENSIONS))
CONFIGS    = $(addprefix config.,$(EXTENSIONS))

.PHONY: config

config: $(TEMPFILEDIR)/tempfile $(CONFIGS)

$(CONFIGS): config.%: config.template.% global.cfg $(TEMPFILEDIR)/tempfile
	./generate_config.sh $*

clean:
