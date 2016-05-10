<?php

/**
 * iubenda.class.php
 * version: 2.0.2
 * codename: Faster
 * @author: Copyright 2015 iubenda
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
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

	class iubendaFaster
	{
		/*
			Variables
		*/
		
		private $getBlack = array
		(
			array
			(
				/*
					Domains
				*/
				
				"platform.twitter.com/widgets.js",
				"apis.google.com/js/plusone.js",
				"apis.google.com/js/platform.js",
				"connect.facebook.net",
				"www.youtube.com/iframe_api",
				"pagead2.googlesyndication.com/pagead/js/adsbygoogle.js",
				"sharethis.com/button/buttons.js",
				"addthis.com/js/",
				
				/*
					JavaScript
				*/
				
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
		
		private $getClass = array("_iub_cs_activate", "_iub_cs_activate-inline");
		
		/*
			Methods
		*/
				
		public function isBlack($offender, $blacklist)
		{
			/*
				Check if a string is in the black list.
			*/
			
			if(empty($offender) || empty($blacklist)) {
						
				return false;
				
			}
			
			/**/
			
			foreach($blacklist as $black)
			{
				if(strpos($offender, $black) !== false) {
					
					return true;
					
				}
			}
			
			/**/
			
			return false;
		}
		
		/**/
		
		public function isParse($offender)
		{	
			/*
				Parse the entrie document and search for black elements.
			*/
			
			libxml_use_internal_errors(true);
			
			/**/
			
			$src = "";
			
			$blank = $this -> getBlank;
			$class = $this -> getClass;
			
			$list_1 = $this -> getBlack[0];
			$list_2 = $this -> getBlack[1];
			
			$document = new DOMDocument();
			
			/**/
			
			$document -> formatOutput = true;
			$document -> preserveWhiteSpace = false;
			
			/**/
			
			$document -> loadHTML($offender);
			
			/**/
			
			$scripts = $document -> getElementsByTagName("script");
			$iframes = $document -> getElementsByTagName("iframe");
			
			/*
				Parse the founded elements and check who is in black.
			*/
			
			foreach($scripts as $script)
			{	
				$src = $script -> getAttribute("src");
				
				/**/
				
				if($this -> isBlack($src, $list_1))
				{
					$script -> setAttribute("type", "text/plain");
					$script -> setAttribute("class", $script -> getAttribute("class")." ".$class[0]);
				}
				elseif($this -> isBlack($script -> nodeValue, $list_1))
				{
					$script -> setAttribute("type", "text/plain");
					$script -> setAttribute("class", $script -> getAttribute("class")." ".$class[1]);
				}
			}
			foreach($iframes as $iframe) {
				
				$src = $iframe -> getAttribute("src");
				
				/**/
				
				if($this -> isBlack($src, $list_2))
				{
					$iframe -> setAttribute("src", $blank);
					$iframe -> setAttribute("suppressedsrc", $src);
					$iframe -> setAttribute("class", $iframe -> getAttribute("class")." ".$class[0]);
				}
			}
			
			/**/
			
			$offender = $document -> saveHTML();
			
			/**/
			
			libxml_use_internal_errors(false);
			
			/**/
			
			return $offender;
		}
	}

?>