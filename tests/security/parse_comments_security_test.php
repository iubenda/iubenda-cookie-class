<?php
/**
 * End-to-end security tests for iubendaParser::parse_comments(), the unauthenticated
 * entry point for blog-comment content wrapped in IUB-COOKIE-BLOCK markers.
 *
 * These feed the reporter PoC bodies through the real marker-processing path and
 * assert the 3.13.7 fixes hold: no stashed javascript: payload substituted into an
 * attribute, no live event handler produced, and the DoS PoCs serialise promptly. A
 * positive test proves cookie-blocking still works (scripts neutralised to
 * text/plain with the activation class) so the fix did not regress functionality.
 *
 * The iubendaParser only requires PHP built-ins and simple_html_dom on this path, so
 * no WordPress stubs are needed.
 *
 * Dependency-free port of ParseCommentsSecurityTest.php. Run: php parse_comments_security_test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once LIB_DIR . '/simple_html_dom.php';
require_once LIB_DIR . '/iubenda.class.php';

echo "ParseCommentsSecurity (end-to-end through the iubenda layer)\n";

/** Run parse_comments() and return the resulting content_page. */
function run_parse_comments($content) {
	$parser = new iubendaParser($content);
	$parser->parse_comments();

	$ref  = new ReflectionObject($parser);
	$prop = $ref->getProperty('content_page');
	$prop->setAccessible(true);
	return (string) $prop->getValue($parser);
}

function iub_wrap($body) {
	return '<!-- IUB-COOKIE-BLOCK-START -->' . $body . '<!-- IUB-COOKIE-BLOCK-END -->';
}

run_test('xss_poc_does_not_inject_javascript_href', function () {
	$out = run_parse_comments(
		iub_wrap('<code>javascript:alert(1)</code><a href="___noise___ 1000">z</a>')
	);

	// No live javascript: URL in any href/src (javascript: legitimately appears
	// inside the <code> text, so match only attribute positions).
	t_not_matches('/(?:href|src)\s*=\s*["\']?\s*javascript:/i', $out);

	// No live event handler or autofocus attribute anywhere.
	t_not_matches('/\s(?:on[a-z]+|autofocus)\s*=?/i', $out);

	// The forged key stays literal; the code body is untouched.
	t_contains('href="___noise___ 1000"', $out);
	t_contains('<code>javascript:alert(1)</code>', $out);

	// Marker delimiters are preserved.
	t_contains('<!-- IUB-COOKIE-BLOCK-START -->', $out);
	t_contains('<!-- IUB-COOKIE-BLOCK-END -->', $out);
});

run_test('handler_breakout_poc_has_no_live_event_attribute', function () {
	$out = run_parse_comments(
		iub_wrap('<a title="<!-- x" onmouseover="alert(1)//-->">z</a>')
	);

	// No live event handler assignment (value must be escaped, not a raw quote).
	t_not_matches('/\son[a-z]+\s*=\s*"[^&]/i', $out);
	t_contains('&quot;', $out);
});

run_test('dos_poc_terminates_promptly', function () {
	$start   = microtime(true);
	$out     = run_parse_comments(iub_wrap('<a title="___noise___ 9999">z</a>'));
	$elapsed = microtime(true) - $start;

	t_less_than(2.0, $elapsed);
	t_contains('title="___noise___ 9999"', $out);
});

run_test('patchstack_dos_poc_terminates_promptly', function () {
	// Patchstack DoS PoC (researcher Dthangws), verbatim: unspaced markers, 5-digit
	// key, no space. On 3.13.6 this held a PHP worker for the full max_execution_time.
	$payload = '<!--IUB-COOKIE-BLOCK-START--><b>___noise___99999</b><!--IUB-COOKIE-BLOCK-END-->';

	$start   = microtime(true);
	$out     = run_parse_comments('<div class="comment">Mallory said: ' . $payload . '</div>');
	$elapsed = microtime(true) - $start;

	t_less_than(2.0, $elapsed);
	t_contains('___noise___99999', $out);
});

run_test('patchstack_marker_free_vector_terminates_promptly', function () {
	// Patchstack "Vector 2": the bare token needs no markers to reach the parser.
	$start   = microtime(true);
	$out     = run_parse_comments('<p>Nice post ___noise___99999 cheers</p>');
	$elapsed = microtime(true) - $start;

	t_less_than(2.0, $elapsed);
	t_contains('___noise___99999', $out);
});

run_test('script_inside_marker_is_neutralised_for_blocking', function () {
	// Positive cookie-blocking test: a real tracking script inside the markers must be
	// neutralised to type="text/plain" with the activation class, body preserved.
	$out = run_parse_comments(
		iub_wrap('<script src="https://x/y.js">var a="q";</script>')
	);

	t_contains('type="text/plain"', $out);
	t_contains('_iub_cs_activate', $out);
	t_contains('src="https://x/y.js"', $out);
	t_contains('var a="q";', $out);
	t_contains('<!-- IUB-COOKIE-BLOCK-START -->', $out);
	t_contains('<!-- IUB-COOKIE-BLOCK-END -->', $out);
});

report_and_exit();
