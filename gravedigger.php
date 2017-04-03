<?php

define('DIR_ROOT', __DIR__);
define('DIR_INPUT', DIR_ROOT . DIRECTORY_SEPARATOR . 'input');

@mkdir(DIR_INPUT);

function read_index_file($path) {
	if (!is_file($path)) {
		return array();
	}
	$data = array();
	$fp = fopen($path, 'rb');
	$i = 1;
	$new_file = null;
	while (($line = fgets($fp)) != false) {
		$line = rtrim($line, "\r\n");
		if (preg_match('/^(\d+) ([^\s]*)$/', $line, $matches) && $new_file == null) {
			if (isset($data[$matches[2]])) {
				trigger_error("duplicate entry in {$path} on line {$i}", E_USER_ERROR);
			}
			$new_file = array('mtime' => (int) $matches[1], 'description' => array());
			$data[$matches[2]] = &$new_file;
		} else if (preg_match('/^#\s*(.*)$/', $line, $matches) && $new_file != null) {
			$new_file['description'][] = $matches[1];
		} else if (strlen(trim($line))) {
			trigger_error("syntax error in {$path} on line {$i}", E_USER_ERROR);
		} else if ($new_file) {
			unset($new_file);
			$new_file = null;
		}
		$i++;
	}
	fclose($fp);
	uasort($data, function($a, $b) { return ($a['mtime'] - $b['mtime']); });
	return $data;
}

function write_index_file($path, $data) {
	uasort($data, function($a, $b) { return ($a['mtime'] - $b['mtime']); });
	$fp = fopen($path, 'wb');
	$i = count($data);
	foreach ($data as $file => $d) {
		fwrite($fp, "{$d['mtime']} {$file}\n");
		foreach ($d['description'] as $txt) {
			fwrite($fp, "# {$txt}\n");
		}
		if (--$i) {
			fwrite($fp, "\n");
		}
	}
	fclose($fp);
}

function find_years() {
	$years = array();
	$handle = opendir(DIR_ROOT);
	while ($entry = readdir($handle)) {
		if (preg_match('/^\d{4}$/', $entry)) {
			$years[(int) $entry] = DIR_ROOT . DIRECTORY_SEPARATOR . $entry;
		}
	}
	closedir($handle);
	krsort($years);
	return $years;
}

function store_new_file($name, $path) {
	$mtime = filemtime($path);
	$y = date('Y', $mtime);
	$dir_year = DIR_ROOT . DIRECTORY_SEPARATOR . $y;
	$path_index = $dir_year . DIRECTORY_SEPARATOR . '_index.txt';
	$path_new = $dir_year . DIRECTORY_SEPARATOR . $name;
	if (file_exists($path_new)) {
		fwrite(STDERR, "duplicate file {$path_new}\n");
	} else {
		@mkdir($dir_year);
		$data = read_index_file($dir_year . DIRECTORY_SEPARATOR . '_index.txt');
		$data[$name] = array('mtime' => (int) $mtime, 'description' => array('*no description*'));
		write_index_file($dir_year . DIRECTORY_SEPARATOR . '_index.txt', $data);
		rename($path, $path_new);
	}
}

// store input files

$handle = opendir(DIR_INPUT);
while ($entry = readdir($handle)) {
	if ($entry == '.' || $entry == '..') {
		continue;
	}
	$path = DIR_INPUT . DIRECTORY_SEPARATOR . $entry;
	if (is_file($path)) {
		store_new_file($entry, $path);
	}
}
closedir($handle);

// create global index and touch files

$fp = fopen(DIR_ROOT . DIRECTORY_SEPARATOR . 'README.md', 'r+b');
while (($line = fgets($fp)) != false) {
	if (trim($line) == '## File Index ##') {
		ftruncate($fp, ftell($fp));
		break;
	}
}
foreach (find_years() as $year => $path) {
	fwrite($fp, "\n");
	fwrite($fp, "### {$year} ###\n");
	$data = read_index_file($path . DIRECTORY_SEPARATOR . '_index.txt');
	uasort($data, function($a, $b) { return ($b['mtime'] - $a['mtime']); });
	foreach ($data as $file => $d) {
		$date = date('Y-m-d', $d['mtime']);
		fwrite($fp, "* [{$file}]($year/$file) : {$date} : {$d['description'][0]}\n");
		touch(implode(DIRECTORY_SEPARATOR, array(DIR_ROOT, $year, $file)), $d['mtime']);
	}
}
fclose($fp);

?>
