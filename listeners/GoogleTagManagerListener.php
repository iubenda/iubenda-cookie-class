<?php

class GoogleTagManagerListener {

	/**
	 * @var DOMElement|simple_html_dom_node
	 */
	private $script;

	/**
	 * @var iubendaParser
	 */
	private $iub_parser;

	/**
	 * Pattern to detect the anonymize configuration
	 *
	 * @var string
	 */
	private $gtag_anonymize_pattern = '~^(?:(?!//).)*?(gtag.+(config)\b.+(,).+(,).+({).+(anonymize_ip)\b.+(:).+(true|[1]{1})\b)(?!(?:(?!/\*)[\s\S])*\*/)~mi';

	/**
	 * GoogleAnalyticsListener constructor.
	 *
	 * @param DOMElement|simple_html_dom_node $script
	 * @param iubendaParser $iub_parser
	 */
	public function __construct( $script, $iub_parser ) {
		$this->script     = $script;
		$this->iub_parser = $iub_parser;
	}

	/**
	 * Special handling for enabled anonymizeIP flag
	 */
	public function handle() {
		# Loop on all scripts
		foreach ( $this->iub_parser->scripts_el as $script ) {
			if ( $this->script instanceof simple_html_dom_node ) {
				$str = $script->innertext;
			} else {
				$str = $script->nodeValue;
			}

			# Avoid non inline-scripts
			if ( ! trim( $str  ?: '' ) ) {
				continue;
			}

			# Match the gtag anonymize pattern
			if ( preg_match( $this->gtag_anonymize_pattern, $str ) ) {
				$this->unblock_script();
				break;
			}
		}
	}

	/**
	 * Unblock script and reset to the original
	 */
	private function unblock_script() {
		$classes = array_filter( explode( ' ', $this->script->getAttribute( 'class' ) ) );
		$flip    = array_flip( $classes );

		# Loop on iub activate classes and remove them
		foreach ( $this->iub_parser->get_activate_classes() as $val ) {
			if ( isset( $flip[ $val ] ) ) {
				unset( $flip[ $val ] );
			}
		}
		$classes = implode( ' ', array_flip( $flip ) );
		# Reset everything to original
		$this->script->setAttribute( 'type', 'text/javascript' );
		$this->script->setAttribute( 'class', $classes );
		$this->script->removeAttribute( 'data-iub-purposes' );

		# Remove AMP support
		$this->script->removeAttribute( 'data-block-on-consent' );
	}
}