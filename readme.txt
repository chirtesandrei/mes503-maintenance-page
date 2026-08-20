=== Mes503 Maintenance Page ===
Contributors: chirtesandrei
Tags: maintenance, coming soon, 503, noindex
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 0.1.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show a clear maintenance page while administrators keep working normally.

== Description ==

Mes503 Maintenance Page focuses on one task: showing visitors a clean temporary page while logged-in administrators continue to use the website normally.

Version 0.1.4 includes:

* one-click activation and deactivation;
* customizable browser title, heading, message, logo, and accent color;
* optional button to a safe URL;
* administrator-only preview;
* HTTP 503 response with a configurable Retry-After header;
* noindex instructions for search engines;
* `robots.txt` left untouched, so crawlers keep reading it normally during the maintenance window;
* no analytics, external requests, or data collection;
* complete settings cleanup when the plugin is uninstalled.

The plugin ships in English with a complete French translation. It is developed with assistance from artificial intelligence tools, then reviewed and tested by a human before release.

== Installation ==

1. In WordPress, open Plugins > Add New Plugin > Upload Plugin.
2. Select the plugin ZIP file.
3. Activate the plugin.
4. Open Settings > Maintenance mode.
5. Customize and preview the page, then enable maintenance mode.

== Frequently Asked Questions ==

= Can administrators still access the website? =

Yes. Logged-in administrators with the `manage_options` capability see the regular website. The WordPress login page also remains available.

= What do search engines receive? =

Regular pages return HTTP 503, a Retry-After header, and noindex instructions. These signals indicate a temporary outage.

`robots.txt` is deliberately excluded and keeps its normal WordPress response instead of receiving the maintenance page. A prolonged 5xx status on that single file makes search engines stop crawling the entire website, which is the opposite of what a temporary maintenance window should signal.

= Does the plugin collect data? =

No. It adds no analytics, contacts no external service, and transmits no information.

= What happens when I uninstall the plugin? =

The plugin option is deleted. A media-library image selected as the logo is preserved because it may be used elsewhere on the website.

== Changelog ==

= 0.1.4 =

* Renamed to Mes503 Maintenance Page for a distinctive WordPress.org directory identity.
* The public maintenance stylesheet now loads through the WordPress Styles API.
* Translation source files remain available in the project while WordPress.org distributes the installed translations.

= 0.1.3 =

* `robots.txt` no longer receives the 503 maintenance page on sites left with plain permalinks. The exclusion relied on `is_robots()`, which needs rewrite rules that only exist once a permalink structure is set; the request path is now checked as well. A prolonged 5xx on `robots.txt` can make search engines stop crawling the whole site.
* The same protection is applied to `favicon.ico`.

= 0.1.2 =

* Fixed the maintenance mode switch, which was stacked below its label instead of sitting on its settings row.
* The public maintenance page is now correctly centred vertically on tall screens.

= 0.1.1 =

* Source strings moved to English with a bundled French translation, so the plugin can be translated into any language.
* `robots.txt` and favicon requests no longer receive the maintenance page. `robots.txt` keeps answering 200 while maintenance mode is active.
* Media picker labels are now translatable.

= 0.1.0 =

* First beta release.
* Secure settings page built with the WordPress Settings API.
* HTTP 503, Retry-After, and noindex responses.
* Administrator bypass and protected preview.
* Clean uninstall routine.

== Screenshots ==

1. The settings screen: content, appearance, and the maintenance mode switch.
2. The public maintenance page as visitors see it.
3. The same page on a mobile screen.

== Upgrade Notice ==

= 0.1.4 =

Directory review update: distinctive plugin identity, WordPress Styles API integration, and WordPress.org-managed translations.

= 0.1.3 =

Recommended if your permalinks are set to plain: `robots.txt` was answering 503 during maintenance, which can stop search engines from crawling the site.

= 0.1.2 =

Fixes two display issues: the settings switch and the vertical centring of the public page.

= 0.1.1 =

Fixes a search engine issue: robots.txt is no longer served as an error while maintenance mode is active.
