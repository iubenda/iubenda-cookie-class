<?php
/**
 * iubenda.class.page.php
 *
 * @author iubenda s.r.l
 * @copyright 2018-2020, iubenda s.r.l
 * @license GNU/GPL
 * @version 1.0.3
 * @deprecated
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

class iubendaPage {

	// variables
	const IUB_REGEX_PATTERN = '/<!--\s*IUB_COOKIE_POLICY_START\s*-->(.*?)<!--\s*IUB_COOKIE_POLICY_END\s*-->/s';
	const IUB_REGEX_PATTERN_2 = '/<!--\s*IUB-COOKIE-BLOCK-START\s*-->(.*?)<!--\s*IUB-COOKIE-BLOCK-END\s*-->/s';

	public $auto_script_tags = array(
		'platform.twitter.com/widgets.js',
		'apis.google.com/js/plusone.js',
		'apis.google.com/js/platform.js',
		'connect.facebook.net',
		'www.youtube.com/iframe_api',
		'pagead2.googlesyndication.com/pagead/js/adsbygoogle.js',
		'securepubads.g.doubleclick.net/tag/js/gpt.js',
		'sharethis.com/button/buttons.js',
		'addthis.com/js/',
		'window.adsbygoogle'
	);
	public $auto_iframe_tags = array(
		'youtube.com',
		'platform.twitter.com',
		'www.facebook.com/plugins/like.php',
		'www.facebook.com/plugins/likebox.php',
		'apis.google.com',
		'www.google.com/maps/embed/',
		'player.vimeo.com/video',
		'maps.google.it/maps',
		'www.google.com/maps/embed',
		'window.adsbygoogle'
	);
	public $iub_comments_detected = array();
	public $iframe_detected = array();
	public $iframe_converted = array();
	public $scripts_detected = array();
	public $scripts_inline_detected = array();
	public $scripts_inline_converted = array();
	public $scripts_converted = array();

	/**
	 * Construct: the whole HTML output of the page
	 *
	 * @param mixed $content_page
	 */
	public function __construct( $content_page ) {
		$this->original_content_page = $content_page;
		$this->content_page = $content_page;
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

if('callback' in _iub.csConfiguration) {
        if('onConsentGiven' in _iub.csConfiguration.callback) iCallback = _iub.csConfiguration.callback.onConsentGiven;

        _iub.csConfiguration.callback.onConsentGiven = function()
        {
                iCallback();

                /*
                 * Separator
                */

                jQuery('noscript._no_script_iub').each(function (a, b) { var el = jQuery(b); el.after(el.html()); });
        };
};
				</script>";
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
		foreach ( $_COOKIE as $key => $value ) {
			if ( iubendaPage::strpos_array( $key, array( '_iub_cs-s', '_iub_cs' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Static, utility function: strpos for array
	 *
	 * @param type $haystack
	 * @param type $needle
	 * @return boolean
	 */
	static function strpos_array( $haystack, $needle ) {
		if ( is_array( $needle ) ) {
			foreach ( $needle as $need ) {
				if ( strpos( $haystack, $need ) !== false ) {
					return true;
				}
			}
		} else {
			if ( strpos( $haystack, $need ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Convert scripts, iframe and other code inside IUBENDAs comment in text/plain to not generate cookies
	 *
	 * @param mixed $html
	 * @return mixed
	 */
	public function create_tags( $html ) {

		$elements = $html->find( "*" );
		$js = '';

		if ( is_array( $elements ) ) {
			$count = count( $elements );
			for ( $j = 0; $j < $count; $j ++  ) {
				$e = $elements[$j];
				switch ( $e->tag ) {
					case 'script':
						$class = $e->class;
						$e->class = $class . ' _iub_cs_activate';
						$e->type = 'text/plain';
						$js .= $e->outertext;
						break;

					case 'iframe':
						$new_src = "//cdn.iubenda.com/cookie_solution/empty.html";
						$class = $e->class;
						$e->suppressedsrc = $e->src;
						$e->src = $new_src;
						$e->class = $class . ' _iub_cs_activate';
						$js .= $e->outertext;
						break;

					default:
						$js = $html;
						break;
				}
			}
		}
		return $js;
	}

	/**
	 * Parse all IUBENDAs comment and convert the code inside with create_tags method
	 */
	public function parse_iubenda_comments() {
		foreach ( array( 'IUB_REGEX_PATTERN', 'IUB_REGEX_PATTERN_2' ) as $pattern ) {
			preg_match_all( constant( 'self::' . $pattern ), $this->content_page, $scripts );

			if ( is_array( $scripts[1] ) ) {
				$count = count( $scripts[1] );
				$js_scripts = array();
				for ( $j = 0; $j < $count; $j ++  ) {
					$this->iub_comments_detected[] = $scripts[1][$j];
					$html = str_get_html( $scripts[1][$j], $lowercase = true, $forceTagsClosed = true, $stripRN = false );
					$js_scripts[] = $this->create_tags( $html );
				}

				if ( is_array( $scripts[1] ) && is_array( $js_scripts ) ) {
					if ( count( $scripts[1] ) >= 1 && count( $js_scripts ) >= 1 ) {
						$this->content_page = strtr( $this->content_page, array_combine( $scripts[1], $js_scripts ) );
					}
				}
			}
		}
	}

	/**
	 * Parse automatically all the scripts in the page and converts it in text/plain
	 * if src or the whole output has inside one of the elements in $auto_script_tags array
	 */
	public function parse_scripts() {
		$html = str_get_html( $this->content_page, $lowercase = true, $forceTagsClosed = true, $stripRN = false );

		if ( is_object( $html ) ) {
			$scripts = $html->find( "script" );
			if ( is_array( $scripts ) ) {
				$count = count( $scripts );
				for ( $j = 0; $j < $count; $j ++  ) {
					$s = $scripts[$j];
					if ( ! empty( $s->innertext ) ) {
						$this->scripts_detected[] = $s->innertext;
						if ( iubendaPage::strpos_array( $s->innertext, $this->auto_script_tags ) !== false ) {
							$class = $s->class;
							$s->class = $class . ' _iub_cs_activate-inline';
							$s->type = 'text/plain';
							$this->scripts_converted[] = $s->innertext;
						}
					} else {
						$src = $s->src;
						if ( $src ) {
							$this->scripts_inline_detected[] = $src;
							if ( iubendaPage::strpos_array( $src, $this->auto_script_tags ) !== false ) {
								$class = $s->class;
								$s->class = $class . ' _iub_cs_activate';
								$s->type = 'text/plain';
								$this->scripts_inline_converted[] = $src;
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

				$ad_client = trim( $ad_client[1] );
				$ad_slot = trim( $ad_slot[1] );
				$ad_width = trim( $ad_width[1] );
				$ad_height = trim( $ad_height[1] );

				$ad_class = 'class="_iub_cs_activate_google_ads"';
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
				            function iubenda_adsense_unblock(){
                        var t = 1;
                        jQuery('._iub_cs_activate_google_ads').each(function() {
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
				            if('callback' in _iub.csConfiguration) {
				                _iub.csConfiguration.callback.onConsentGiven = iubenda_adsense_unblock;
				            }
				            else
				            {
				                _iub.csConfiguration.callback = {};

				                _iub.csConfiguration.callback.onConsentGiven = iubenda_adsense_unblock;
				            }
				        </script>
				    ";

				$html = str_replace( "</body>", $adsense_callback . "</body>", $html );
			}

			$this->content_page = $html;
		}
	}

	/**
	 * Parse automatically all the iframe in the page and change the src to suppressedsrc
	 * if src has inside one of the elements in $auto_iframe_tags array
	 */
	public function parse_iframe() {
		$html = str_get_html( $this->content_page, $lowercase = true, $forceTagsClosed = true, $stripRN = false );

		if ( is_object( $html ) ) {
			$iframes = $html->find( "iframe" );
			if ( is_array( $iframes ) ) {
				$count = count( $iframes );
				for ( $j = 0; $j < $count; $j ++  ) {
					$i = $iframes[$j];
					$src = $i->src;
					$this->iframe_detected[] = $src;
					if ( iubendaPage::strpos_array( $src, $this->auto_iframe_tags ) !== false ) {
						$new_src = "//cdn.iubenda.com/cookie_solution/empty.html";
						$class = $i->class;
						$i->suppressedsrc = $src;
						$i->src = $new_src;
						$i->class = $class . ' _iub_cs_activate';
						$this->iframe_converted[] = $src;
					}
				}
			}
			$this->content_page = $html;
		}
	}

	/**
	 * Call three methods to parse the page, iubendas comment, scripts + iframe
	 */
	public function parse() {
		$this->parse_iubenda_comments();
		$this->parse_scripts();
		$this->parse_iframe();
	}

	/**
	 * Return the final page to output
	 *
	 * @return mixed
	 */
	public function get_converted_page() {
		return $this->content_page;
	}

}
