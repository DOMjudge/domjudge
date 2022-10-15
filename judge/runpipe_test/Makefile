ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

TESTCASES = J_closes_stdout J_returns_42 J_returns_43 S_exits_early J_exits_early S_closes_stdin S_doesnt_write J_doesnt_write sigterm timeout_with_traffic
RUNPIPES = runpipe

TESTCASES_JUDGE = $(TESTCASES:=/judge)
TESTCASES_SOLUTION = $(TESTCASES:=/solution)
TESTCASES_OUTPUTS = $(TESTCASES:=/output.txt) $(TESTCASES:=/stderr.txt)

TESTCASES_TARGETS = $(TESTCASES:%=testcase/%)

test: $(TESTCASES_TARGETS)

testcase/%: TESTCASE = $*
testcase/%: %/judge %/solution
	@printf "========================\n"
	@printf "\033[32;1mTesting %s\033[0m\n" $(TESTCASE)
	@printf "========================\n"
	for runpipe in $(RUNPIPES); do \
		$(MAKE) test/$$runpipe TESTCASE=$(TESTCASE); \
	done

test/%: RUNPIPE = $*
test/%:
	$(info Testing $(TESTCASE) with $(RUNPIPE))
	cd $(TESTCASE) && ./run.sh ../../$(RUNPIPE)


%/judge: %/judge.c
	$(CC) $(CFLAGS) -o $@ $<

%/solution: %/solution.c
	$(CC) $(CFLAGS) -o $@ $<

clean-l:
	-rm -f $(TESTCASES_JUDGE) $(TESTCASES_SOLUTION) $(TESTCASES_OUTPUTS)
