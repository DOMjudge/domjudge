EXTENSIONS = h sh php tex
TEMPLATES  = $(addprefix config.template.,$(EXTENSIONS))
CONFIGS    = $(addprefix config.,$(EXTENSIONS))

TEMPFILEDIR = ../bin

all: config

config: $(CONFIGS)

$(CONFIGS): config.%: config.template.% global.cfg $(TEMPFILEDIR)/tempfile
	./generate_config.sh $*

$(TEMPFILEDIR)/tempfile:
	$(MAKE) -C $(TEMPFILEDIR) tempfile

clean:
	-rm -f $(CONFIGS)
	-rm -f *~

.PHONY: config clean
