# Read Me

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

* Google Analytics
* Google Maps
* Google AdSense
* Google ReCaptcha
* Google Site Search
* Google Tag Manager
* Google oAuth
* Google+ widgets
* Twitter widgets
* Facebook widgets
* Facebook Comments
* YouTube
* Vimeo
* Linkedin widgets
* ShareThis widgets
* Instagram widgets
* AddThis widgets
* Pinterest widgets
* PayPal widgets
* Disqus
* Optimizely
* Neodata
* Criteo
* Outbrain
* Headway
* Codepen
* Freshchat
* Uservoice
* AdRoll
* Olark
* Segment
* Kissmetrics
* Mixpanel
* Pingdom
* Bing
* Elevio


It also allows the manual blocking of all other resources without direct intervention on the actual scripts. Read more about the [prior blocking functionality here](https://www.iubenda.com/en/help/1229-cookie-law-solution-preventing-code-execution-that-could-install-cookies).

* * *

Here is an example of the PHP class integration:
```php
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
```

The `iubenda_system` method verifies if the page visitor consents to the use of cookies. If they have consented, the script returns the HTML provided as a parameter without taking any action such as parsing/replacing.
Simply copy your method into the PHP document and then call it with the following syntax `iubenda_system("contenutohtml");` that will return the code.

* Parsing/replacing the portions of code contained within `<!--IUB-COOKIE-BLOCK-START-->` and `<!--IUB-COOKIE-BLOCK-END-->`
* Automatic parsing/replacing of iframe that contain defined src
* Automatic parsing/replacing of scripts that contain defined src

These operations take place in accordance with the rules explained in [this guide](https://www.iubenda.com/en/help/posts/1229). We suggest that you consult the posts relating to the alteration of script, img and iframe tags.

## Additional Help and docs

* [Full Cookie Solution Documentation](https://www.iubenda.com/en/help/1205-technical-documentation-for-the-cookie-law-solution-banner-cookie-policy-and-consent-management)
* [Prior Blocking Guide](https://www.iubenda.com/en/help/1229-cookie-law-solution-preventing-code-execution-that-could-install-cookies)
* [Cookie Solution Feature Overview](https://www.iubenda.com/en/features#cookie-solution)

## Changelog

##### 4.1.13
* Update PHP Simple HTML DOM Parser library to the latest version 1.9.1
* Remove deprecated (Faster/Page) classes

##### 4.1.12
* Remove GTM from Basic interaction in Iframes

##### 4.1.11
* Support PHP 8

##### 4.1.10
* Move www.googletagmanager.com/gtag/js under analytics #4

##### 4.1.9
* Fix SSRF security vulnerability
* Remove googletagmanager.com/gtm.js from basic interaction

##### ``4.1.8
* Fix: Avoid overriding the purposes attr if it was set

##### 4.1.7
* Fix: purpose evaluation for iframes blocking

##### 4.1.6
* Fix: Check script type before getting content in GTM

##### 4.1.5
* Tweak: Add google analytics to analytics scripts
* Tweak: Add data-iub-purposes on inline-scripts

##### 4.1.4
* Fix: Move FB connect to experience enhancement

##### 4.1.3
* Tweak: Add Google GPT to per-purpose blocking support

##### 4.1.2
* Security Fix: limit url sanitize to http protocols

##### 4.1.1
* Fix: AddThis per-purpose category

##### 4.1.0
* New: Google AMP support

##### 4.0.0
* New: Per-purpose script blocking support
* New: Reject button support

##### 3.4.0
* New: Introducing wildcard support for scripts and iframes

##### 3.3.1
* Tweak: Improved Google Tag Manager script blocking

##### 3.3.0
* Tweak: Simple HTML Dom PHP class update to 1.9

##### 3.2.0
* New: Introducing a way to skip specific script parsing

##### 3.1.2
* Tweak: Improved Youtube and Google Maps support

##### 3.1.1
* Tweak: Update composer.json autoloader

##### 3.1.0
* Tweak: Update and extend the list of blocked scripts including Google Site Search, Google oAuth, Linkedin widgets, PayPal widgets, Pinterest, AddThis, Disqus, Optimizely, Neodata, Criteo, Outbrain, Headway, Codepen, Freshchat, Uservoice
, AdRoll, Olark, Segment, Kissmetrics, Mixpanel, Pingdom, Bing and Elevio

##### 3.0.0
* Tweak: Update and unify iubenda parsing engine

### License

This project is licensed under the GPl 3 license.
