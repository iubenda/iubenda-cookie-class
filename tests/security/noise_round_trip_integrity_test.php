<?php
/**
 * Legitimate round-trip integrity: the security fix must not corrupt the bodies the
 * parser legitimately stashes as "noise" (script/style/code/comment/CDATA). These
 * bodies are what cookie-blocking relies on, so they must be restored byte-for-byte.
 * Guards against the offset rewrite breaking nested restoration.
 *
 * Bodies are parsed with stripRN = false (5th positional arg of str_get_html) so the
 * comparison is a true byte-identity check, including embedded newlines.
 *
 * Dependency-free port of NoiseRoundTripIntegrityTest.php. Run: php noise_round_trip_integrity_test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once LIB_DIR . '/simple_html_dom.php';

echo "NoiseRoundTripIntegrity (legitimate noise still round-trips)\n";

$stashed_bodies = array(
	'script with < > &&'      => '<script>var a = 1 < 2 && 3 > b;</script>',
	'script with src+body'    => '<script src="https://cdn.example/x.js">window.x="q";</script>',
	'style pseudo + quotes'   => '<style>.a::before{content:"x";}</style>',
	'code with entities'      => '<code>if (a &lt; b) { return "&amp;"; }</code>',
	'html comment w/ markup'  => '<!-- hello <world> & "friends" -->',
	'cdata w/ angle brackets' => '<![CDATA[ x < y && z > 0 ]]>',
	'multiline script'        => "<script>\n  var a = 1;\n  var b = 2;\n</script>",
	'multiline style'         => "<style>\n.a { color: red; }\n</style>",
);

foreach ($stashed_bodies as $label => $doc) {
	run_test('stashed_body_round_trips_byte_for_byte [' . $label . ']', function () use ($doc) {
		$html = str_get_html($doc, true, true, 'UTF-8', false);
		t_is_object($html);
		t_same($doc, $html->save());
	});
}

run_test('mixed_document_restores_body_and_keeps_structure', function () {
	$doc  = '<div class="wrap">before<code>var u = "a&b";</code>after</div>';
	$html = str_get_html($doc, true, true, 'UTF-8', false);
	t_is_object($html);

	$out = $html->save();
	t_contains('<code>var u = "a&b";</code>', $out);
	t_contains('class="wrap"', $out);
	t_contains('before', $out);
	t_contains('after', $out);
});

report_and_exit();
