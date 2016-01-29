=== Suspensions for bbPress ===

Contributors: superbecc
Tags: bbpress, suspending, moderation
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds a bbPress 'suspended' role that denies commenting ability and can be assigned to a user for a specified period of time to prevent commenting on bbPress forums.

== Description ==

Adds a bbPress 'suspended' role that denies commenting ability on bbPress forums. When the admin creates a suspension, this role is assigned to the targeted user to temporarily silence them; the role is removed with wp-cron after their suspension period expires.

Settings allow the admin to specify a default suspension period, though this can also be altered for any individual suspension.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/suspensions-for-bbpress` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Under the 'Suspensions -> Options' menu, specify a default suspension period and a message to appear on the page informing any suspended user that they're temporarily unable to comment.

== To-Do's ==

* Bulk actions (delete, expire) not working yet
* Filtering (all/active/complete) to each show counts, with current view bolded
* Database insert/update triggers not thoroughly tested when conducting plugin update process