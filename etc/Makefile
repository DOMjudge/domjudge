ifndef TOPDIR
TOPDIR=..
endif
include $(TOPDIR)/Makefile.global

EXTENSIONS = h sh php tex

default: config

config:
	@printf "Generating config [ "; \
	 for c in $(EXTENSIONS); do \
	     ./generate_config.sh $$c; \
	     printf "$$c ";\
	 done; \
	 printf "] done.\n"

check:
	./check_config.php

clean:

.PHONY: config
