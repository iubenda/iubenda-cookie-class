<?php
/**
 * Runs every *_test.php in this directory as its own PHP subprocess and aggregates
 * exit codes. Subprocesses (not require) are used deliberately: a hang in one file
 * is contained by that file's wall-clock guard and cannot take the rest down.
 *
 * Run:                 php run_all.php
 * Against pre-fix lib: IUB_COOKIE_CLASS_DIR=/path/to/old-lib php run_all.php
 */

$files = glob(__DIR__ . '/*_test.php');
sort($files);

$php    = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
$failed = 0;

foreach ($files as $file) {
	echo str_repeat('=', 72) . "\n";
	passthru(escapeshellarg($php) . ' ' . escapeshellarg($file), $code);
	if ($code !== 0) {
		$failed++;
	}
}

echo str_repeat('=', 72) . "\n";
if ($failed === 0) {
	echo "ALL FILES PASSED (" . count($files) . " files)\n";
	exit(0);
}

echo "$failed of " . count($files) . " FILES FAILED\n";
exit(1);
