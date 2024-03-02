ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

export judgehost_tmpdir
export judgehost_judgedir
test: mem threads hello
	./runguard_test.sh

mem: mem.cc
	$(CXX) $(CXXFLAGS) -o $@ $<

threads: threads.cc
	$(CXX) $(CXXFLAGS) -o $@ $< -lpthread

hello: hello.cc
	$(CXX) $(CXXFLAGS) -static -o $@ $<

.PHONY: test
