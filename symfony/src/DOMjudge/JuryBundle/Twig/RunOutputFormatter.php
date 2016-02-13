<?php

namespace DOMjudge\JuryBundle\Twig;

class RunOutputFormatter extends \Twig_Extension
{
	public function getFunctions()
	{
		return array(
			new \Twig_SimpleFunction('truncateCheck', array($this, 'truncateCheck')),
			new \Twig_SimpleFunction('parseRunDiff', array($this, 'parseRunDiff'), array(
				'is_safe' => array('html'),
			)),
			new \Twig_SimpleFunction('formatRunOutputText', array($this, 'formatOutputText'), array(
				'is_safe' => array('html'),
			)),
		);
	}

	/**
	 * Check if output has been truncated and if so, append text denoting this
	 * 
	 * @param string $value
	 *   The output to check
	 * @return string
	 *   The modified output
	 */
	public function truncateCheck($value)
	{
		if ( mb_strlen($value) > 50000 ) {
			return $value . "\n[output truncated after 50,000 B]\n";
		}

		return $value;
	}

	/**
	 * Parse run diff output
	 * 
	 * @param string $difftext
	 *   The difftext to parse
	 * @return string
	 *   The parsed difftext
	 */
	public function parseRunDiff($difftext)
	{
		$line = strtok($difftext, "\n"); //first line
		if ( sscanf($line, "### DIFFERENCES FROM LINE %d ###\n", $firstdiff) != 1 )
			return $difftext;
		$return = $line . "\n";

		// Add second line 'team ? reference'
		$line = strtok("\n");
		$return .= $line . "\n";

		// We determine the line number width from the '_' characters and
		// the separator position from the character '?' on the second line.
		$linenowidth = mb_strrpos($line, '_') + 1;
		$midloc = mb_strpos($line, '?') - ($linenowidth + 1);

		$line = strtok("\n");
		while ( mb_strlen($line) != 0 ) {
			$linenostr = mb_substr($line, 0, $linenowidth);
			$diffline = mb_substr($line, $linenowidth + 1);
			$mid = mb_substr($diffline, $midloc - 1, 3);
			switch ( $mid ) {
				case ' = ':
					$formdiffline = "<span class='correct'>" . $this->specialchars($diffline) . "</span>";
					break;
				case ' ! ':
					$formdiffline = "<span class='differ'>" . $this->specialchars($diffline) . "</span>";
					break;
				case ' $ ':
					$formdiffline = "<span class='endline'>" . $this->specialchars($diffline) . "</span>";
					break;
				case ' > ':
				case ' < ':
					$formdiffline = "<span class='extra'>" . $this->specialchars($diffline) . "</span>";
					break;
				default:
					$formdiffline = $this->specialchars($diffline);
			}
			$return = $return . $linenostr . " " . $formdiffline . "\n";
			$line = strtok("\n");
		}
		return $return;
	}

	/**
	 * Format output text
	 * 
	 * @param string $outputRun
	 *   The run output
	 * @param string $outputReference
	 *   The reference output
	 * @return string
	 *   The formatted text
	 */
	public function formatOutputText($outputRun, $outputReference) {
		$return = '';
		$return .= "<pre class=\"output_text\">";
		// TODO: can be improved using diffposition.txt
		// FIXME: only show when diffposition.txt is set?
		// FIXME: cut off after XXX lines
		$lines_team = preg_split('/\n/', trim($this->truncateCheck($outputRun)));
		$lines_ref  = preg_split('/\n/', trim($this->truncateCheck($outputReference)));

		$diffs = array();
		$firstErr = sizeof($lines_team) + 1;
		$lastErr  = -1;
		for ($i = 0; $i < min(sizeof($lines_team), sizeof($lines_ref)); $i++) {
			$lcs = $this->compute_lcsdiff($lines_team[$i], $lines_ref[$i]);
			if ( $lcs[0] === TRUE ) {
				$firstErr = min($firstErr, $i);
				$lastErr  = max($lastErr, $i);
			}
			$diffs[] = $lcs[1];
		}
		$contextLines = 5;
		$firstErr -= $contextLines;
		$lastErr  += $contextLines;
		$firstErr = max(0, $firstErr);
		$lastErr  = min(sizeof($diffs)-1, $lastErr);
		$return .= "<table class=\"lcsdiff\">\n";
		if ($firstErr > 0) {
			$return .= "<tr><td class=\"linenr\">[...]</td><td/></tr>\n";
		}
		for ($i = $firstErr; $i <= $lastErr; $i++) {
			$return .= "<tr><td class=\"linenr\">" . ($i + 1) . "</td><td>" . $diffs[$i] . "</td></tr>";
		}
		if ($lastErr < sizeof($diffs) - 1) {
			$return .= "<tr><td class=\"linenr\">[...]</td><td/></tr>\n";
		}
		$return .= "</table>";

		$return .= "</pre>\n\n";
		
		return $return;
	}

	/**
	 * Compute the LCS diff of two lines
	 * 
	 * @param string $line1
	 *   The first line
	 * @param string $line2
	 *   The second line
	 * @return array
	 *   A boolean whether the two lines match and a value containing the diff
	 */
	private function compute_lcsdiff($line1, $line2) {
		$tokens1 = preg_split('/\s+/', $line1);
		$tokens2 = preg_split('/\s+/', $line2);
		$cutoff = 100; // a) LCS gets inperformant, b) the output is not longer readable

		$n1 = min($cutoff, sizeof($tokens1));
		$n2 = min($cutoff, sizeof($tokens2));

		// compute longest common sequence length
		$dp = array_fill(0, $n1+1, array_fill(0, $n2+1, 0));
		for ($i = 1; $i < $n1 + 1; $i++) {
			for ($j = 1; $j < $n2 + 1; $j++) {
				if ($tokens1[$i-1] == $tokens2[$j-1]) {
					$dp[$i][$j] = $dp[$i-1][$j-1] + 1;
				} else {
					$dp[$i][$j] = max($dp[$i-1][$j], $dp[$i][$j-1]);
				}
			}
		}

		if ($n1 == $n2 && $n1 == $dp[$n1][$n2]) {
			return array(false, $this->specialchars($line1) . "\n");
		}

		// reconstruct lcs
		$i = $n1;
		$j = $n2;
		$lcs = array();
		while ($i > 0 && $j > 0) {
			if ($tokens1[$i-1] == $tokens2[$j-1]) {
				$lcs[] = $tokens1[$i-1];
				$i--;
				$j--;
			} else if ($dp[$i-1][$j] > $dp[$i][$j-1]) {
				$i--;
			} else {
				$j--;
			}
		}
		$lcs = array_reverse($lcs);

		// reconstruct diff
		$diff = "";
		$l = sizeof($lcs);
		$i = 0;
		$j = 0;
		for ($k = 0; $k < $l ; $k++) {
			while ($i < $n1 && $tokens1[$i] != $lcs[$k]) {
				$diff .= "<del>" . $this->specialchars($tokens1[$i]) . "</del> ";
				$i++;
			}
			while ($j < $n2 && $tokens2[$j] != $lcs[$k]) {
				$diff .= "<ins>" . $this->specialchars($tokens2[$j]) . "</ins> ";
				$j++;
			}
			$diff .= $lcs[$k] . " ";
			$i++;
			$j++;
		}
		while ($i < $n1 && ($k >= $l || $tokens1[$i] != $lcs[$k])) {
			$diff .= "<del>" . $this->specialchars($tokens1[$i]) . "</del> ";
			$i++;
		}
		while ($j < $n2 && ($k >= $l || $tokens2[$j] != $lcs[$k])) {
			$diff .= "<ins>" . $this->specialchars($tokens2[$j]) . "</ins> ";
			$j++;
		}

		if ($cutoff < sizeof($tokens1) || $cutoff < sizeof($tokens2)) {
			$diff .= "[cut off rest of line...]";
		}
		$diff .= "\n";

		return array(TRUE, $diff);
	}

	/**
	 * Strip special characters from a string
	 * 
	 * Normally Twig takes care of this, but we have is_safe => ['html'], so we need to do this.
	 * If we need it in more places, move it to some service
	 * 
	 * @param string $string
	 *   The string to strip
	 * @return string
	 *   The stripped string
	 */
	private function specialchars($string) {
		return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE);
	}

	public function getName()
	{
		return 'run_output_formatter';
	}
}
