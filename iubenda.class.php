<?php

	class Page {
	
		const IUB_REGEX_PATTERN = '/<!--IUB_COOKIE_POLICY_START-->(.*)<!--IUB_COOKIE_POLICY_END-->/sU';
	
		public $auto_script_tags = array(
			'platform.twitter.com/widgets.js',
			'apis.google.com/js/plusone.js',
			'apis.google.com/js/platform.js',
			'connect.facebook.net',
			'www.youtube.com/iframe_api',
			'pagead2.googlesyndication.com/pagead/show_ads.js',
			'pagead2.googlesyndication.com/pagead/js/adsbygoogle.js',
            'sharethis.com/button/buttons.js',
            'addthis.com/js/'
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
			'www.google.com/maps/embed'
		);
		
		public $iub_comments_detected = array();
		public $iframe_detected = array();
		public $iframe_converted = array();
		public $scripts_detected = array();
		public $scripts_inline_detected = array();
		public $scripts_inline_converted = array();
		public $scripts_converted = array();
		
	
		/*
		construct: the whole HTML output of the page
		*/
		public function __construct($content_page){
			$this->original_content_page = $content_page;
			$this->content_page = $content_page;
		}
		
		/*
		print iubenda banner, parameter: the script code of iubenda to print the banner
		*/
		public function print_banner($banner){	
			return $banner.= "\n
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
			
		/*
		Static, detect bot & crawler
		*/
		static function bot_detected() {
            return (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|slurp|spider/i', $_SERVER['HTTP_USER_AGENT']));
		}

		/*
		Static, utility function: Return true if the user has already given consent on the page
		*/
		static function consent_given(){
			foreach($_COOKIE as $key => $value){
				if(Page::strpos_array($key, array('_iub_cs-s', '_iub_cs'))){
					return true;
				}
			}
		}
		/*
		Static, utility function: strpos for array
		*/
		static function strpos_array($haystack, $needle){
			if(is_array($needle)){
	       		foreach($needle as $need){
	       			if(strpos($haystack, $need) !== false){
	        			return true;
	        		}
	       		}
		     }else{
		     	if(strpos($haystack, $need) !== false) {
	    	    	return true;
	        	}
	     	}
	  		return false;
		}
		

		/* Convert scripts, iframe and other code inside IUBENDAs comment in text/plain to not generate cookies */
		public function create_tags($html){
			
			$elements = $html->find("*");
			$js = '';
			
			if(is_array($elements)){
				$count = count($elements);
				for($j=0; $j<$count; $j++){
					$e = $elements[$j];
					switch($e->tag){
						case 'script':
							$class = $e->class;
							$e->class = $class . ' _iub_cs_activate';
							$e->type = 'text/plain';								
							$js.= $e->outertext;
						break;
						
						case 'iframe':
							$new_src = "//cdn.iubenda.com/cookie_solution/empty.html";
							$class = $e->class;
							$e->suppressedsrc = $e->src;
							$e->src = $new_src;
							$e->class = $class . ' _iub_cs_activate';						
							$js.= $e->outertext;
						break;
		
						default:
							$js.= '<noscript class="_no_script_iub">';
							$js.= $e->outertext;
							$js.= '</noscript>';
						break;
					}	
				}
			}
			return $js;
		}
	
		/* Parse all IUBENDAs comment and convert the code inside with create_tags method */
		public function parse_iubenda_comments(){
			preg_match_all(self::IUB_REGEX_PATTERN, $this->content_page, $scripts);
			if(is_array($scripts[1])){
				$count = count($scripts[1]);
				$js_scripts = array();
				for($j=0; $j<$count; $j++){
					$this->iub_comments_detected[] = $scripts[1][$j];		
					$html = str_get_html($scripts[1][$j], $lowercase=true, $forceTagsClosed=true, $stripRN=false);
					$js_scripts[] = $this->create_tags($html);
				}
	
			    if(is_array($scripts[1]) && is_array($js_scripts)){
			    	if(count($scripts[1]) >= 1 && count($js_scripts) >= 1){
						$this->content_page = strtr($this->content_page, array_combine($scripts[1], $js_scripts));
			    	}
			    }		
			}		
		}
		
		/* Parse automatically all the scripts in the page and converts it in text/plain 
		if src or the whole output has inside one of the elements in $auto_script_tags array */
		public function parse_scripts(){

			$html = str_get_html($this->content_page, $lowercase=true, $forceTagsClosed=true, $stripRN=false);
			if(is_object($html)){
				$scripts = $html->find("script");
				if(is_array($scripts)){
					$count = count($scripts);
					for($j=0; $j<$count; $j++){
						$s = $scripts[$j];
						if(!empty($s->innertext)){
							$this->scripts_detected[] = $s->innertext;
							if (Page::strpos_array($s->innertext, $this->auto_script_tags) !== false) {
								$class = $s->class;
								$s->class = $class . ' _iub_cs_activate-inline';
								$s->type = 'text/plain';
								$this->scripts_converted[] = $s->innertext;				
							}
						}else{
						    $src = $s->src;
							if($src){
								$this->scripts_inline_detected[] = $src;
								if (Page::strpos_array($src, $this->auto_script_tags) !== false) {
									$class = $s->class;
									$s->class = $class . ' _iub_cs_activate';
									$s->type = 'text/plain';
									$this->scripts_inline_converted[] = $src;
								}
							}
						}
					}
				}

				/*
				 * AdSense check by Peste Vasile Alexandru
				*/

				while(preg_match("#<script(.*?)>(.*?)google_ad_client(.*?)=(.*?);(.*?)</script>#is", $html))
				{
					$google_ad_client;
					$google_ad_slot;
					$google_ad_width;
					$google_ad_height;

					/* */

					preg_match("#<script(.*?)>(.*?)google_ad_client(.*?)=(.*?);(.*?)</script>#is", $html, $google_ad_client);
					preg_match("#<script(.*?)>(.*?)google_ad_slot(.*?)=(.*?);(.*?)</script>#is", $html, $google_ad_slot);
					preg_match("#<script(.*?)>(.*?)google_ad_width(.*?)=(.*?);(.*?)</script>#is", $html, $google_ad_width);
					preg_match("#<script(.*?)>(.*?)google_ad_height(.*?)=(.*?);(.*?)</script>#is", $html, $google_ad_height);

					/* */

					$google_settings = '
						<div style="width: '.$google_ad_width[4].'px;height:'.$google_ad_height[4].'px;" class="_iub_cs_activate_google_ads"
						    data-client='.$google_ad_client[4].'
							data-slot='.$google_ad_slot[4].'
							data-width='.$google_ad_width[4].'
							data-height='.$google_ad_height[4].'>
						</div>
					';

					/* */

					$html = preg_replace("#<script(.*?)>(.*?)google_ad_client(.*?)=(.*?);(.*?)</script>#is", $google_settings, $html, 1);
					$html = preg_replace("#<script(.*?)>(.*?)pagead2.googlesyndication.com/pagead/show_ads.js(.*?)</script>#is", null, $html, 1);
				}

				/* */

				$this->content_page = $html;
			}
		}

		/* Parse automatically all the iframe in the page and change the src to suppressedsrc
		if src has inside one of the elements in $auto_iframe_tags array */	
		public function parse_iframe(){
			$html = str_get_html($this->content_page, $lowercase=true, $forceTagsClosed=true, $stripRN=false);
			if(is_object($html)){
				$iframes = $html->find("iframe");			
				if(is_array($iframes)){
					$count = count($iframes);
					for($j=0; $j<$count; $j++){
						$i = $iframes[$j];
						$src = $i->src;
						$this->iframe_detected[] = $src;
						if (Page::strpos_array($src, $this->auto_iframe_tags) !== false){					
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
		
		/*
		Call three methods to parse the page, iubendas comment, scripts + iframe
		*/
		public function parse(){
			$this->parse_iubenda_comments();
			$this->parse_scripts();
			$this->parse_iframe();
		}
		
		/*
		Return the final page to output
		*/
		public function get_converted_page(){
			return $this->content_page;
		}
	
	}

?>