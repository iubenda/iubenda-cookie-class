<?php
	include_once 'simple_html_dom.php';
	include_once 'iubenda.class.php';
	
	// Check if the user has already given consent
	if(!Page::consent_given()){

		$url = 'http://www.facciamoilpresepe.it';
		
		// Here you should pass the content of the page, this is just an example
		// using file_get_contents(url) to have a real web page
		$content = file_get_contents(A);

		// Istantiate new Page with content
		$page = new Page($content);
		
		// Parse and convert content
		$page->parse();
		
		// Print the new page
		echo $page->get_converted_page();
	}else{
		echo 'Consent already given.. print the page without instantiate and parse';
	}
	
?>