# Read Me 



[![GitHub release](https://img.shields.io/github/release/iubenda/iubenda-cookie-class.svg)](https://github.com/iubenda/iubenda-cookie-class/releases/tag/2.0.3)


***PHP class for the iubenda cookie law solution***
 
If you have European users you need to obtain and manage consent for the use of most cookies. 
The iubenda Cookie Solution is an all-in-one approach developed by iubenda, that helps to make your website GDPR and Cookie Law compliant by integrating with your cookie policy, providing a compliant cookie banner and the blocking management of cookie scripts. The Cookie Solution also allows users to set advertising preferences on-site and within the solution, facilitated the recent-but-widely adopted IAB Europe Transparency & Consent [framework](https://www.iubenda.com/en/help/7440#aboutIAB).

[Read more about the Cookie Solution here](https://www.iubenda.com/en/features#cookie-solution).

* * *
#### This class allows you to scan a page in PHP for scripts and run the automatic blocking of scripts

*This is the class on which our WordPress and Joomla! and Drupal plugins are based and you can use it to build your own plugin independently for a platform other than those for which we have already developed a dedicated solution.*

* * *

## Functionality

This class works with the iubenda Cookie Law Solution and allows you to block the most common widgets and third-party cookies to comply with Cookie Law. 

The class is currently able to detect and automatically block the following scripts:

* Facebook widgets
* Twitter widgets
* Google+ widgets
* Google AdSense
* YouTube widgets
* Vimeo
* AddThis widgets
* ShareThis widgets

It also allows the manual blocking of all other resources without direct intervention on the actual scripts. Read more about the [prior blocking functionality here](https://www.iubenda.com/en/help/1229-cookie-law-solution-preventing-code-execution-that-could-install-cookies).
* * *
Here is an example of the PHP class integration:

```php
<?php
    function iubenda_system( $html ) {
        if ( ! function_exists( "file_get_html" ) ) {
            require_once( "simple_html_dom.php" );
        }
 
        require_once( "iubenda.class.php" );

        $page = new iubendaPage( $html );
 
        if (! $page->consent_given() && ! $page->bot_detected() ) {
            $page->parse();
            $html = $page->get_converted_page();
        }
 
        return $html;
    }
    
    ob_start( "iubenda_system" );
?>
```



The `iubenda_system` method verifies if the page visitor consents to the use of cookies. If they have consented, the script returns the HTML provided as a parameter without taking any action such as parsing/replacing.
Simply copy your method into the PHP document and then call it with the following syntax `iubenda_system("contenutohtml");` that will return the code.

* Parsing/replacing the portions of code contained within `<!--IUB-COOKIE-BLOCK-START-->` and `<!--IUB-COOKIE-BLOCK-END-->`
* Automatic parsing/replacing of iframe that contain defined src
* Automatic parsing/replacing of scripts that contain defined src

These operations take place in accordance with the rules explained in [this guide](https://www.iubenda.com/en/help/posts/1229). We suggest that you consult the posts relating to the alteration of script, img and iframe tags. 

As a last step the script invokes the `get_converted_page()` of the Page object and returns the page modified in such a way that no cookie will be generated. 


## Additional Help and docs

* [Full Cookie Solution Documentation](https://www.iubenda.com/en/help/1205-technical-documentation-for-the-cookie-law-solution-banner-cookie-policy-and-consent-management)
* [Prior Blocking Guide](https://www.iubenda.com/en/help/1229-cookie-law-solution-preventing-code-execution-that-could-install-cookies) 
* [Cookie Solution Feature Overview](https://www.iubenda.com/en/features#cookie-solution)
