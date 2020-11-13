<?php

class GoogleAnalyticsListener {

	/**
	 * @var DOMElement
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
	private $GA_anonymize_pattern = '~^(?:(?!//).)*?(ga.+(set)\b.+(,).+(anonymizeIp)\b.+(,).+(true|[1]{1})\b)(?!(?:(?!/\*)[\s\S])*\*/)~mi';

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
		if ( $this->script instanceof simple_html_dom_node ) {
			$str = $this->script->innertext;
		} else {
			$str = $this->script->nodeValue;
		}

		# if the GA is anonymized then unblock it
		if ( preg_match( $this->GA_anonymize_pattern, $str ) ) {
			$this->unblock_script();

			return;
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
		# Remove AMP support
		$this->script->removeAttribute( 'data-block-on-consent' );
		$this->script->removeAttribute( 'data-iub-purposes' );
	}
}