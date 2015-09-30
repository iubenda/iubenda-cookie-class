<?php

// the "$html" parameter must contain the content of the web page with the iubenda JavaScript banner/policy included
 
function iubenda_system($html)
{
    if(empty($html)) return;
       
    /*
     * Separator
    */
 
    if(!function_exists("file_get_html")) {
        require_once("simple_html_dom.php");
    }
 
    require_once("iubenda.class.php");
 
    /*
     * Separator
    */
 
    if(!Page::consent_given() && !Page::bot_detected()) {
        $page = new Page($html);
        $page->parse();
        $html = $page->get_converted_page();
    }
 
    /* Finished */
 
   	return $html;
}

/*
 *
 * Example:
 *
 * echo iubenda_system("<html> ...content... </html>");
 *
*/

?>