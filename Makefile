all: config build

export TOPDIR = $(PWD)
-include $(TOPDIR)/Makefile.global

SUBDIRS = bin etc lib doc submit judge www test-programs

build: config

config:
	$(MAKE) -C etc config

dvi:
	$(MAKE) -C doc dvi

install: build
	@echo "FIXME: Nothing done here, install manually."

REC_TARGETS = build clean

$(REC_TARGETS): %:
	for dir in $(SUBDIRS) ; do $(MAKE) -C $$dir $@ || exit 1 ; done

.PHONY: dvi
