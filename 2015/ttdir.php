<?php

// ttdir: TrackThisDIRrectory

define('WORKING_DIR', realpath('.'));
define('DATA_FILE_NAME', 'ttdir.json');
define('DATA_FILE', WORKING_DIR . DIRECTORY_SEPARATOR . DATA_FILE_NAME);

$options = getopt('', array('duplicates', 'show-all', 'rename', 'add', 'remove', 'update'));

$data = array();
if (is_file(DATA_FILE)) {
	$data = json_decode(file_get_contents(DATA_FILE), true);
} else {
	echo "No ttdir data file found. This is a untracked directory.\n";
}

$new_files = array();
$deleted_files = $data;
$changed_files = array();

$sha1_cache = array();

function sha1_file_cache($path) {
	global $sha1_cache;
	if (isset($sha1_cache[$path])) {
		return $sha1_cache[$path];
	}
	return ($sha1_cache[$path] = sha1_file($path));
}

function get_file_info($path) {
	return array(
		'size' => filesize($path),
		'mtime' => filemtime($path),
		'sha1' => sha1_file_cache($path)
	);
}

function do_process_file($parent_path, $parent_path_rel, $entry, $entry_path, $entry_path_rel) {
	global $data, $new_files, $deleted_files, $changed_files;
	if (!isset($data[$entry_path_rel])) {
		$new_files[$entry_path_rel] = get_file_info($entry_path);
	} else {
		unset($deleted_files[$entry_path_rel]);
		if (filesize($entry_path) != $data[$entry_path_rel]['size'] || sha1_file_cache($entry_path) != $data[$entry_path_rel]['sha1']) {
			$changed_files[$entry_path_rel] = $data[$entry_path_rel];
		} else {

		}
	}
}

function do_walk_path($path, $path_rel='') {
	$handle = opendir($path);
	while (($entry = readdir($handle)) !== false) {
		if ($entry != '.' && $entry != '..') {
			$entry = utf8_encode($entry);
			$entry_path = $path . DIRECTORY_SEPARATOR . $entry;
			$entry_path_rel = ($path_rel == '' ? $entry : $path_rel . '/' . $entry);
			if ($entry_path_rel == DATA_FILE_NAME) {
				continue;
			} else if (is_dir($entry_path)) {
				do_walk_path($entry_path, $entry_path_rel);
			} else if (is_file($entry_path)) {
				do_process_file($path, $path_rel, $entry, $entry_path, $entry_path_rel);
			}
		}
	}
	closedir($handle);
}

do_walk_path(WORKING_DIR);

echo 'Tracking ', count($data), " files.\n";

function get_files_by_sha1($file_list) {
	$by_sha1 = array();
	foreach ($file_list as $file_path_rel => $file_info) {
		$by_sha1[$file_info['sha1']][] = $file_path_rel;
	}
	return $by_sha1;
}

if (isset($options['duplicates'])) {
	echo "\nDuplicates:\n";
	$nop = true;
	foreach (get_files_by_sha1($data) as $sha1 => $file_list) {
		if (count($file_list) > 1) {
			$nop = false;
			echo $sha1, "\n";
			foreach ($file_list as $file_path_rel) {
				echo '  ', $file_path_rel, "\n";
			}
		}
	}
	if ($nop) {
		echo "No duplicates found.\n";
	}

} else {
	function echo_file_list($list, $callback=null) {
		global $options;
		$i = 0;
		foreach ($list as $file_path_rel => $info) {
			if ($callback) {
				$callback($file_path_rel, $info);
			} else {
				echo '  ', $file_path_rel, "\n";
			}
			if (!isset($options['show-all']) && ++$i >= 5) {
				echo "  ... (--show-all to show all files)\n";
				break;
			}
		}
	}

	$deleted_files_by_sha1 = get_files_by_sha1($deleted_files);
	$changed_files_by_sha1 = get_files_by_sha1($changed_files);
	$renamed_files = array();

	foreach ($new_files as $file_path_rel => $file_info) {
		if (isset($deleted_files_by_sha1[$file_info['sha1']])) {
			$old_file_path_rel = $deleted_files_by_sha1[$file_info['sha1']][0];
			unset($deleted_files[$old_file_path_rel]);
			$renamed_files[$file_path_rel] = array($old_file_path_rel, $file_info);
			unset($new_files[$file_path_rel]);
		} else if (isset($changed_files_by_sha1[$file_info['sha1']])) {
			$old_file_path_rel = $changed_files_by_sha1[$file_info['sha1']][0];
			$new_files[$old_file_path_rel] = get_file_info(realpath($old_file_path_rel));
			unset($changed_files[$old_file_path_rel]);
			$renamed_files[$file_path_rel] = array($old_file_path_rel, $file_info);
			unset($new_files[$file_path_rel]);
		}
	}

	if (count($renamed_files)) {
		echo "\n";
		echo_file_list($renamed_files, function($file_path_rel, $info) {
			echo '  ', $info[0], "\n";
			echo '   -> ', $file_path_rel, "\n";
		});
		echo "\n", count($renamed_files), " files RENAMED! \n";
		if (isset($options['rename'])) {
			foreach ($renamed_files as $file_path_rel => $file_misc) {
				list($old_file_path_rel, $file_info) = $file_misc;
				unset($data[$old_file_path_rel]);
				$data[$file_path_rel] = $file_info;
			}
			echo "Changes updated for these files.\n";
		} else {
			echo "Use --rename to track the new changes.\n";
		}
	}

	if (count($new_files)) {
		echo "\n";
		echo_file_list($new_files);
		echo "\n", count($new_files), " NEW files! \n";
		if (isset($options['add'])) {
			$data = array_merge($data, $new_files);
			echo "Now traking these files.\n";
		} else {
			echo "Use --add to track the new files.\n";
		}
	}

	if (count($deleted_files)) {
		echo "\n";
		echo_file_list($deleted_files);
		echo "\n", count($deleted_files), " files DELETED! \n";
		if (isset($options['remove'])) {
			$data = array_diff_key($data, $deleted_files);
			echo "No longer tracking these files.\n";
		} else {
			echo "Use --remove to stop tracking these files.\n";
		}
	}

	if (count($changed_files)) {
		echo "\n";
		echo_file_list($changed_files);
		echo "\n", count($changed_files), " files CHANGED or are CORRUPT! \n";
		if (isset($options['update'])) {
			foreach ($changed_files as $file_path_rel => $void) {
				$data[$file_path_rel] = get_file_info(realpath($file_path));
			}
			echo "Changes updated for these files.\n";
		} else {
			echo "Use --update to track the new changes.\n";
		}
	}

	if (!count($renamed_files) && !count($new_files) && !count($deleted_files) && !count($changed_files)) {
		echo "\nNo changes. Everything is OK.\n";
	}
}

if (is_file(DATA_FILE) || count($data)) {
	file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

?>
