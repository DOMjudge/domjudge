-include Makefile.global

SUBDIRS = bin etc lib doc submit judge www test-sources test-programs

all: config build

build: config
	-for i in $(SUBDIRS) ; do make -I .. -C $$i build ; done

config:
	make -I .. -C etc config

install: build
	@echo "FIXME: Nothing done here, install manually."

clean:
	-for i in $(SUBDIRS) ; do make -I .. -C $$i clean ; done
