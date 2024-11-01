=== Visit Notifications ===
Contributors: wphelpdeskuk
Tags: notification, post, visit, read, receipt
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 3.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Quickly receive email notifications when a visitor looks at a page or a quick summary of who visited a page.

== Description ==
Immediately receive email notifications when a visitor accesses a page on your site. Provides a user\'s basic information to you as soon as they visit the page, such as time of access, user agent, HTTP referrer, anonymised IP Address and approximate location and timezone.

And instead of immediately receiving an email about the visitor the email can be delayed by a day or hour which will send a visit summary.

This plugin includes a built-in copy of ACF, see https://www.advancedcustomfields.com/resources/including-acf-within-a-plugin-or-theme/

== Installation ==
1. Search for Visit Notifications in the WordPress Plugin Repo
2. Install the plugin
3. Activate the plugin

== Frequently Asked Questions ==

= Where is visit notification email sent to? =

The current version sends the email only to the admin email address (which can be found in Settings > General).

== Changelog ==
= 3.1.0 =
* chore: update branding to Watch The Dot / support.watchthedot.com
* fix: use static functions when $this is not referenced
  This fixes a memory leak standard to using anonymous functions in classes
* reactor: remove Plugin::__ helper method and instead use __ directly
* chore: tested up to WP 6.4

= 3.0.0 =
* First version released to WP Repo

For more information, see [the plugin page](https://support.watchthedot.com/our-plugins/visit-notifications)
