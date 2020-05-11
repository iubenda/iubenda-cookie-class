<?php
/**
 * usage.php
 *
 * @author iubenda s.r.l
 * @copyright 2018-2020, iubenda s.r.l
 * @license GNU/GPL
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

// the "$html" parameter must contain the content of the web page with the iubenda JavaScript banner/policy included

function iubenda_system( $html, $type = 'page' ) {
	if ( empty( $html ) )
		return;

	require_once( 'iubenda.class.php' );

	// separator
	if ( ! iubendaParser::consent_given() && ! iubendaParser::bot_detected() ) {
		$iubenda = new iubendaParser( $html, array( 'type' => in_array( $type, array( 'page', 'faster' ), true ) ? $type : 'page' ) );
		$html = $iubenda->parse();
	}

	// finished
	return $html;
}

/**
 * Example:
 *
 * echo iubenda_system( "<html> ...content... </html>", 'faster' );
 *
 */
