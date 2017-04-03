<?php

define('WORKING_DIR', realpath('.'));
define('CHECKSUMS_FILE_NAME', 'checksums.sha1');
define('CHECKSUMS_FILE', WORKING_DIR . DIRECTORY_SEPARATOR . CHECKSUMS_FILE_NAME);

$options = getopt('', array('create', 'touch', 'mkdirs'));

function do_walk_path($path, $callback, $path_rel='') {
	$handle = opendir($path);
	while (($entry = readdir($handle)) !== false) {
		if ($entry != '.' && $entry != '..') {
			$entry_path = $path . DIRECTORY_SEPARATOR . $entry;
			$entry_path_rel = ($path_rel == '' ? $entry : $path_rel . '/' . $entry);
			if ($entry_path_rel == CHECKSUMS_FILE_NAME) {
				continue;
			} else if (is_dir($entry_path)) {
				$callback($entry, $entry_path, $entry_path_rel);
				do_walk_path($entry_path, $callback, $entry_path_rel);
			} else if (is_file($entry_path)) {
				$callback($entry, $entry_path, $entry_path_rel);
			} else {
				echo "WARNING: Invalid file type \'", filetype($entry_path), "', '{$entry_path_rel}'.\n";
			}
		}
	}
	closedir($handle);
}

function walk_working_path($callback) {
	do_walk_path(WORKING_DIR, $callback);
}

function read_data_file($file) {
	$data = array();
	$fp = fopen($file, 'rb');
	$i = 0;
	$file_entry = null;
	while (($line = fgets($fp)) !== false) {
		if (preg_match('/^# FILE: ({.+})$/', $line, $matches)) {
			$file_entry = json_decode($matches[1], true);
		} else if (preg_match('/^([0-9a-fA-F]{40}) \*(.+)$/', $line, $matches)) {
			$file_entry['sha1'] = $matches[1];
			$data[$matches[2]] = $file_entry;
		} else if (preg_match('/^# DIRECTORY: (\{.+\})$/', $line, $matches)) {
			$dir_entry = json_decode($matches[1], true);
			$data[utf8_decode($dir_entry['path'])] = array('mtime' => $dir_entry['mtime']);
		}
	}
	fclose($fp);
	return $data;
}

function write_data_file($file, $data) {
	$files = 0;
	$directories = 1;
	$total_size = 0;
	foreach ($data as $path => $info) {
		if(isset($info['size'])) {
			$total_size += $info['size'];
			$files++;
		} else {
			$directories++;
		}
	}
	$fp = fopen($file, 'wb');
	fwrite($fp, "# sha1sum compatible format with extra information\n#\n");
	fwrite($fp, "# {$files} files (in {$directories} directories) totalling {$total_size} bytes\n#\n");
	foreach ($data as $path => $info) {
		if(isset($info['size'])) {
			fwrite($fp, '# FILE: ');
			fwrite($fp, json_encode(array('size' => $info['size'], 'mtime' => $info['mtime'])));
			fwrite($fp, "\n");
			fwrite($fp, "{$info['sha1']} *{$path}\n");
		} else {
			fwrite($fp, '# DIRECTORY: ');
			fwrite($fp, json_encode(array('path' => utf8_encode($path), 'mtime' => $info['mtime'])));
			fwrite($fp, "\n");
		}
	}
	fclose($fp);
}

if (is_file(CHECKSUMS_FILE)) {
	if (isset($options['create'])) {
		echo "Checksums file '", CHECKSUMS_FILE_NAME, "' already exists.\n";
	} else {
		$data = read_data_file(CHECKSUMS_FILE);
		echo "Checking", (isset($options['touch']) ? " (and touching)" : ""), " files...\n\n";
		$errors_sha1 = $errors_mtime = 0;
		walk_working_path(function($entry, $entry_path, $entry_path_rel) use ($options, &$data, &$errors_sha1, &$errors_mtime) {
			if (is_file($entry_path)) {
				if (isset($data[$entry_path_rel]) && isset($data[$entry_path_rel]['size'])) {
					if (sha1_file($entry_path) != $data[$entry_path_rel]['sha1']) {
						echo "WARNING: '{$entry_path_rel}' has different sha1. HASH FAILED!\n";
						$errors_sha1++;
					} else if (filemtime($entry_path) != $data[$entry_path_rel]['mtime']) {
						if (isset($options['touch'])) {
							touch($entry_path, $data[$entry_path_rel]['mtime']);
						} else {
							echo "INFO: '{$entry_path_rel}' has different mtime.\n";
						}
						$errors_mtime++;
					}
					unset($data[$entry_path_rel]);
				} else {
					echo "INFO: New file '{$entry_path_rel}'.\n";
				}
			} else {
				if (isset($data[$entry_path_rel]) && !isset($data[$entry_path_rel]['size'])) {
					if (filemtime($entry_path) != $data[$entry_path_rel]['mtime']) {
						if (isset($options['touch'])) {
							touch($entry_path, $data[$entry_path_rel]['mtime']);
						} else {
							echo "INFO: '{$entry_path_rel}' (directory) has different mtime.\n";
						}
						$errors_mtime++;
					}
					unset($data[$entry_path_rel]);
				} else {
					echo "INFO: New directory '{$entry_path_rel}'.\n";
				}
			}
		});

		// Find missing.
		$errors_missing_files = 0;
		$errors_missing_dirs = 0;
		foreach ($data as $path => $info) {
			if(isset($info['size'])) {
				echo "WARNING: Missing file '{$path}'!\n";
				$errors_missing_files++;
			} else {
				if (isset($options['mkdirs'])) {
					if (!file_exists($path)) {
						$parent_path = dirname($path);
						$parent_mtime = filemtime($parent_path);
						mkdir($path, 0777, true);
						touch($parent_path, $parent_mtime);
					}
					touch($path, $info['mtime']);
				} else {
					echo "WARNING: Missing directory '{$path}'!\n";
				}
				$errors_missing_dirs++;
			}
		}

		// Error messages.
		$any_true_errors = $errors_sha1 + ($errors_mtime * (int) !isset($options['touch'])) + $errors_missing_files + ($errors_missing_dirs * (int) !isset($options['mkdirs']));
		if ($any_true_errors) {
			echo "\n";
		}
		if ($any_true_errors && $errors_sha1 == 0 && $errors_missing_files == 0) {
			echo "ALL FILES are OK.\n";
		}
		if ($errors_sha1) {
			echo "Some files ({$errors_sha1}) CHANGED or are CORRUPT!!!\n";
		}
		if ($errors_mtime) {
			if (isset($options['touch'])) {
				echo "Fixed {$errors_mtime} mtime differences.\n";
			} else {
				echo "Some files/directories ({$errors_mtime}) have different mtime, use --touch to fix it.\n";
			}
		}
		if ($errors_missing_files) {
			echo "Some files ({$errors_missing_files}) are MISSING!!!\n";
		}
		if ($errors_missing_dirs) {
			if (isset($options['mkdirs'])) {
				echo "Recreated {$errors_missing_dirs} directories.\n";
			} else {
				echo "Some directories ({$errors_missing_dirs}) are MISSING, use --mkdirs to recreate them.\n";
			}
		}
		if (!$any_true_errors) {
			echo "EVERYTHING is OK.\n";
		}
	}
} else if (isset($options['create'])) {
	$data = array();
	walk_working_path(function($entry, $entry_path, $entry_path_rel) use (&$data) {
		if (is_file($entry_path)) {
			$data[$entry_path_rel] = array(
				'size' => filesize($entry_path),
				'mtime' => filemtime($entry_path),
				'sha1' => sha1_file($entry_path)
			);
		} else {
			$data[$entry_path_rel] = array(
				'mtime' => filemtime($entry_path)
			);
		}
	});
	write_data_file(CHECKSUMS_FILE, $data);
} else {
	echo "Checksums file '", CHECKSUMS_FILE_NAME, "' not found, use --create to create it.\n";
}

?>
