SUBDIRS = bin etc lib doc submit judge www test-sources test-programs

all: config build

build: config
	-for i in $(SUBDIRS) ; do make -C $$i build ; done

config:
	make -C etc config

install: build
	echo "FIXME: Nothing done here, install manually."

clean:
	-for i in $(SUBDIRS) ; do make -C $$i clean ; done
	-rm -f *~

.PHONY: build config install clean
