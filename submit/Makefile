CFLAGS  = -O2 -Wall
TARGETS = submit submitdaemon

all: build

build: $(TARGETS)

install:

$(TARGETS): %: %.cc submitcommon.h ../etc/config.h ../lib/*.h
	$(CXX) $(CFLAGS) $(CXXFLAGS) $< -o $@

clean:
	-rm -f $(TARGETS)
	-rm -f *~

.PHONY: build install clean
