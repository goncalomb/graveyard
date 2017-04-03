<?php

$fp0 = fopen($argv[1], 'rb');
$fp1 = fopen($argv[2], 'rb');

if (!$fp0 || !$fp1) {
	trigger_error('invalid files', E_USER_ERROR);
}

$s0 = fstat($fp0)['size'];
$s1 = fstat($fp1)['size'];

echo "File 1 with {$s0} bytes\n";
echo "File 2 with {$s1} bytes.\n";

if (strlen($s0) != strlen($s1)) {
	trigger_error('files with different sizes', E_USER_ERROR);
}

$r = 0;
$e = 0;
while (true) {
	$b0 = fread($fp0, 1024*1024);
	$b1 = fread($fp1, 1024*1024);
	if (strlen($b0) != strlen($b1)) {
		trigger_error('fread read different lengths', E_USER_ERROR);
	}
	if (strlen($b0) == 0) {
		break;
	}
	for ($i = 0, $l = strlen($b0); $i < $l; $i++) {
		if ($b0[$i] != $b1[$i]) {
			$e++;
		}
		$r++;
	}
	$pr = floor($r*10000/$s0)/100;
	$pe = floor($e*10000/$s0)/100;
	echo "\r$s0 $r ($pr%) $e ($pe%)";
}

echo "\n";

fclose($fp0);
fclose($fp1);

?>
