=== Orbisius SEO Editor ===
Contributors: lordspace,orbisius
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7APYDVPBCSY9A
Tags: seo,seo plugin,seo,bulk edit seo,wpseo,yoast seo,yoast,autodescription,rank-math,twitter card,meta title, meta description, woocommerce seo,wc seo,seo plugin,WordPress SEO,SEO Rank Math import, WordPress SEO import
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 5.6
Stable tag: 1.0.3
License: GPLv2 or later

Orbisius SEO editor is (almost) a universal SEO editor that allows you to edit meta titles and descriptions regardless of the SEO plugin or theme you’re currently using

== Description ==

Orbisius SEO editor aims to be a universal SEO editor for meta titles and descriptions regardless of the SEO plugin or theme you’re currently using.
Allows you bulk edit meta Titles and Descriptions of your pages, posts or WooCommerce products.
We support lots of plugins and themes.
The plugin will read the meta title and description even if the supported plugin is not installed anymore.

= Features / Benefits =
* Manage SEO of your site from one single page.
* Export a selection into Excel (CSV), modify it and then upload the modified file.
* Allows you to quickly update meta title and description for various supported plugins even if you don't have them installed or activated anymore
* You can search for a specific post, page or WooCommerce product ID or keyword
* Smart update: if properties weren't changed it won't do an update to save server resources
* Search by post status: Published, Draft or Pending Review
* You can quickly navigate to the public link of the edited object or edit it within the WP admin.
* WordPress Roles: Editor or Admin can edit the meta/SEO fields
* Loads minimal files when the plugin is not running. Doesn't load at all for WP Heartbeat requests.
* Secure and efficient

> Pro version:
If you'd like more features and you should check the Pro version.
It builds on top of the free version. It supports more SEO plugins, themes and more fields.
> <a href="https://orbisius.com/go/seo-editor-pro/" target="_blank">https://orbisius.com/go/seo-editor-pro/</a>

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

== Bugs | Suggestions | Support ==

Please check the currently opened tickets and comment if there's already an existing one or open a new one.
> <a href="https://github.com/orbisius/orbisius-seo-editor/issues" target="_blank">https://github.com/orbisius/orbisius-seo-editor/issues</a>

== Customizations | For Hire | New Feature Requests ==

If you'd like us to develop a feature (paid service) feel free to reach out for a free quote.
<a href="https://orbisius.com/free-quote" target="_blank">Free Quote</a>

= Author =
Svetoslav Marinov (Slavi) | <a href="https://orbisius.com" title="Custom Web Programming, Web Design, e-commerce, e-store, Wordpress Plugin Development Development in Niagara Falls, St. Catharines, Ontario, Canada" target="_blank">Custom Web and WordPress Development by Orbisius.com</a>

== Upgrade Notice ==
* Click update and pray ... just kidding. Our code (almost) always works :)

== Screenshots ==
1. Showing the search page
2. Showing the search result with boxes to edit

== Installation ==

1. Go to WP-Admin > Plugins > Add New
1. Upload the plugin's zip the package or search for SEO Editor
1. Activate the plugin through the 'Plugins' menu in WordPress
1. To go Tools > SEO Editor

== Frequently Asked Questions ==

= How to use this plugin? =
* Install the plugin and activate it.
* Go to the Tools > Orbisius SEO Editor
* Decide what meta information you will edit and go!
* Then either save the changes or export to CSV

== Changelog ==

= 1.0.3 =
* Tested with latest WP
* Fixed Deprecated: auto_detect_line_endings is deprecated
* Updated csv parsing buffer to 2k
* Skip the first 5 non-empty rows if they have X or fewer columns. Sometimes people enter content before the heading columns.
* Fixes

= 1.0.2 =
* Added focus keyword to the WordPress SEO

= 1.0.1 =
* Validate CSV columns in Import. They have to be there.
* Fixed warnings after import

= 1.0.0 =
* Initial release
