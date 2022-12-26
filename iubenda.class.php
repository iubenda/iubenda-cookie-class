<?php
/**
 * iubenda.class.php
 *
 * @author iubenda s.r.l
 * @copyright 2018-2020, iubenda s.r.l
 * @license GNU/GPL
 * @version 4.1.13
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class iubendaParser {

	// variables
	const IUB_REGEX_PATTERN = '/<!--\s*IUB_COOKIE_POLICY_START\s*-->(.*?)<!--\s*IUB_COOKIE_POLICY_END\s*-->/s';
	const IUB_REGEX_PATTERN_2 = '/<!--\s*IUB-COOKIE-BLOCK-START\s*-->(.*?)<!--\s*IUB-COOKIE-BLOCK-END\s*-->/s';
	const IUB_REGEX_PURPOSE_PATTERN = '/<!--\s*IUB-COOKIE-BLOCK-START-PURPOSE-(\d+)\s*-->(.*?)<!--\s*IUB-COOKIE-BLOCK-END-PURPOSE-\d+\s*-->/s';
	const IUB_REGEX_SKIP_PATTERN = '/<!--\s*IUB-COOKIE-BLOCK-SKIP-START\s*-->(.*?)<!--\s*IUB-COOKIE-BLOCK-SKIP-END\s*-->/s';

	// scripts
	public $auto_script_tags = array();

	// iframes
	public $auto_iframe_tags = array();

	// purposes
	public $purposes = array();

	// Listeners to do special handling
	private $observers = array(
		'google-analytics.com/analytics.js' => array(
			'GoogleAnalyticsListener'
		),
		'www.googletagmanager.com/gtag/js' => array(
			'GoogleTagManagerListener'
		)
	);

	// per-purpose scripts
	public $script_tags = array(
		// Strictly necessary
		1 => array(),
		// Basic interactions & functionalities
		2 => array(
			'apis.google.com/js/api.js',
			'cse.google.com/cse.js',
			'loader.engage.gsfn.us/loader.js',
			'headwayapp.co/widget.js',
			'wchat.freshchat.com',
			'widget.uservoice.com',
			'UserVoice.push',
			'static.olark.com/jsclient/loader0.js',
			'cdn.elev.io',
			'paypalobjects.com/js/external/api.js',
			'paypalobjects.com/api/checkout.js'
		),
		// Experience enhancement
		3 => array(
			'apis.google.com/js/plusone.js',
			'apis.google.com/js/client/plusone.js',
			'apis.google.com/js/platform.js',
			'www.youtube.com/iframe_api',
			'youtu.be',
			'platform.twitter.com/widgets.js',
			'instawidget.net/js/instawidget.js',
			'disqus.com/embed.js',
			'platform.linkedin.com/in.js',
			'pinterest.com/js/pinit.js',
			'codepen.io',
            'addthis.com/js/',
			'bat.bing.com',
            'connect.facebook.net'
		),
		// Analytics
		4 => array(
			'sharethis.com/button/buttons.js',
			'scorecardresearch.com/beacon.js',
			'neodatagroup.com',
			'lp4.io',
			'cdn.optimizely.com/js/',
			'cdn.segment.io/analytics.js',
			'cdn.segment.com/analytics.js',
			'i.kissmetrics.com/i.js',
			'cdn.mxpnl.com',
			'rum-static.pingdom.net/prum.min.js',
			'google-analytics.com/analytics.js',
            'www.googletagmanager.com/gtag/js'
		),
		// Targeting & Advertising
		5 => array(
			'googlesyndication.com/pagead/js/adsbygoogle.js',
			'securepubads.g.doubleclick.net/tag/js/gpt.js',
			'googlesyndication.com/pagead/show_ads.js',
			'googleadservices.com/pagead/conversion.js',
			'window.adsbygoogle',
			'static.ads-twitter.com',
			'static.criteo.net/js/',
			'adagionet.com/uploads/js/sipra.js',
			'cdn-wx.rainbowtgx.com/rtgx.js',
			'outbrain.js',
			's.adroll.com',
			'scdn.cxense.com'
		)
	);

	// per-purpose iframes
	public $iframe_tags = array(
		// Strictly necessary
		1 => array(),
		// Basic interactions & functionalities
		2 => array(),
		// Experience enhancement
		3 => array(
			'apis.google.com',
			'maps.google.it/maps',
			'maps.google.com/maps',
			'www.google.com/maps/embed',
			'youtube.com',
			'platform.twitter.com',
			'player.vimeo.com',
			'www.facebook.com/plugins/like.php',
			'www.facebook.com/*/plugins/like.php',
			'www.facebook.com/plugins/likebox.php',
			'www.facebook.com/*/plugins/likebox.php'
		),
		// Analytics
		4 => array(),
		// Targeting & Advertising
		5 => array(
			'window.adsbygoogle',
			'4wnet.com'
		)
	);

	private $type = 'page';
	private $amp = false;
	public $iub_comments_detected = array();
	public $skipped_comments_detected = array();
	public $iframes_skipped = array();
	public $iframes_detected = array();
	public $iframes_converted = array();
	public $scripts_el = array();
	public $scripts_skipped = array();
	public $scripts_detected = array();
	public $scripts_converted = array();
	public $scripts_inline_skipped = array();
	public $scripts_inline_detected = array();
	public $scripts_inline_converted = array();
	private $iub_empty = '//cdn.iubenda.com/cookie_solution/empty.html';
	private $iub_class = '_iub_cs_activate';
	private $iub_class_inline = '_iub_cs_activate-inline';
	private $iub_class_skip = '_iub_cs_skip';

	/**
	 * Construct: the whole HTML output of the page
	 *
	 * @param mixed $content_page
	 * @param array $args
	 */
	public function __construct( $content_page = '', $args = array() ) {
		// valid type?
		$this->type = ! empty( $args['type'] ) && in_array( $args['type'], array( 'page', 'faster' ), true ) ? $args['type'] : 'page';

		// amp support>
		$this->amp = (bool) ( isset( $args['amp'] ) && $args['amp'] === true );

		// load Simple HTML DOM if needed
		if ( ! function_exists( 'file_get_html' ) || ! function_exists( 'str_get_html' ) )
			require_once( dirname( __FILE__ ) . '/simple_html_dom.php' );

		// set content
		$this->original_content_page = $content_page;
		$this->content_page = $content_page;

		// get purposes
		$this->purposes = self::get_purposes();

		// check for additional scripts
		if ( ! empty( $args['scripts'] ) && is_array( $args['scripts'] ) ) {
			// array is not multidimensional, backward compatibility, so block it
			if ( ! is_array( reset( $args['scripts'] ) ) ) {
				$this->auto_script_tags = array_merge( $this->auto_script_tags, $args['scripts'] );
			// array is multidimensional, assign per purpose
			} else {
				// block unassigned script
				if ( array_key_exists( 0, $args['scripts'] ) ) {
					$this->auto_script_tags = array_merge( $this->auto_script_tags, $args['scripts'][0] );
					unset( $args['scripts'][0] );
				}

				$this->script_tags = $this->array_merge_custom( $this->script_tags, $args['scripts'] );
			}
		}

		// check for additional iframes
		if ( ! empty( $args['iframes'] ) && is_array( $args['iframes'] ) ) {
			// array is not multidimensional, backward compatibility, so assign block it
			if ( ! is_array( reset( $args['iframes'] ) ) ) {
				$this->auto_iframe_tags = array_merge( $this->auto_iframe_tags, $args['iframes'] );
			// array is multidimensional, assign per purpose
			} else {
				// block unassigned script
				if ( array_key_exists( 0, $args['iframes'] ) ) {
					$this->auto_iframe_tags = array_merge( $this->auto_iframe_tags, $args['iframes'][0] );
					unset( $args['iframes'][0] );
				}

				$this->iframe_tags = $this->array_merge_custom( $this->iframe_tags, $args['iframes'] );
			}
		}

		// get script tags to block
		$this->auto_script_tags = array_unique( self::get_script_tags() );

		// get iframes tags to block
		$this->auto_iframe_tags = array_unique( self::get_iframe_tags() );
	}

	/**
	 * Static, detect bot & crawler
	 *
	 * @return bool
	 */
	static function bot_detected() {
		return ( isset( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '/bot|crawl|slurp|spider|google|yahoo/i', $_SERVER['HTTP_USER_AGENT'] ) );
	}

	/**
	 * Static, utility function: Return true if the user has already given consent on the page
	 *
	 * @return boolean
	 */
	static function consent_given() {
		$consent_given = false;

		foreach ( $_COOKIE as $key => $value ) {
			$found = self::strpos_array( $key, array( '_iub_cs-s', '_iub_cs' ) );

			if ( $found !== false ) {
				$consent_data = json_decode( stripslashes( $value ), true );

				// read cookie value if given
				if ( isset( $consent_data['consent'] ) && $consent_data['consent'] == true )
					$consent_given = true;

				// read purposes if given
				if ( ! empty( $consent_data['purposes'] ) && is_array( $consent_data['purposes'] ) ) {
					// all purposes accepted, consent given
					if ( ! in_array( false, $consent_data['purposes'] ) )
						$consent_given = true;
				}
			}
		}

		return $consent_given;
	}

	/**
	 * Get user accepted purposes.
	 *
	 * @return array
	 */
	static function get_purposes() {
		$purposes = array();

		if ( ! empty( $_COOKIE ) ) {
			foreach ( $_COOKIE as $key => $value ) {
				$found = self::strpos_array( $key, array( '_iub_cs-s', '_iub_cs' ) );

				if ( $found !== false ) {
					$consent_data = json_decode( $value, true );

					// read purposes if given
					if ( ! empty( $consent_data['purposes'] ) && is_array( $consent_data['purposes'] ) )
						$purposes = $consent_data['purposes'];
				}
			}
		}

		return $purposes;
	}

	/**
	 * Get script tags to be blocked.
	 *
	 * @return array
	 */
	private function get_script_tags() {
		$tags = $this->auto_script_tags;

		foreach ( $this->script_tags as $purpose_id => $tags_list ) {
			// empty tags list, go to another
			if ( empty( $tags_list ) )
				continue;

			// purposes available, filter per purpose
			if ( ! empty( $this->purposes ) ) {
				// don't block scripts unavailable in the user purposes
				// if ( array_key_exists( $purpose_id, $this->purposes ) && $this->purposes[$purpose_id] == false ) {

				// block scripts unavailable in the user purposes
				if ( ! isset( $this->purposes[$purpose_id] ) || $this->purposes[$purpose_id] == false ) {
					foreach ( $tags_list as $tag ) {
						$tags[] = $tag;
					}
				}
			// no purposes yet, just add all scripts
			} else {
				foreach ( $tags_list as $tag ) {
					$tags[] = $tag;
				}
			}
		}

		return $tags;
	}

	/**
	 * Get iframe tags to be blocked.
	 *
	 * @return array
	 */
	private function get_iframe_tags() {
		$tags = $this->auto_iframe_tags;

		foreach ( $this->iframe_tags as $purpose_id => $tags_list ) {
			// empty tags list, go to another
			if ( empty( $tags_list ) )
				continue;

			// purposes available, filter per purpose
			if ( ! empty( $this->purposes ) ) {
				// don't block iframes unavailable in the user purposes
				// if ( array_key_exists( $purpose_id, $this->purposes ) && $this->purposes[$purpose_id] == false ) {

				// block iframes unavailable in the user purposes
				if ( ! isset( $this->purposes[$purpose_id] ) || $this->purposes[$purpose_id] == false ) {
					foreach ( $tags_list as $tag ) {
						$tags[] = $tag;
					}
				}
			// no purposes yet, just add all scripts
			} else {
				foreach ( $tags_list as $tag ) {
					$tags[] = $tag;
				}
			}
		}

		return $tags;
	}

	/**
	 * Convert scripts, iframe and other code inside IUBENDAs comment in text/plain to not generate cookies
	 *
	 * @param mixed $content
	 * @return mixed
	 */
	public function create_tags( $content, $args ) {
		$elements = $content->find( "*" );
		$js = '';

		if ( is_array( $elements ) ) {
			$count = count( $elements );

			for ( $j = 0; $j < $count; $j++ ) {
				$e = $elements[$j];

				switch ( $e->tag ) {
					case 'script':
						if ( $args['pattern'] === 'IUB_REGEX_PURPOSE_PATTERN' )
							$e->{'data-iub-purposes'} = $args['number'];

						// AMP support
						if ( $this->amp )
							$e->{'data-block-on-consent'} = '_till_accepted';

						$class = $e->class;
						$e->class = $class . ' ' . $this->iub_class;
						$e->type = 'text/plain';
						$js .= $e->outertext;
						break;

					case 'iframe':
						if ( $args['pattern'] === 'IUB_REGEX_PURPOSE_PATTERN' )
							$e->{'data-iub-purposes'} = $args['number'];

						// AMP support
						if ( $this->amp )
							$e->{'data-block-on-consent'} = '_till_accepted';

						$new_src = $this->iub_empty;
						$class = $e->class;
						$e->suppressedsrc = $e->src;
						$e->src = $new_src;
						$e->class = $class . ' ' . $this->iub_class;
						$js .= $e->outertext;
						break;

					default:
						$js .= $e->outertext;
						break;
				}
			}
		}

		return $js;
	}

	/**
	 * Skip scripts and iframes inside IUBENDAs comments.
	 *
	 * @param string $content
	 * @return string
	 */
	public function skip_tags( $content ) {
		$elements = $content->find( "*" );
		$js = '';

		if ( is_array( $elements ) ) {
			$count = count( $elements );

			for ( $j = 0; $j < $count; $j++ ) {
				$element = $elements[$j];

				switch ( $element->tag ) {
					case 'script':
					case 'iframe':
						$class = trim( $element->class ?: '' );
						$element->class = ( $class !== '' ? $class . ' ' : '' ) . $this->iub_class_skip;
						$js .= $element->outertext;
						break;

					default:
						$js .= $element->outertext;
						break;
				}
			}
		}

		return $js;
	}

	/**
	 * Parse automatically all the scripts in the page and converts it in text/plain
	 * if src or the whole output has inside one of the elements in $auto_script_tags array
	 *
	 * @return void
	 */
	public function parse_scripts() {
		switch ( $this->type ) {
			case 'page':
				// get page contents
				$html = str_get_html( $this->content_page, true, true, false );

				if ( is_object( $html ) ) {
					// get scripts
					$scripts = $html->find( 'script' );

					if ( is_array( $scripts ) ) {

						$this->scripts_el = $scripts;
						$count = count( $scripts );
						$class_skip = $this->iub_class_skip;

						// loop through scripts
						for ( $j = 0; $j < $count; $j ++  ) {
							$s = $scripts[$j];
							$script_class = trim( $s->class ?: '' );

							if ( $script_class !== '' ) {
								$classes = explode( ' ', $script_class );

								if ( in_array( $class_skip, $classes, true ) ) {
									// add script as skipped
									if ( ! empty( $s->innertext ) )
										$this->scripts_inline_skipped[] = $s->innertext;
									else
										$this->scripts_skipped[] = $s->src;

									continue;
								}
							}

							if ( ! empty( $s->innertext ) ) {
								$this->scripts_inline_detected[] = $s->innertext;

								$found = self::strpos_array( $s->innertext, $this->auto_script_tags );

								if ( $found !== false ) {
									$class = $s->class;
									$s->class = $class . ' ' . $this->iub_class_inline;
									$s->type = 'text/plain';
									$this->scripts_inline_converted[] = $s->innertext;

									// add data-iub-purposes attribute
									$this->set_purpose($s, $found);

									# Run observers
									$this->run_observers( $found, $s );
								}
							} else {
								$src = $s->src;

								if ( $src ) {
									$this->scripts_detected[] = $src;

									$found = self::strpos_array( $src, $this->auto_script_tags );

									if ( $found !== false ) {
										$class = $s->class;
										$s->class = $class . ' ' . $this->iub_class;
										$s->type = 'text/plain';

										// add data-iub-purposes attribute
										$this->set_purpose($s, $found);

										// AMP support
										if ( $this->amp )
											$s->{'data-block-on-consent'} = '_till_accepted';

										// Run observers
										$this->run_observers( $found, $s );

										$this->scripts_converted[] = $src;
									}
								}
							}
						}
					}

					// AdSense check by Peste Vasile Alexandru, AdSense here
					$ad_found = false;

					while ( preg_match( "#google_ad_client =(.*?);#i", $html ) ) {
						$ad_found = true;
						$ad_client = null;
						$ad_slot = null;
						$ad_width = null;
						$ad_height = null;
						$ad_block = null;

						preg_match( "#google_ad_client =(.*?);#i", $html, $ad_client );
						preg_match( "#google_ad_slot =(.*?);#i", $html, $ad_slot );
						preg_match( "#google_ad_width =(.*?);#i", $html, $ad_width );
						preg_match( "#google_ad_height =(.*?);#i", $html, $ad_height );

						$html = preg_replace( "#google_ad_client =(.*?);#i", "", $html, 1 );
						$html = preg_replace( "#google_ad_slot =(.*?);#i", "", $html, 1 );
						$html = preg_replace( "#google_ad_width =(.*?);#i", "", $html, 1 );
						$html = preg_replace( "#google_ad_height =(.*?);#i", "", $html, 1 );

						$ad_client = trim( $ad_client[1] ?: '' );
						$ad_slot = trim( $ad_slot[1] ?: '' );
						$ad_width = trim( $ad_width[1] ?: '' );
						$ad_height = trim( $ad_height[1] ?: '' );

						$ad_class = 'class="' . $this->iub_class . '_google_ads"';
						$ad_style = 'style="width:' . $ad_width . 'px; height:' . $ad_height . 'px;"';

						$ad_client = 'data-client=' . $ad_client;
						$ad_slot = 'data-slot=' . $ad_slot;
						$ad_width = 'data-width="' . $ad_width . '"';
						$ad_height = 'data-height="' . $ad_height . '"';

						$ad_block = "<div $ad_style $ad_class $ad_width $ad_height $ad_slot $ad_client></div>";

						$html = preg_replace( '#(<[^>]+) src="//pagead2.googlesyndication.com/pagead/show_ads.js"(.*?)</script>#i', $ad_block, $html, 1 );
					}

					if ( $ad_found ) {
						$adsense_callback = "
						<script>
							function iubenda_adsense_unblock() {
								var t = 1;
								jQuery('." . $this->iub_class . "_google_ads').each(function() {
									var banner = jQuery(this);
									setTimeout(function(){
										var client = banner.data('client');
										var slot = banner.data('slot');
										var width = banner.data('width');
										var height = banner.data('height');
										var adsense_script = '<scr'+'ipt>'
												+ 'google_ad_client = " . chr( 34 ) . "'+client+'" . chr( 34 ) . ";'
												+ 'google_ad_slot = '+slot+';'
												+ 'google_ad_width = '+width+';'
												+ 'google_ad_height = '+height+';'
												+ '</scr'+'ipt>';
										var script = document.createElement('script');
										var ads = document.createElement('ads');
										var w = document.write;
										script.setAttribute('type', 'text/javascript');
										script.setAttribute('src', 'http://pagead2.googlesyndication.com/pagead/show_ads.js');
										document.write = (function(params) {
											ads.innerHTML = params;
											document.write = w;
										});
										banner.html(adsense_script).append(ads).append(script);
									}, t);
									t += 300;
								});
							}

							if ( 'callback' in _iub.csConfiguration ) {
								_iub.csConfiguration.callback.onConsentGiven = iubenda_adsense_unblock;
							} else {
								_iub.csConfiguration.callback = {};

								_iub.csConfiguration.callback.onConsentGiven = iubenda_adsense_unblock;
							}
						</script>";

						$html = str_replace( '</body>', $adsense_callback . '</body>', $html );
					}

					$this->content_page = $html;
				}
				break;

			case 'faster':
				libxml_use_internal_errors( true );

				// get class attributes for better performance
				$script_tags = $this->auto_script_tags;
				$class = $this->iub_class;
				$class_inline = $this->iub_class_inline;
				$class_skip = $this->iub_class_skip;

				// create new DOM document
				$document = new DOMDocument();

				// set document arguments
				$document->formatOutput = true;
				$document->preserveWhiteSpace = false;

				// load HTML
				$document->loadHTML( $this->content_page );

				// search for scripts
				$scripts = $document->getElementsByTagName( 'script' );
				$this->scripts_el = $scripts;
				// any scripts?
				if ( ! empty( $scripts ) && is_object( $scripts ) ) {
					foreach ( $scripts as $script ) {
						$src = $script->getAttribute( 'src' );
						$script_class = trim( $script->getAttribute( 'class' ) ?: '' );

						if ( $script_class !== '' ) {
							$classes = explode( ' ', $script_class );

							if ( in_array( $class_skip, $classes, true ) ) {
								// add script as skipped
								if ( ! empty( $src ) )
									$this->scripts_skipped[] = $src;

								// add inline script as skipped
								if ( ! empty( $script->nodeValue ) )
									$this->scripts_inline_skipped[] = $script->nodeValue;

								continue;
							}
						}

						// add script as detected
						if ( ! empty( $src ) )
							$this->scripts_detected[] = $src;

						// add inline script as detected
						if ( ! empty( $script->nodeValue ) )
							$this->scripts_inline_detected[] = $script->nodeValue;

						$found = self::strpos_array( $src, $script_tags );
						$found_inline = self::strpos_array( $script->nodeValue, $script_tags );

						if ( $found !== false ) {
							$script->setAttribute( 'type', 'text/plain' );
							$script->setAttribute( 'class', $script->getAttribute( 'class' ) . ' ' . $class );

							// add data-iub-purposes attribute
							$this->set_purpose( $script, $found );

							// AMP support
							if ( $this->amp )
								$script->setAttribute( 'data-block-on-consent', '_till_accepted' );

							// Run observers
							$this->run_observers( $found, $script );

							// add script as converted
							$this->scripts_converted[] = $src;
						} elseif ( $found_inline !== false ) {
							$script->setAttribute( 'type', 'text/plain' );
							$script->setAttribute( 'class', $script->getAttribute( 'class' ) . ' ' . $class_inline );

							// AMP support
							if ( $this->amp )
								$script->setAttribute( 'data-block-on-consent', '_till_accepted' );

							// add data-iub-purposes attribute
							$this->set_purpose($script, $found_inline);

							// Run observers
							$this->run_observers( $found_inline, $script );

							// add inline script as converted
							$this->scripts_inline_converted[] = $script->nodeValue;
						}
					}
				}

				// save document content
				$content = $document->saveHTML();

				libxml_use_internal_errors( false );

				// update content
				$this->content_page = $content;
				break;
		}
	}

	/**
	 * Parse automatically all the iframe in the page and change the src to suppressedsrc
	 * if src has inside one of the elements in $auto_iframe_tags array
	 *
	 * @return void
	 */
	public function parse_iframes() {
		switch ( $this->type ) {
			case 'page':
				$html = str_get_html( $this->content_page, true, true, false );

				if ( is_object( $html ) ) {
					$iframes = $html->find( 'iframe' );

					if ( is_array( $iframes ) ) {
						$count = count( $iframes );
						$class_skip = $this->iub_class_skip;

						for ( $j = 0; $j < $count; $j ++  ) {
							$i = $iframes[$j];
							$iframe_class = trim( $i->class ?: '' );

							if ( $iframe_class !== '' ) {
								$classes = explode( ' ', $iframe_class );

								if ( in_array( $class_skip, $classes, true ) ) {
									// add iframe as skipped
									$this->iframes_skipped[] = $i->src;

									continue;
								}
							}

							$src = $i->src;
							$this->iframes_detected[] = $src;

							$found = self::strpos_array( $src, $this->auto_iframe_tags );

							if ( $found !== false ) {
								$class = $i->class;
								$i->suppressedsrc = $src;
								$i->src = $this->iub_empty;
								$i->class = $class . ' ' . $this->iub_class;

								// add data-iub-purposes attribute
								$i->{'data-iub-purposes'} = $this->recursive_array_search( $found, $this->iframe_tags );

								// AMP support
								if ( $this->amp )
									$i->{'data-block-on-consent'} = '_till_accepted';

								$this->iframes_converted[] = $src;
							}
						}
					}

					$this->content_page = $html;
				}
				break;

			case 'faster':
				libxml_use_internal_errors( true );

				// get class attributes for better performance
				$iframe_tags = $this->auto_iframe_tags;
				$empty = $this->iub_empty;
				$class = $this->iub_class;
				$class_skip = $this->iub_class_skip;

				// create new DOM document
				$document = new DOMDocument();

				// set document arguments
				$document->formatOutput = true;
				$document->preserveWhiteSpace = false;

				// load HTML
				$document->loadHTML( $this->content_page );

				// search for iframes
				$iframes = $document->getElementsByTagName( 'iframe' );

				// any iframes?
				if ( ! empty( $iframes ) && is_object( $iframes ) ) {
					foreach ( $iframes as $iframe ) {
						$src = $iframe->getAttribute( 'src' );
						$iframe_class = trim( $iframe->getAttribute( 'class' ) ?: '' );

						if ( $iframe_class !== '' ) {
							$classes = explode( ' ', $iframe_class );

							if ( in_array( $class_skip, $classes, true ) ) {
								// add iframe as skipped
								$this->iframes_skipped[] = $src;

								continue;
							}
						}

						// add iframe as detected
						$this->iframes_detected[] = $src;

						$found = self::strpos_array( $src, $iframe_tags );

						if ( $found !== false ) {
							$iframe->setAttribute( 'src', $empty );
							$iframe->setAttribute( 'suppressedsrc', $src );
							$iframe->setAttribute( 'class', $iframe_class . ' ' . $class );

							// per purpose, add data-iub-purposes attribute
							$iframe->setAttribute( 'data-iub-purposes', $this->recursive_array_search( $found, $this->iframe_tags ) );

							// AMP support
							if ( $this->amp )
								$iframe->setAttribute( 'data-block-on-consent', '_till_accepted' );

							// add iframe as converted
							$this->iframes_converted[] = $src;
						}
					}
				}

				// save document content
				$content = $document->saveHTML();

				libxml_use_internal_errors( false );

				// update content
				$this->content_page = $content;
				break;
		}
	}

	/**
	 * Parse all IUBENDAs comments.
	 *
	 * @return void
	 */
	public function parse_comments() {
		// skip
		preg_match_all( constant( 'self::IUB_REGEX_SKIP_PATTERN' ), $this->content_page, $scripts );

		// found any content?
		if ( is_array( $scripts[1] ) ) {
			$count = count( $scripts[1] );
			$js_scripts = array();

			for ( $j = 0; $j < $count; $j++ ) {
				$this->skipped_comments_detected[] = $scripts[1][$j];

				// get HTML dom from string
				$html = str_get_html( $scripts[1][$j], true, true, false );

				// skip scripts and iframes inside iubenda's comments
				$js_scripts[] = $this->skip_tags( $html );
			}

			if ( ( is_array( $scripts[1] ) && is_array( $js_scripts ) ) && ( $count >= 1 && count( $js_scripts ) >= 1 ) )
				$this->content_page = strtr( $this->content_page, array_combine( $scripts[1], $js_scripts ) );
		}

		unset( $scripts );

		// block
		foreach ( array( 'IUB_REGEX_PATTERN', 'IUB_REGEX_PATTERN_2', 'IUB_REGEX_PURPOSE_PATTERN' ) as $pattern ) {
			preg_match_all( constant( 'self::' . $pattern ), $this->content_page, $scripts );

			$chunks = array();
			$args = array(
				'pattern' => $pattern
			);

			if ( $pattern === 'IUB_REGEX_PURPOSE_PATTERN' ) {
				$numbers = $scripts[1];
				$chunks = $scripts[2];
			} else
				$chunks = $scripts[1];

			// found any content?
			if ( is_array( $chunks ) ) {
				$count = count( $chunks );
				$js_scripts = array();

				for ( $j = 0; $j < $count; $j++ ) {
					$this->iub_comments_detected[] = $chunks[$j];

					// get HTML dom from string
					$html = str_get_html( $chunks[$j], true, true, false );

					if ( $pattern === 'IUB_REGEX_PURPOSE_PATTERN' )
						$args['number'] = $numbers[$j];

					// convert scripts, iframes and other code inside IUBENDAs comment in text/plain to not generate cookies
					$js_scripts[] = $this->create_tags( $html, $args );
				}

				if ( ( is_array( $chunks ) && is_array( $js_scripts ) ) && ( $count >= 1 && count( $js_scripts ) >= 1 ) )
					$this->content_page = strtr( $this->content_page, array_combine( $chunks, $js_scripts ) );
			}
		}
	}

	/**
	 * Call three methods to parse the page, iubendas comment, scripts and iframes
	 *
	 * @return string Content
	 */
	public function parse() {
		$this->parse_comments();
		$this->parse_scripts();
		$this->parse_iframes();

		return $this->content_page;
	}

	/**
	 * Return the final page to output
	 *
	 * @return mixed
	 */
	public function get_converted_page() {
		return $this->content_page;
	}

	/**
	 * Print iubenda banner, parameter: the script code of iubenda to print the banner
	 *
	 * @param string $banner
	 * @return string
	 */
	public function print_banner( $banner ) {
		return $banner .= "\n
			<script>
				var iCallback = function(){};

				if ( 'callback' in _iub.csConfiguration ) {
					if ( 'onConsentGiven' in _iub.csConfiguration.callback )
						iCallback = _iub.csConfiguration.callback.onConsentGiven;

					_iub.csConfiguration.callback.onConsentGiven = function() {
						iCallback();

						jQuery( 'noscript._no_script_iub' ).each( function (a, b) { var el = jQuery(b); el.after( el.html() ); } );
					};
				};
			</script>";
	}

	/**
	 * Static, utility function: strpos for array wilth wildcard support
	 *
	 * @param type $haystack
	 * @param type $needle
	 * @return boolean
	 */
	static function strpos_array( $haystack, $needle ) {
		if ( empty( $haystack ) || empty( $needle ) )
			return false;

		$needle = ! is_array( $needle ) ? array( $needle ) : $needle;

		foreach ( $needle as $need ) {
			// wildcard?
			if ( strpos( $need, '/*/' ) !== false ) {
				// strtok - removes query string
				// str_replace - removes double slashes // from url
				// preg_replace - removes http or https from url
				$haystack = strtok( str_replace( '//', '', preg_replace( "(^https?://)", "", $haystack ) ), '?' );

				if ( fnmatch( $need, $haystack ) !== false )
					return $need;
			// regular
			} else {
				if ( strpos( $haystack, $need ) !== false )
					return $need;
			}
		}

		return false;
	}

	/**
	 * Custom array merge helper function.
	 *
	 * @return array
	 */
	public function array_merge_custom( $builtin, $data ) {
		foreach ( $data as $type => $array ) {
			// if ( $type === 0 )
				// continue;

			foreach ( $array as $block ) {
				$builtin[$type][] = $block;
			}

			$builtin[$type] = array_unique( $builtin[$type] );
		}

		return $builtin;
	}

	/**
	 * Array search helper function.
	 *
	 * @param type $needle
	 * @param type $haystack
	 * @return boolean
	 */
	public function recursive_array_search( $needle, $haystack ) {
		foreach ( $haystack as $key => $value ) {
			$current_key = $key;
			if ( $needle === $value OR ( is_array( $value ) &&
			$this->recursive_array_search( $needle, $value ) !== false) ) {
				return $current_key;
			}
		}
		return false;
	}

	/**
	 * Get the activate classes
	 *
	 * @return array
	 */
	public function get_activate_classes() {
		return array( $this->iub_class, $this->iub_class_inline );
	}

	/**
	 * @param string $link
	 * @param DOMElement $script
	 */
	private function run_observers( $link, $script ) {
		# Escape if there is no defined observer for link
		if ( ! isset( $this->observers[ $link ] ) ) {
			return;
		}

		# Loop on script listeners
		foreach ( $this->observers[ $link ] as $class ) {
			require_once "listeners/{$class}.php";
			$listener_instance = new $class( $script, $this );
			$listener_instance->handle();
		}
	}

	/**
	 * Set purpose on script tag if not exist
	 *
	 * @param $script
	 * @param $url
	 */
	private function set_purpose( $script, $url ) {
		if ( ! $script->hasAttribute( 'data-iub-purposes' ) ) {
			$script->setAttribute( 'data-iub-purposes', $this->recursive_array_search( $url, $this->script_tags ) );
		}
	}
}
