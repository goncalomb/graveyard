<?php

// generate pi from random coprimes
// https://www.youtube.com/watch?v=RZBhSi_PwHU

$t = 0;
$c = 0;

while (true) {
	$a = gmp_random();
	$b = gmp_random();
	if (gmp_cmp(gmp_gcd($a, $b), 1) == 0) {
		$c++;
	}
	$t++;
	if ($t%10000 == 0) {
		echo $t, ' ', $c, ' ', sqrt(6*$t/$c), "\n";
	}
}

?>


