=== Plugin Name ===
Contributors: eteubert
Donate link: http://FarBeyondProgramming.com/
Tags: podcast, archive, loop, shortcode
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: trunk

Shortcode Plugin to display a Podcast Archive by category.

== Description ==

= Quick Start =

The plugin assumes your podcasts are well categorized.
To display the archive, use the shortcode anywhere in a page or article.

	[podcast-archive-page category="podcast"]

Replace "podcast" with your category. Watch out, we need the slug here.
That's the category name without capital letters and spaces.

= Specifics =

You can specify a custom template to display the archive elements.
Go to `Preferences > Podcast Archive` for plugin preferences.
Use HTML and any of the following template placeholders.

- `%TITLE%` - The post title.
- `%PERMALINK%` - The post permalink.
- `%AUTHOR%` - The post author.
- `%CATEGORIES%` - The post categories as unordered list.
- `%CATEGORIES|...%` - The post categories with a custom separator. Example: `%CATEGORIES|, %`
- `%TAGS%` - The post tags with default separator.
- `%TAGS|...%` - The post tags with a custom separator. Example: `%TAGS|, %`
- `%EXCERPT%` - The post excerpt.
- `%POST_META|...%` - Any post meta. Example: `%POST_META|duration%`.
- `%DATE%` - The post date with default format.
- `%DATE|...%` - The post date with custom format. Example: `%DATE|Y/m/d%`
- `%POST_THUMBNAIL|...x...%` - The post thumbnail with certain dimensions. Example: `%POST_THUMBNAIL|75x75%`
- `%COMMENTS%` - The post comment count.

== Installation ==

1. Upload the `podcast-archive-page` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `[podcast-archive-page category="podcast"]` in your archive post or page

== Frequently Asked Questions ==

= W00t, it says I need PHP 5.3?! =

PHP 5.3 is available since June 2009.
It introduced some long overdue features to the language and I refuse to support legacy junk.
Please ask your hoster to update, kindly.

= Can I help to add a feature? =

That would be awesome!
Visit https://github.com/eteubert/Podcast-Archive-Page, fork the project, add your feature and create a Pull Request. I'll be happy to review and add your changes.

== Screenshots ==

1. The Admin Interface
2. Example Archive

== Changelog ==

= 0.9 =
* It's alive!

== Upgrade Notice ==

No updates yet. Be ready to upgrade to 1.0 once it's available.
