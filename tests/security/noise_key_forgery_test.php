<?php
/**
 * Regression tests for the unforgeable per-parse noise-key fix (3.13.7).
 *
 * Old behaviour: remove_noise()/restore_noise() addressed the noise table with a
 * fixed, guessable key ("___noise___" + a space-padded counter restarting at 1000
 * each parse). An unauthenticated commenter could pre-seed a matching key inside an
 * attribute value and have their stashed <code> body (a javascript: URL) substituted
 * in as a live attribute value at render time -> stored XSS.
 *
 * Fixed behaviour: prepare() now builds a random per-parse prefix
 * ("___noise___" + 16 hex chars + " ") so a key an attacker types verbatim can never
 * address the noise table.
 *
 * Dependency-free port of NoiseKeyForgeryTest.php. Run: php noise_key_forgery_test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once LIB_DIR . '/simple_html_dom.php';

echo "NoiseKeyForgery (unforgeable per-parse key)\n";

run_test('two_parses_use_different_unforgeable_prefixes', function () {
	$a = str_get_html('<p>A<code>alpha&body</code></p>', true, true, 'UTF-8', false);
	$b = str_get_html('<p>B<code>beta&body</code></p>', true, true, 'UTF-8', false);

	t_is_object($a);
	t_is_object($b);

	$pa = noise_prefix_of($a);
	$pb = noise_prefix_of($b);

	// Each prefix is the random, per-parse form: ___noise___<16 hex><space>.
	t_matches('/^___noise___[0-9a-f]{16} $/', $pa);
	t_matches('/^___noise___[0-9a-f]{16} $/', $pb);

	// Per-parse randomness: the two prefixes must differ.
	t_not_same($pa, $pb, 'Noise prefixes must be unique per parse');

	// Both documents still restore their stashed <code> bodies byte-for-byte.
	t_same('<p>A<code>alpha&body</code></p>', $a->save());
	t_same('<p>B<code>beta&body</code></p>', $b->save());
});

run_test('forged_key_in_attribute_is_inert_with_code_body', function () {
	$payload = '<code>javascript:alert(1)</code><a href="___noise___ 1000">z</a>';
	$html    = str_get_html($payload, true, true, 'UTF-8', false);
	t_is_object($html);

	$out = $html->save();

	// The attacker key stays literal in the attribute.
	t_contains('href="___noise___ 1000"', $out);

	// The <code> body is preserved verbatim (no content loss).
	t_contains('<code>javascript:alert(1)</code>', $out);

	// Crucially, no live javascript: URL was substituted into an href/src.
	t_not_matches(
		'/(?:href|src)\s*=\s*["\']?\s*javascript:/i',
		$out,
		'Stashed javascript: payload must not be substituted into an attribute'
	);
});

run_test('forged_key_in_attribute_is_inert_without_code_body', function () {
	$html = str_get_html('<a title="___noise___ 1000">z</a>', true, true, 'UTF-8', false);
	t_is_object($html);
	t_same('<a title="___noise___ 1000">z</a>', $html->save());
});

run_test('forged_key_cannot_address_another_parses_noise_table', function () {
	$a        = str_get_html('<p><code>SECRET-A</code></p>', true, true, 'UTF-8', false);
	t_is_object($a);
	$a_prefix = noise_prefix_of($a);

	// Forge A's first-counter token into a document B that has its own noise body.
	$forged = $a_prefix . ' 1000';
	$doc_b  = '<a title="' . $forged . '">z</a><code>SECRET-B</code>';

	$b = str_get_html($doc_b, true, true, 'UTF-8', false);
	t_is_object($b);

	$out      = $b->save();
	$b_prefix = noise_prefix_of($b);

	// Per-parse isolation: distinct prefixes.
	t_not_same($a_prefix, $b_prefix);

	// The forged token stays literal; B's own secret is NOT leaked into the title.
	// (On old code the title would read title="SECRET-B" and this would fail.)
	t_contains('title="' . $forged . '"', $out);
	t_not_contains('title="SECRET-B', $out);

	// B's legitimate <code> body is still restored in its own place.
	t_contains('<code>SECRET-B</code>', $out);
});

run_test('noise_key_can_never_form_an_attribute_name', function () {
	$dom = str_get_html('<p><code>body</code></p>', true, true, 'UTF-8', false);
	t_is_object($dom);

	$prefix = noise_prefix_of($dom);

	// Prefix must end in a space so a key can never be a whole attribute name.
	t_ends_with(' ', $prefix, 'Noise prefix must end in a space');

	// Holds for a padded counter and for an unpadded one past 9999.
	foreach (array(1000, 10000) as $counter) {
		$key = $prefix . sprintf('% 5d', $counter);
		t_matches(
			'/[\s"\'>\/=]/',
			$key,
			"Key for counter {$counter} must contain an attribute-name terminator"
		);
	}

	// The legitimate body still round-trips.
	t_same('<p><code>body</code></p>', $dom->save());
});

report_and_exit();
