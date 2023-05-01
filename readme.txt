=== Speed Up - Page Cache ===
Contributors: nigro.simone
Donate link: http://paypal.me/snwp
Tags: seo, cache, page cache, caching, optimize, front-end optimization, performance, speed, web performance optimization, wordpress optimization tool
Requires at least: 3.5
Tested up to: 6.0
Stable tag: 1.0.20
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A very simple plugin to make your site run lightning fast with page caching.

== Description ==

Speed Up - Page Cache was constructed for made caching dead simple. Simple one-click install. That's it.

When a page is rendered, php and mysql are used. Therefore, system needs RAM and CPU.
If many visitors come to a site, system uses lots of RAM and CPU so page is rendered so slowly. In this case, you need a cache system not to render page again and again. Cache system generates a static html file and saves. Other users reach to static html page.

After a html file is generated your webserver will serve that file instead of processing the comparatively heavier and more expensive WordPress PHP scripts.

The static html files will be served to the vast majority of your users:
- Users who are not logged in.
- Users who have not left a comment on your blog.
- Or users who have not viewed a password protected post.
99% of your visitors will be served static html files. One cached file can be served thousands of times. 

In addition, the site speed is used in Google's search ranking algorithm so cache plugins that can improve your page load time will also improve your SEO ranking.

== Installation ==

1. Upload the complete `speed-up-page-cache` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==

= 1.0.19 =
* Tested up to Wordpress 6.0

= 1.0.18 =
* Fix mkdir

= 1.0.17 =
* Tested up to Wordpress 5.9

= 1.0.16 =
* Tested up to Wordpress 5.8

= 1.0.15 =
* Tested up to Wordpress 5.7

= 1.0.14 =
* Tested up to Wordpress 5.5

= 1.0.13 =
* Better opcache invalidation
* Add filter speed_up_page_cache_cacheable.

= 1.0.12 =
* Better opcache invalidation
* Better file cache invalidation

= 1.0.11 =
* Purge blog page whe publish a post

= 1.0.10 =
* Readme update

= 1.0.9 =
* Tested up to Wordpress 5.3
* Add action listener for clean_post_cache

= 1.0.8 =
* Purge taxonomy pagination

= 1.0.7 =
* Small fix
* Add monthly cron scheduler option

= 1.0.6 =
* Exclude wp-login.php from cache
* Exclude 404 page not found from cache
* Exclude rest request from cache
* Exclude feed from cache
* Add weekly cron scheduler option

= 1.0.5 =
* Add options in admin page

= 1.0.4 =
* Better admin page

= 1.0.3 =
* Add admin bar utility for flush cache
* Add plugin page (draft)

= 1.0.2 =
* Add action supc_purge_cache_post.
* Read me.

= 1.0.1 =
* Read me.

= 1.0.0 =
* Initial release.