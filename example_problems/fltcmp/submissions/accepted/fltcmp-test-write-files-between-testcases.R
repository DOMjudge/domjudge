# This checks if we can write extra files
# between different testcase runs. R uses
# a temporary directory which DOMjudge should
# make unavailable between runs and in case this
# is forgotten teams can precalculate extra files for later
# testcases. We need a working solution to make sure
# we get to all testcases.
#
# This submission is symlinked to make sure we submit it twice
# to verify that we can't see the TMPDIR of the earlier submission.
#
# The script will be WRONG-ANSWER if any of the assumptions
# are wrong.
# The script will be NO-OUTPUT if there is a security violation.
# The verdict RUN-ERROR is expected if we correctly detect the
# violation and would be the correct verdict if we don't need a
# TMPDIR.
#
# @EXPECTED_RESULTS@: CORRECT

# For R the TMPDIR needs to be set, if it's not
# we can skip this whole test.
tmpdir<-Sys.getenv(c("TMPDIR"))
if (tmpdir == '') {
  print("TMPDIR not set, update installation instructions.")
  quit()
}

# We had 3 testcases in the past, try with double for when new testcases get added.
testcaseIds<-rep(1:6)
possibleTempDirs <- (function (x) sprintf("/testcase%05d/write_tmp", x))(testcaseIds)
if (!(tmpdir %in% possibleTempDirs)) {	
  print("Either TMPDIR format has changed, or too many testcases.")
  print(sprintf("Current TMPDIR: %s", tmpdir))
  quit()
}

for (possibleTempDir in possibleTempDirs) {
  teamFile<-sprintf("%s/fileFromTeam.txt", possibleTempDir)
  if (file.exists(teamFile)) {
    # File should not be here
    quit()
  }
}

# Try to write to our TMPDIR to read it in the next testcase.
currentTeamFile<-sprintf("%s/fileFromTeam.txt", tmpdir)
fileConn<-file(currentTeamFile)
writeLines(c("Calculate","all","floats"), fileConn)
close(fileConn)
# Make sure our test from earlier does work for this testcase
if (!(file.exists(currentTeamFile))) {
  # File should be here
  quit()
}

# Now try the actual problem to advance to the next testcase.
input<-file("stdin")
lines<-readLines(input)

# https://evolutionarygenetics.github.io/Chapter10.html
# a simple reciprocal example
reciprocal <- function(x){
  # calculate the reciprocal
  y <- 1/x
  return(y)
}

for (l in lines[-1]) {
  l<-as.numeric(l)
  r<-reciprocal(l)
  cat(r,"\n")
}
