CFLAGS = -Wall -O2
TARGETS = submit submitdaemon

all: $(TARGETS)

$(TARGETS): %: %.cc submitcommon.h ../etc/config.h ../lib/*.h
	$(CXX) $(CFLAGS) $(CXXFLAGS) $< -o $@
