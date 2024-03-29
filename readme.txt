=== Archivist - Custom Archive Templates ===
Contributors: eteubert
Donate link: http://flattr.com/thing/396382/WordPress-Plugin-Archivist-Custom-Archive-Templates
Tags: archive, loop, shortcode, category, tag, custom, query, template, html, customizable
Requires at least: 3.0
Tested up to: 6.1.1
Stable tag: 1.7.7

Shortcode Plugin to display an archive by category, tag or custom query. Customizable via HTML templates.

== Description ==

= Quick Start =

The plugin assumes your articles are well categorized.
To display the archive, use the shortcode anywhere in a page or article.

	[archivist category="kitten"]
	[archivist tag="kitten"]

Replace "kitten" with your category/tag. Watch out, we need the slug here.
That's the name without capital letters and spaces.

= Placeholders =

You can specify a custom template to display the archive elements.
Go to `Settings > Archivist` for plugin preferences.
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
- `%POST_META|...|...%` - Any post meta list, separated by custom HTML. Example: `%POST_META|guest|<br>%`
- `%DATE%` - The post date with default format.
- `%DATE|...%` - The post date with custom format. Example: `%DATE|Y/m/d%`
- `%POST_THUMBNAIL|...x...%` - The post thumbnail with certain dimensions. Example: `%POST_THUMBNAIL|75x75%`
- `%COMMENTS%` - The post comment count.
- `%ACF|field_name%` - Display [ACF](https://www.advancedcustomfields.com) field. Uses the [`get_field()`](https://www.advancedcustomfields.com/resources/get_field/) function.

You can add your own placeholders:

    add_filter( 'archivist_template_render', function( $template, $post ) {
        // modify $template
        return $template;
    }, 10, 2 );

= Pagination =

If you have many entries, you may prefer to paginate. The archivist comes with asynchronous pagination built-in. Use the `pagination` parameter in the shortcode to activate it. Its value is the amount of items you want to display per page.

    [archivist category="kitten" pagination="10"]

Pagination controls are displayed both on top and at the bottom of the archive. You can customize this by setting `controls` to `top` or `bottom`.

    [archivist category="kitten" pagination="10" controls="top"]

= Filter by Query =

Are you feeling bold? Is filtering by category or archive not satisfying you? Read on, I've got a challenge for you.
WordPress uses a certain query syntax to define the so called loop which is used to display the archive.
You can find the complete documentation at http://codex.wordpress.org/Class_Reference/WP_Query
and you can take advantage of every single parameter or combination of parameters listed there. Some examples:

	[archivist query="year=1984&author_name=gorwell"]

Lists all entries from the year `1984` by the author with `user_nicename` `gorwell`.

	[archivist query="tag=straw+mask&post_status=private&orderby=comment_count&order=DESC"]

Lists all entries marked with post status `private` which are tagged with both `straw` and `mask`, ordered by the amount of comments in a descending order.

= Using multiple Templates =

When you install the plugin, there is just one templated called "default".
If you don't specify a specific template in the shortcode, this one will be used.
Therefore the following two shortcodes yield identical results.

	[archivist category="kitten"]
	[archivist category="kitten" template="default"]

You can add as many templates as you like. Think twice before deleting one. If it's still in use, the archive can't be displayed.

== Installation ==

1. Upload the `archivist` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `[archivist category="kitten"]` in your archive post or page

== Screenshots ==

1. The Admin Interface
2. Example Archive

== Changelog ==

= 1.7.7 =

* fix security issues (XSS)

= 1.7.5 =

* fix PHP 8 compatibility
* fix security issues

= 1.7.4 =

* add Chinese language files

= 1.7.3 =

* fix use of PHP 5.4 syntax

= 1.7.2 =

* fix: define own ajaxurl in case the global one is not defined

= 1.7.1 =

* fix: set jQuery as dependency

= 1.7 =

* New Feature: Pagination. Use `pagination="10"` as shortcode parameter to paginate your archive with 10 items per page. See readme for more details.
* New Feature: Support for ACF Fields. `%ACF|field_name%` - Display [ACF](https://www.advancedcustomfields.com) field. Uses the [`get_field()`](https://www.advancedcustomfields.com/resources/get_field/) function.

This update is sponsored by [Light Aware](http://lightaware.org/).

= 1.6 =

* Enhancement: Use Ace editor with HTML and CSS syntax highlighting
* Fix: PHP warnings
* Fix: Some links in about box

= 1.5.1 =
* Hotfix: Typo, sorry

= 1.5 =
* Enhancement: display_by_query: default to displaying all posts (like tag and category display)
* Feature: enable shortcodes in templates

= 1.4.1 =
* hotfix: forgot to deploy a new file to svn

= 1.4 =
* restore PHP 5.2 backwards compatibility

= 1.3.8 =
* fix default thumb bug
* add support for post meta lists
* add plugin repo banner

= 1.3.7 =
* fix typo (prevented custom css from being used)

= 1.3.6 =
* fix bug using query parameter in shortcode

= 1.3.4 & 1.3.5 =
* minor capability fix

= 1.3.3 =
* generic backslash fix

= 1.3.2 =
* you can now set any template as the default
* current default template more easily recognizable (bold font & marked in template chooser)
* add internal version number so update and compatibility scripts get run only when needed
* Bugfixes (Settings Validation, backslashes, ...)

= 1.3.1 =
* fix backward compatibility issue

= 1.3.0 =
* templates can be renamed
* "default" template can be renamed, too
* when you delete the last template, the "edit template" tab is deactivated until you create a new one
* some bug fixes

= 1.2.2, 1.2.3 =
* Hotfixes

= 1.2.1 =
* add missing textdomains

= 1.2.0 =
* allow for multiple templates
* add an examples block in the sidebar

= 1.1.0 =
* add fallback thumbnail
* new options page

= 1.0.1 =
* fix typos

= 1.0 =
* change name to Archivist - "Custom Archive Templates"
* change shortcode

= 0.9 =
* It's alive!

== Upgrade Notice ==

= 0.9 to 1.0 =
* change your shortcodes to [archivist ...]
