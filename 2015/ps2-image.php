<?php

$data = file_get_contents("E:\SYSTEM.CNF");
preg_match("/cdrom0?\:\\\?(.+)\;1/", $data, $matches);
$code = $matches[1];

if ($code) {
	$dest = 'D:\\my-ps2-roms\\' . $matches[1] . '.iso';
	echo "Found PS2 DVD ({$code}).\n";
	echo "Creating image to {$dest}...\n";
	system('"C:\Program Files (x86)\ImgBurn\ImgBurn.exe" /MODE READ /DEST "' . $dest . '" /START /CLOSESUCCESS');
	echo "Done.";
	system('echo' . str_repeat(' ' . chr(7), 3));
} else {
	echo "PS2 DVD not found!\n";
}

?>
