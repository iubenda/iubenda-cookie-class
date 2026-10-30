<?php
/**
 * Dependency-free test harness for the 3.13.7 noise-mechanism security fixes.
 *
 * No PHPUnit, no composer: every file under tests/security/ runs with plain
 * `php <file>.php`. This bootstrap provides the assertion helpers, a per-test
 * runner, and a hard wall-clock guard so a DoS regression FAILS loudly (via a
 * SIGALRM exception, or a set_time_limit() fallback) instead of hanging.
 *
 * Why not assert(): in a production php.ini zend.assertions is -1, which compiles
 * assert() out entirely, so an assert()-based suite "passes" vacuously. The
 * helpers below always execute and always count.
 *
 * The library under test defaults to two levels up (the clone root) but can be
 * pointed elsewhere with IUB_COOKIE_CLASS_DIR -- that is how the discrimination
 * run exercises the pre-fix copy of the files.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

if (! defined('LIB_DIR')) {
	$env = getenv('IUB_COOKIE_CLASS_DIR');
	define('LIB_DIR', $env !== false && $env !== '' ? rtrim($env, '/') : dirname(__DIR__, 2));
}

$GLOBALS['t_assert']   = 0;
$GLOBALS['t_pass']     = 0;
$GLOBALS['t_fail']     = 0;
$GLOBALS['t_failures'] = array();
$GLOBALS['current_test'] = null;
$GLOBALS['t_reported'] = false;

final class AssertionFailure extends Exception {}

/* ------------------------------------------------------------------ asserts */

function t_fail_assert($msg) {
	throw new AssertionFailure($msg);
}

function t_true($cond, $msg = 'expected true') {
	$GLOBALS['t_assert']++;
	if (! $cond) { t_fail_assert($msg); }
}

function t_same($expected, $actual, $msg = '') {
	$GLOBALS['t_assert']++;
	if ($expected !== $actual) {
		t_fail_assert(($msg ? $msg . ' -- ' : '') . 'expected [' . var_export($expected, true) . '] got [' . var_export($actual, true) . ']');
	}
}

function t_not_same($a, $b, $msg = '') {
	$GLOBALS['t_assert']++;
	if ($a === $b) {
		t_fail_assert(($msg ? $msg . ' -- ' : '') . 'values must differ but both are [' . var_export($a, true) . ']');
	}
}

function t_contains($needle, $haystack, $msg = '') {
	$GLOBALS['t_assert']++;
	if (strpos((string) $haystack, (string) $needle) === false) {
		t_fail_assert(($msg ? $msg . ' -- ' : '') . 'expected to find [' . $needle . '] in [' . $haystack . ']');
	}
}

function t_not_contains($needle, $haystack, $msg = '') {
	$GLOBALS['t_assert']++;
	if (strpos((string) $haystack, (string) $needle) !== false) {
		t_fail_assert(($msg ? $msg . ' -- ' : '') . 'expected NOT to find [' . $needle . '] in [' . $haystack . ']');
	}
}

function t_matches($regex, $subject, $msg = '') {
	$GLOBALS['t_assert']++;
	if (! preg_match($regex, (string) $subject)) {
		t_fail_assert(($msg ? $msg . ' -- ' : '') . 'expected [' . $subject . '] to match ' . $regex);
	}
}

function t_not_matches($regex, $subject, $msg = '') {
	$GLOBALS['t_assert']++;
	if (preg_match($regex, (string) $subject)) {
		t_fail_assert(($msg ? $msg . ' -- ' : '') . 'expected [' . $subject . '] to NOT match ' . $regex);
	}
}

function t_is_object($v, $msg = 'expected an object') {
	$GLOBALS['t_assert']++;
	if (! is_object($v)) { t_fail_assert($msg . ' (got ' . gettype($v) . ')'); }
}

function t_ends_with($suffix, $subject, $msg = '') {
	$GLOBALS['t_assert']++;
	if (substr((string) $subject, -strlen($suffix)) !== $suffix) {
		t_fail_assert(($msg ? $msg . ' -- ' : '') . 'expected [' . $subject . '] to end with [' . $suffix . ']');
	}
}

function t_less_than($bound, $actual, $msg = '') {
	$GLOBALS['t_assert']++;
	if (! ($actual < $bound)) {
		t_fail_assert(($msg ? $msg . ' -- ' : '') . 'expected ' . $actual . ' < ' . $bound);
	}
}

/* ------------------------------------------------------------- reflection */

/** Read the protected per-parse noise prefix from a simple_html_dom instance. */
function noise_prefix_of($dom) {
	$ref  = new ReflectionObject($dom);
	$prop = $ref->getProperty('noise_prefix');
	$prop->setAccessible(true);
	return $prop->getValue($dom);
}

/* ------------------------------------------------------------ time guard */

function t_guard_arm($seconds) {
	if (function_exists('pcntl_async_signals') && function_exists('pcntl_alarm')) {
		pcntl_async_signals(true);
		pcntl_signal(SIGALRM, function () {
			throw new RuntimeException('did not terminate within the time bound (DoS regression)');
		});
		pcntl_alarm($seconds);
		return true;
	}
	// No pcntl: fall back to set_time_limit. The old loop is CPU-bound so this
	// fires -- but it is an uncatchable fatal, caught by the shutdown handler
	// below and attributed to the running test.
	@set_time_limit($seconds + 2);
	return false;
}

function t_guard_disarm($had_pcntl) {
	if ($had_pcntl) {
		pcntl_alarm(0);
	} else {
		@set_time_limit(0);
	}
}

/* -------------------------------------------------------------- runner */

function run_test($name, callable $fn, $guard_seconds = 3) {
	$GLOBALS['current_test'] = $name;
	$had = t_guard_arm($guard_seconds);
	try {
		$fn();
		$GLOBALS['t_pass']++;
		echo "  PASS  $name\n";
	} catch (Throwable $e) {
		$GLOBALS['t_fail']++;
		$GLOBALS['t_failures'][] = $name . ': ' . $e->getMessage();
		echo "  FAIL  $name\n        " . $e->getMessage() . "\n";
	}
	t_guard_disarm($had);
	$GLOBALS['current_test'] = null;
}

function t_print_summary() {
	if ($GLOBALS['t_reported']) { return; }
	$GLOBALS['t_reported'] = true;
	echo "\n";
	echo '  ' . ($GLOBALS['t_pass'] + $GLOBALS['t_fail']) . ' tests, '
		. $GLOBALS['t_assert'] . ' assertions, '
		. $GLOBALS['t_pass'] . ' passed, '
		. $GLOBALS['t_fail'] . " failed\n";
	if ($GLOBALS['t_fail'] > 0) {
		echo "\n  FAILURES:\n";
		foreach ($GLOBALS['t_failures'] as $f) {
			echo "    - $f\n";
		}
	}
}

function report_and_exit() {
	t_print_summary();
	exit($GLOBALS['t_fail'] > 0 ? 1 : 0);
}

// Attribute an uncatchable death (fatal / set_time_limit kill) to the test that
// was running, so a hang is reported as a failure instead of a bare fatal.
register_shutdown_function(function () {
	if (! empty($GLOBALS['current_test'])) {
		$name = $GLOBALS['current_test'];
		echo "  FAIL  $name\n        process killed mid-test (uncatchable time-limit / DoS regression)\n";
		$GLOBALS['t_fail']++;
		$GLOBALS['t_failures'][] = $name . ': killed mid-test';
		t_print_summary();
		exit(1);
	}
});
