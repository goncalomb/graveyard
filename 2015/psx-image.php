<?php

$data = file_get_contents("E:\SYSTEM.CNF");
preg_match("/cdrom\:\\\?(.+)\;1/", $data, $matches);
$code = $matches[1];

if ($code) {
	$dest = 'D:\\my-psx-roms\\' . $matches[1] . '.bin';
	echo "Found PSX CD ({$code}).\n";
	echo "Creating image to {$dest}...\n";
	system('"C:\Program Files (x86)\ImgBurn\ImgBurn.exe" /MODE READ /DEST "' . $dest . '" /START /CLOSESUCCESS');
	echo "Done.";
	system('echo' . str_repeat(' ' . chr(7), 3));
} else {
	echo "PSX CD not found!\n";
}

?>
