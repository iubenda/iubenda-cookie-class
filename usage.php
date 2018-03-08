<?php
/**
 * usage.php
 * @author: Copyright 2018 iubenda
 * @license GNU/GPL
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// the "$html" parameter must contain the content of the web page with the iubenda JavaScript banner/policy included

function iubenda_system( $html ) {
	if ( empty( $html ) )
		return;

	// separator
	if ( ! function_exists( "file_get_html" ) ) {
		require_once("simple_html_dom.php");
	}

	require_once("iubenda.class.php");

	// separator
	if ( ! Page::consent_given() && ! Page::bot_detected() ) {
		$page = new Page( $html );
		$page->parse();
		$html = $page->get_converted_page();
	}

	// finished
	return $html;
}

/**
 *
 * Example:
 *
 * echo iubenda_system("<html> ...content... </html>");
 *
 */