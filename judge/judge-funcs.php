<?

/*

General way the judging works:

- ./compile ( sourcefile : dir with files)
- if (compile-error) return COMPILE-ERROR
- for each testcase:
	- ./run [ compiledir, testcasefile, testcaseparams : output, out-metainfo ]
	- ./evaluate [ output, out-metainfo, testcasedir : diff, run-result ]
	- ./determine-result [ run-results : $result, $isfinal ]
	- if ($isfinal) break;
	
- return $result

*/


function processSubmission($probleminfo, $submissioninfo)
{
	$RES_NO_TESTCASES = "INTERNAL-ERROR-NO-TESTCASES";

	log(INFO, "START processing submission ". $submissioninfo->toString());

	$tmpdir = createSomeTemporaryDirectory();

	$compileres = executePluginscript( 'compile', array(
		$submissioninfo->language()
		$submissioninfo->sourcefilePath(),
		"$tmpdir/compiled",
		"$tmpdir/compiler-output"));

	if (failed( $compileres )) {
		return $compileres;
	}

	storeInDb( "$tmpdir/compiler-output" );
		
	$result = $RES_NO_TESTCASES;

	$testcasedir = updateTestcasesFromDb( $probleminfo );

	$testcases = array();

	foreach ( $probleminfo->testcases() as $testcase ) {

		$testcases.push_back( $testcase );

		$testcaseid = $testcase->id();


		// ./run [ compiledir, testcasefile, testcaseparams
		//       : output, out-metainfo ]
		executePluginscript( 'run', array(
			$submissioninfo->language(),
			"$tmpdir/compiled",
			$testcasedir,
			"$tmpdir/testcase-$testcaseid" ));

		// ./evaluate [ output, out-metainfo, testcasedir : diff, run-result ]
		executePluginscript( 'evaluate', array(
			"$tmpdir/testcase-$testcaseid",
			$testcasedir));

		// ./determine-result [ run-results : result, is-final ]
		$determineResultOutput = executePluginscript( 'determine-result',
			array("$tmpdir/testcase-"), implode("\n", $testcases) . "\n\n");

		list($result, $isfinal) = explode(' ', trim($determineResultOutput));

		if (strpos($result, "RESULT=") !== 0) {
			log(ERROR, "determine-result returned wrongly formatted output:
				First word should start with RESULT=");
			exit(1);
		}
		$result = substr($result, strlen("RESULT=") );

		if ($isfinal === "FINAL=YES") {
			break;
		} else if ($isfinal === "FINAL=NO") {
			continue;
		} else {
			log(ERROR, "determine-result returned wrongly formatted output:
				Second word should be FINAL=<YES|NO>");
			exit(1);
		}
	}
		
	log(INFO, "DONE  processing submission ". $submissioninfo->toString());

	if ($result == $RES_NO_TESTCASES) {
		log(CONFIGURATIONERROR, "No testcases found for problem $problemid");
	}

	return $result;
}

function executePluginscript($name, $args, $in=NULL)
{
	$proc = popen( array( PLUGINDIR . $name, $argv ));

	$proc.run();
	if (!is_null($in)) {
		fwrite( $proc, $in);
	}

	// stderr, returnvalue lezen
	// indien stderr != "" of returnvalue != 0, raise error

	return $stdout;
}


