<?php
/**
 * Regression tests for the makeup() attribute-serialisation hardening (3.13.7).
 *
 * An attribute value must never be able to terminate its own quoting. restore_noise()
 * can reinsert attacker-controlled text (containing a quote character) into an
 * attribute value before serialisation; makeup() is the backstop that escapes the
 * quote in use (" -> &quot;, ' -> &#039;) and forces double-quoting for unquoted
 * attributes, so a value cannot break out and inject live markup.
 *
 * Dependency-free port of MakeupAttributeEscapingTest.php. Run: php makeup_attribute_escaping_test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once LIB_DIR . '/simple_html_dom.php';

echo "MakeupAttributeEscaping (attribute values cannot break out)\n";

run_test('double_quote_in_value_is_escaped', function () {
	$html = str_get_html('<a title="x">z</a>', true, true, 'UTF-8', false);
	$node = $html->find('a', 0);
	$node->title = 'ev"il';

	$out = $node->outertext;
	t_same('<a title="ev&quot;il">z</a>', $out);
	t_not_contains('title="ev"il"', $out);
});

run_test('single_quote_in_value_is_escaped', function () {
	$html = str_get_html("<a title='x'>z</a>", true, true, 'UTF-8', false);
	$node = $html->find('a', 0);
	$node->title = "ev'il";

	$out = $node->outertext;
	t_same("<a title='ev&#039;il'>z</a>", $out);
});

run_test('unquoted_attribute_is_force_quoted', function () {
	$html = str_get_html('<a title=x>z</a>', true, true, 'UTF-8', false);
	$node = $html->find('a', 0);
	$node->title = 'a"b';

	$out = $node->outertext;
	t_contains('title="a&quot;b"', $out);
});

run_test('restored_quote_cannot_break_out_of_double_quoted_attribute', function () {
	$payload = '<a title="<!-- x" onmouseover="alert(1)//-->">z</a>';
	$html    = str_get_html($payload, true, true, 'UTF-8', false);
	t_is_object($html);

	$out = $html->save();

	// Noise restored INTO an attribute value is treated as untrusted and
	// HTML-encoded for the attribute context, so the whole breakout body is
	// trapped inside the title value.
	t_same('<a title="&lt;!-- x&quot; onmouseover=&quot;alert(1)//--&gt;">z</a>', $out);

	// The dangerous quote inside the title value is neutralised.
	t_contains('&quot;', $out);

	// Readable negative: no LIVE onmouseover attribute (value escaped, not a raw quote).
	t_not_matches(
		'/\sonmouseover\s*=\s*"[^&]/i',
		$out,
		'A live onmouseover handler must not escape the attribute value'
	);
});

run_test('restored_quote_cannot_break_out_of_single_quoted_attribute', function () {
	$payload = "<a title='<!-- x' onmouseover='alert(1)//-->'>z</a>";
	$html    = str_get_html($payload, true, true, 'UTF-8', false);
	t_is_object($html);

	$out = $html->save();

	t_same("<a title='&lt;!-- x&#039; onmouseover=&#039;alert(1)//--&gt;'>z</a>", $out);
	t_contains('&#039;', $out);
	t_not_matches(
		"/\sonmouseover\s*=\s*'[^&]/i",
		$out,
		'A live onmouseover handler must not escape the single-quoted attribute value'
	);
});

report_and_exit();
