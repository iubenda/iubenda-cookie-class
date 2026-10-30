<?php
/**
 * Regression tests for the restore_noise() DoS fix (3.13.7).
 *
 * Old behaviour: when restore_noise() met a token carrying the prefix but no
 * registered key (unknown key) or a truncated token at end-of-string, it re-emitted
 * the sentinel. strpos() then re-matched the same position on the next pass and the
 * while-loop spun forever while the string grew -> unauthenticated CPU/memory DoS.
 *
 * Fixed behaviour: forward-only offset, no branch re-emits the prefix, and the
 * unknown-key / truncated-token branches leave the original bytes in place.
 *
 * Each test runs under the bootstrap wall-clock guard (SIGALRM, with a
 * set_time_limit() fallback) so a regression FAILS loudly instead of hanging. The
 * inline microtime assertions give a second, softer signal under either mode.
 *
 * Dependency-free port of NoiseDosTerminationTest.php. Run: php noise_dos_termination_test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once LIB_DIR . '/simple_html_dom.php';

echo "NoiseDosTermination (restore_noise terminates, O(n))\n";

run_test('unknown_key_token_is_preserved_and_terminates', function () {
	$dom    = str_get_html('<div>x</div>', true, true, 'UTF-8', false);
	t_is_object($dom);
	$prefix = noise_prefix_of($dom);

	$input   = 'A' . $prefix . '00000' . 'B';
	$start   = microtime(true);
	$out     = $dom->restore_noise($input);
	$elapsed = microtime(true) - $start;

	// Bytes are preserved, not silently deleted, and the prefix is never
	// re-emitted into a position the loop would re-match.
	t_same($input, $out);
	t_less_than(2.0, $elapsed, 'restore_noise() must complete well under the time bound');
});

run_test('truncated_token_at_end_is_preserved_and_terminates', function () {
	$dom    = str_get_html('<div>x</div>', true, true, 'UTF-8', false);
	t_is_object($dom);
	$prefix = noise_prefix_of($dom);

	$input   = 'C' . $prefix;
	$start   = microtime(true);
	$out     = $dom->restore_noise($input);
	$elapsed = microtime(true) - $start;

	t_same($input, $out);
	t_less_than(2.0, $elapsed);
});

run_test('many_prefix_occurrences_all_terminate', function () {
	$dom    = str_get_html('<div>x</div>', true, true, 'UTF-8', false);
	t_is_object($dom);
	$prefix = noise_prefix_of($dom);

	$text    = str_repeat($prefix, 50) . 'zzzzz';
	$start   = microtime(true);
	$out     = $dom->restore_noise($text);
	$elapsed = microtime(true) - $start;

	// None of them maps to a real key, so the text is returned unchanged,
	// and crucially the call terminates instead of spinning.
	t_same($text, $out);
	t_less_than(2.0, $elapsed);
});

run_test('reporter_dos_poc_parses_and_serialises_promptly', function () {
	$start   = microtime(true);
	$html    = str_get_html('<a title="___noise___ 9999">z</a>', true, true, 'UTF-8', false);
	$out     = is_object($html) ? $html->save() : '';
	$elapsed = microtime(true) - $start;

	t_same('<a title="___noise___ 9999">z</a>', $out);
	t_less_than(2.0, $elapsed);
});

report_and_exit();
