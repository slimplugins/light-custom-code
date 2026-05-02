=== Light Custom Code ===
Contributors:       slimplugins
Tags:               custom code, php snippets, custom css, head footer, code manager
Requires at least:  6.0
Tested up to:       6.9
Requires PHP:       7.4
Stable tag:         1.0.0
License:            GPL-2.0-or-later
License URI:        https://www.gnu.org/licenses/gpl-2.0.html

Add custom PHP snippets, responsive CSS, and head/footer code without a child theme. All customisations survive theme updates.

== Description ==

**Light Custom Code** gives you the same power as editing a child theme's `functions.php` — without needing a child theme. All code is stored in the WordPress database and survives every theme update.

= Features =

* **PHP Snippets** — Add PHP code snippets with names, priorities, and active/inactive toggles. Snippets are compiled to a cache file (no `eval()`).
* **Head & Footer Code** — Inject raw HTML, meta tags, Google Tag Manager, verification codes, or any script into `<head>` or before `</body>`.
* **Custom CSS** — Four CSS editors: Global (all screens), Desktop only, Tablet, and Mobile. Each responsive section is automatically wrapped in the correct `@media` query.
* **Adjustable breakpoints** — Configure your own tablet and mobile breakpoints.
* **Dark / Light editor theme** — Switch the code editor between dark and light themes. Preference is saved per-browser.
* **PHP syntax validation** — Active snippets are checked for syntax errors before saving. You are shown the exact line and error message.
* **Three-layer safety net** — Syntax check before save → file-based shutdown handler → emergency recovery URL.
* **No child theme required** — Everything survives theme updates.
* **WordPress Coding Standards** — Written to the full WPCS ruleset.

= Safety & Error Recovery =

If a snippet causes a fatal PHP error the plugin protects your site automatically:

1. **Syntax check** — Errors are caught before the snippet is saved as active.
2. **Shutdown handler** — If a runtime fatal error occurs, a filesystem sentinel file is written before any database call. The next page load detects this file and skips all snippet execution, restoring site access immediately — even if the database is also unavailable.
3. **Recovery URL** — An emergency URL is shown on the snippets page. If your WordPress admin becomes inaccessible, visiting this URL disables all snippets and redirects you to the admin. The URL is single-use and regenerated after each use.

= Usage =

1. Activate the plugin.
2. Go to **Custom Code** in the admin sidebar.
3. Use the **PHP Snippets** tab to add PHP code (just like `functions.php`).
4. Use the **Head & Footer** tab to inject HTML/scripts into `<head>` or before `</body>`.
5. Use the **Custom CSS** tab to add global or responsive CSS.

= Security =

This plugin intentionally allows `manage_options` administrators to execute arbitrary PHP and inject raw HTML — the same trust level as WordPress's built-in Theme and Plugin File Editors. Do not grant the `manage_options` capability to untrusted users.

All admin pages require `manage_options`. All forms are protected by nonces. The cache directory is protected from direct HTTP access.

== Installation ==

1. Upload the `light-custom-code` folder to `/wp-content/plugins/`.
2. Activate through the **Plugins** screen in WordPress.
3. Go to **Custom Code** in the admin sidebar.

== Frequently Asked Questions ==

= Will my snippets survive a theme update? =
Yes. All data is stored in the WordPress database, which is independent of your theme.

= Do I need a child theme? =
No. That is the whole point of this plugin.

= Where is the PHP cache file stored? =
In `wp-content/uploads/light-custom-code/active-snippets.php`. The directory is protected by an `.htaccess` file that denies direct HTTP access.

= What happens if a snippet crashes my site? =
The plugin has three layers of protection. First, PHP syntax errors are caught before saving. Second, if a runtime fatal error occurs, a filesystem flag is written during shutdown and snippet execution is skipped on the next request. Third, an emergency recovery URL is shown on the snippets page that disables all snippets even if the WordPress admin is inaccessible.

= Is it safe? =
All admin pages require `manage_options`. All forms use nonces. Snippets are compiled to a cache file instead of executed with `eval()`. The cache directory is protected from direct HTTP access. The emergency recovery URL uses `hash_equals()` for constant-time comparison and is regenerated after each use.

= What if I want to keep a snippet with a syntax error? =
Save it as **inactive**. Syntax validation only runs when saving a snippet as active. Inactive snippets are saved as-is so you can draft, fix, and activate later.

== Screenshots ==

1. PHP Snippets list with active/inactive toggles and emergency recovery URL.
2. Snippet editor with dark-themed code editor and inline error banner.
3. Head & Footer code editors side by side.
4. Custom CSS editor with responsive tabs and breakpoint settings.

== Changelog ==

= 1.0.0 =
* Initial release.
* PHP Snippets with priority and active/inactive toggle.
* Head & Footer code injection.
* Custom CSS with Global, Desktop, Tablet, and Mobile tabs.
* Configurable breakpoints.
* Dark / Light code editor theme switcher.
* PHP syntax validation before save.
* Three-layer fatal error protection: syntax check, file-based shutdown handler, emergency recovery URL.
* WP_Filesystem for all file writes.
* Full uninstall cleanup via uninstall.php.
* WordPress Coding Standards compliant.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
