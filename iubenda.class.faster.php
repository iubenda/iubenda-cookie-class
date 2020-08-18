<?php
/**
 * iubenda.class.faster.php
 *
 * @author iubenda s.r.l
 * @copyright 2018-2020, iubenda s.r.l
 * @license GNU/GPL
 * @version 2.0.3
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

class iubendaFaster {

	// variables
	const IUB_REGEX_PATTERN = '/<!--\s*IUB_COOKIE_POLICY_START\s*-->(.*?)<!--\s*IUB_COOKIE_POLICY_END\s*-->/s';
	const IUB_REGEX_PATTERN_2 = '/<!--\s*IUB-COOKIE-BLOCK-START\s*-->(.*?)<!--\s*IUB-COOKIE-BLOCK-END\s*-->/s';

	public $iub_comments_detected = array();
	private $getBlack = array(
		array(
			// domains
			"platform.twitter.com/widgets.js",
			"apis.google.com/js/plusone.js",
			"apis.google.com/js/platform.js",
			"connect.facebook.net",
			"www.youtube.com/iframe_api",
			"securepubads.g.doubleclick.net/tag/js/gpt.js",
			"pagead2.googlesyndication.com/pagead/js/adsbygoogle.js",
			"sharethis.com/button/buttons.js",
			"addthis.com/js/",
			// javascript
			"window.adsbygoogle"
		),
		array
			(
			"youtube.com",
			"platform.twitter.com",
			"www.facebook.com/plugins/like.php",
			"www.facebook.com/plugins/likebox.php",
			"apis.google.com",
			"www.google.com/maps/embed/",
			"player.vimeo.com/video",
			"maps.google.it/maps",
			"www.google.com/maps/embed"
		)
	);

	/**/
	private $getBlank = "//cdn.iubenda.com/cookie_solution/empty.html";
	private $getClass = array( "_iub_cs_activate", "_iub_cs_activate-inline" );

	/**
	 * Methods
	 *
	 * @param type $offender
	 * @param type $blacklist
	 * @return boolean
	 */
	public function isBlack( $offender, $blacklist ) {
		// check if a string is in the black list.
		if ( empty( $offender ) || empty( $blacklist ) ) {

			return false;
		}

		foreach ( $blacklist as $black ) {
			if ( strpos( $offender, $black ) !== false ) {

				return true;
			}
		}

		return false;
	}

	/**
	 * Parse automatically all the scripts in the page and converts it in text/plain
	 * if src or the whole output has inside one of the elements in $auto_script_tags array
	 *
	 * @param mixed $content
	 * @return mixed
	 */
	public function isParse( $content ) {
		// parse the entrie document and search for black elements.
		libxml_use_internal_errors( true );

		// parse all IUBENDAs comment and convert the code inside
		$content = $this->parse_iubenda_comments( $content );

		$src = "";

		$blank = $this->getBlank;
		$class = $this->getClass;

		$list_1 = $this->getBlack[0];
		$list_2 = $this->getBlack[1];

		$document = new DOMDocument();

		$document->formatOutput = true;
		$document->preserveWhiteSpace = false;

		$document->loadHTML( $content );

		$scripts = $document->getElementsByTagName( "script" );
		$iframes = $document->getElementsByTagName( "iframe" );

		// parse the founded elements and check who is in black.
		foreach ( $scripts as $script ) {
			$src = $script->getAttribute( "src" );

			if ( $this->isBlack( $src, $list_1 ) ) {
				$script->setAttribute( "type", "text/plain" );
				$script->setAttribute( "class", $script->getAttribute( "class" ) . " " . $class[0] );
			} elseif ( $this->isBlack( $script->nodeValue, $list_1 ) ) {
				$script->setAttribute( "type", "text/plain" );
				$script->setAttribute( "class", $script->getAttribute( "class" ) . " " . $class[1] );
			}
		}
		foreach ( $iframes as $iframe ) {

			$src = $iframe->getAttribute( "src" );

			if ( $this->isBlack( $src, $list_2 ) ) {
				$iframe->setAttribute( "src", $blank );
				$iframe->setAttribute( "suppressedsrc", $src );
				$iframe->setAttribute( "class", $iframe->getAttribute( "class" ) . " " . $class[0] );
			}
		}

		$content = $document->saveHTML();

		libxml_use_internal_errors( false );

		return $content;
	}

	/**
	 * Parse all IUBENDAs comment and convert the code inside with create_tags method
	 *
	 * @param mixed $content
	 * @return mixed
	 */
	public function parse_iubenda_comments( $content ) {
		foreach ( array( 'IUB_REGEX_PATTERN', 'IUB_REGEX_PATTERN_2' ) as $pattern ) {
			preg_match_all( constant( 'self::' . $pattern ), $content, $scripts );

			// found any content?
			if ( is_array( $scripts[1] ) ) {
				$count = count( $scripts[1] );
				$js_scripts = array();

				for ( $j = 0; $j < $count; $j ++ ) {
					// keep it for testing
					$this->iub_comments_detected[] = $scripts[1][$j];

					// get HTML dom from string
					$html = str_get_html( $scripts[1][$j], true, true, false );

					// convert scripts, iframes and other code inside IUBENDAs comment in text/plain to not generate cookies
					$js_scripts[] = $this->create_tags( $html );
				}

				if ( is_array( $js_scripts ) && $count >= 1 && count( $js_scripts ) >= 1 )
					$content = strtr( $content, array_combine( $scripts[1], $js_scripts ) );
			}
		}

		return $content;
	}

	/**
	 * Convert scripts, iframe and other code inside IUBENDAs comment in text/plain to not generate cookies
	 *
	 * @param mixed $content
	 * @return mixed
	 */
	public function create_tags( $content ) {
		$elements = $content->find( "*" );
		$js = '';

		if ( is_array( $elements ) ) {
			$count = count( $elements );

			for ( $j = 0; $j < $count; $j ++ ) {
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
						$js = $content;
						break;
				}
			}
		}

		return $js;
	}

}
