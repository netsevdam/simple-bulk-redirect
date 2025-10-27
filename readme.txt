=== Simple Bulk Redirect ===
Contributors: kaan73
Donate link: https://www.patreon.com/korsantaksi
Plugin URI: https://github.com/netsevdam/simple-bulk-redirect
Tags: redirect, 301 redirect, SEO, CSV, wildcard
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.4.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage 301 redirects in WordPress with bulk CSV import/export and wildcard support.

== Description ==

Simple Bulk Redirect allows WordPress site administrators to manage 301 redirects easily. Supports bulk redirects via admin panel, CSV import/export, and wildcard redirects. Redirect old URLs to root or any page. Lightweight, safe, and SEO-friendly.

= Features =
* Bulk 301 redirect management
* CSV import/export functionality  
* Wildcard redirect support (e.g., /old/* → /new/$1)
* Domain-to-domain redirects
* Simple admin interface
* No database bloat
* SEO-friendly

== Installation ==

1. Upload the `simple-bulk-redirect` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Bulk Redirects** in the admin menu to add, edit, or import redirects.

== Frequently Asked Questions ==

= Can I redirect multiple URLs at once? =
Yes! You can import a CSV file with multiple old and new URLs.

= Does it support wildcard redirects? =
Yes, use `*` at the end of an old URL path to redirect all URLs under that path.

= Can I redirect a URL to my homepage? =
Yes, set the new URL as `/` in the admin panel or CSV.

= What CSV format should I use? =
Use two columns: "Old URL" and "New URL". See the example below.

== Screenshots ==

1. Admin page with redirects table.
2. CSV import/export interface.
3. Example of wildcard redirect usage.
4. Adding a redirect to root URL (`/`).

== Example CSV Format ==

Old URL,New URL
/about-us/,/about/
/category/about-us/*,/about/
/istanbul-taxi/,/
/old-page/,/new-page/
/blog/old-category/*,/blog/new-category/

== Changelog ==

= 1.4.2 =
* Fixed security issues with proper sanitization and validation
* Improved file upload security
* Proper script enqueueing
* Complete code cleanup

= 1.4.1 =
* Enhanced security with dangerous URL detection
* Added file size limits for CSV imports
* Improved redirect validation
* Added translation support

= 1.4.0 =
* Major security improvements
* Added proper data sanitization and escaping
* Fixed option name conflicts
* Improved wildcard redirect handling

= 1.3.1 =
* Added wildcard redirect support.
* Improved CSV import/export.
* Minor bug fixes.

= 1.2 =
* Initial public release.

== Upgrade Notice ==

= 1.4.2 =
Important security update. Includes comprehensive data sanitization and validation improvements.

= 1.4.1 =
Security enhancement update. Adds dangerous URL detection and improved file upload validation.

= 1.4.0 =
Security update. Fixes data sanitization issues and improves plugin stability.

= 1.3.1 =
Update to version 1.3.1 to use wildcard redirect functionality and improved CSV handling.

== Additional Information ==

Maintained by Kaan Hanoğlu. Lightweight, secure, and designed for SEO-friendly URL management. All redirects execute server-side; no frontend code is injected.