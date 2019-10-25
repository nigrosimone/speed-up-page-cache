=== Speed Up - Page Cache ===
Contributors: nigro.simone
Donate link: http://paypal.me/snwp
Tags: optimize, front-end optimization, performance, speed, web performance optimization, wordpress optimization tool
Requires at least: 3.5
Tested up to: 5.2
Stable tag: 1.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple plugin to make your site run lightning fast with page caching.

== Description ==

Speed Up - Page Cache was constructed for made caching dead simple. Simple one-click install. That's it. No configuration are provided.
Improves the SEO and user experience of your site by increasing website performance, reducing load times.

You can choose, in several way, which page doesn't cache. 

1) Adding a filter in your function.php, eg.:

`function exclude_from_cache(){
    // exclude page, if the uri contains page-do-not-cache
	return false !== strpos($_SERVER['REQUEST_URI'], 'page-do-not-cache');
}
add_filter('speed-up-page-cache-cacheable', 'exclude_from_cache');`

2) Adding the string `DONOTCACHEPAGE` into the page, (eg. in a content of the page you have to write: `<!-- DONOTCACHEPAGE -->`)

3) Defining the costant `DONOTCACHEPAGE` with `true`, eg.:

`if( !defined('DONOTCACHEPAGE') ){
    define('DONOTCACHEPAGE', true);
}`

The page are cached only in certain condition, when a page can't be stored into the cache, in the response is added a header `x-supc-miss`, eg.:

`x-supc-miss: speed-up-page-cache-cacheable filter return true`

some of those conditions are:
1. The HTTP request method is GET
2. The HTTP request don't have query string parameters
3. The current user isn't authenticated
4. The current post is public

The cache is totally purged everyday (24 houres) by WordPress cron job. 
The single page are also purged on every update.

You can trigger a complete cache purge with:

`do_action('supc_purge_cache');`

or a single post cache purge with:

`// $postID is the post id
do_action('supc_purge_cache_post', $postID);`

Into the admin toolbar are also present some button for trigger the purge.


== Installation ==

1. Upload the complete `speed-up-page-cache` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==

= 1.0.4 =
* Better admin page

= 1.0.3 =
* Add admin bar utility for flush cache
* Add plugin page (draft)

= 1.0.2 =
* Add filter speed-up-page-cache-cacheable.
* Add action supc_purge_cache_post.
* Read me.

= 1.0.1 =
* Read me.

= 1.0.0 =
* Initial release.