<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SpeedUp_CacheUtils {

	/**
	 * Return the HTTP_HOST.
	 *
	 * @since 1.0.0
	 * @static
	 * @access public
	 * @var null|string
	 */
	public static function get_host() {
		$host = ( isset( $_SERVER['HTTP_HOST'] ) ) ? $_SERVER['HTTP_HOST'] : '';
		if ( empty( $host ) ) {
			return null;
		}
		if ( strpos( $host, ':' ) !== false ) {
			$host = strtok( $host, ':' );
		}
		return $host;
	}

	/**
	 * Return a $_REQUEST parameter.
	 *
	 * @since 1.0.3
	 * @static
	 * @access public
	 * @return string
	 */
	public static function get_request( $key, $fallback = null ) {
		return isset( $_REQUEST[ $key ] ) ? $_REQUEST[ $key ] : $fallback;
	}

	/**
	 * Get cache directory
	 *
	 * @since  1.0.0
	 * @static
	 * @access public
	 * @return string
	 */
	public static function get_cache_dir() {
		return rtrim( WP_CONTENT_DIR, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'speed-up-page-cache' . DIRECTORY_SEPARATOR;
	}

	/**
	 * Get URL path for caching
	 *
	 * @since  1.0.0
	 * @static
	 * @access private
	 * @param  string $url
	 * @return string
	 */
	public static function url_to_path( $url ) {
		$url_parsed = parse_url( $url );

		$url_host = isset( $url_parsed['host'] ) ? $url_parsed['host'] : '';

		if ( empty( $url_host ) ) {
			return null;
		}

		$url_path = isset( $url_parsed['path'] ) ? $url_parsed['path'] : '';

		$path = str_replace( '/', DIRECTORY_SEPARATOR, $url_host . $url_path );
		return trim( $path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Get path by URL
	 *
	 * @since  1.0.4
	 * @static
	 * @access private
	 * @param  string $path
	 * @return string
	 */
	public static function path_to_url( $path ) {
		$cache_dir = self::get_cache_dir();
		$path      = str_replace( $cache_dir, '', $path );
		$path      = str_replace( '_index.html', '', $path );
		$path      = str_replace( '\\', '/', $path );

		if ( self::is_https() ) {
			$path = 'https://' . $path;
		} else {
			$path = 'http://' . $path;
		}

		return $path;
	}

	/**
	 * Return true if https is enabled
	 *
	 * @since  1.0.5
	 * @static
	 * @access private
	 * @return boolean
	 */
	public static function is_https() {
		if ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) {
			return true;
		}

		// SERVER_PORT non e' garantita: manca in CLI, in WP-CLI e in alcune
		// configurazioni FastCGI. Leggerla senza isset() produceva un warning a
		// ogni richiesta, e questo codice gira nel drop-in, prima che WordPress
		// gestisca gli errori: il warning finiva dentro la pagina messa in cache.
		return isset( $_SERVER['SERVER_PORT'] ) && 443 === (int) $_SERVER['SERVER_PORT'];
	}

	/**
	 * Query string parameters that only track where a visitor came from.
	 *
	 * They do not change the page, so a request carrying nothing else can be
	 * served from cache. This is the difference between caching the traffic that
	 * arrives from ads, social networks and newsletters, and not caching it at
	 * all.
	 *
	 * Deliberately not filterable: adding a parameter that does change the page
	 * would make the cache serve one visitor's version to everybody, and the
	 * filter would only apply on the write path anyway, since plugins are not
	 * loaded yet when the cache is read.
	 *
	 * @since  1.0.23
	 * @static
	 * @access private
	 * @return array
	 */
	private static function tracking_parameters() {
		return array(
			// Google Analytics and Google Ads.
			'gclid',
			'gclsrc',
			'gbraid',
			'wbraid',
			'dclid',
			'gad_source',
			'_ga',
			'_gl',
			// Meta.
			'fbclid',
			// Microsoft, LinkedIn, X, TikTok, Reddit, Pinterest.
			'msclkid',
			'li_fat_id',
			'twclid',
			'ttclid',
			'rdt_cid',
			'epik',
			// Instagram share links.
			'igshid',
			'igsh',
			// Yandex.
			'yclid',
			// Mailchimp.
			'mc_cid',
			'mc_eid',
			// HubSpot.
			'_hsenc',
			'_hsmi',
			'hsCtaTracking',
			// Klaviyo, Matomo.
			'_kx',
			'mtm_source',
			'mtm_medium',
			'mtm_campaign',
			'mtm_keyword',
			'mtm_content',
			'mtm_group',
			'mtm_placement',
			'mtm_cid',
		);
	}

	/**
	 * Prefixes whose parameters only track the visitor's origin.
	 *
	 * @since  1.0.23
	 * @static
	 * @access private
	 * @return array
	 */
	private static function tracking_parameter_prefixes() {
		return array(
			'utm_',   // Every Urchin parameter, including the newer utm_id and utm_creative_format.
			'hsa_',   // HubSpot Ads.
			'pk_',    // Piwik.
		);
	}

	/**
	 * Return true when the query string carries something that changes the page.
	 *
	 * A request with only tracking parameters is the same page as the request
	 * without them, so it can be served from cache. A request with "?s=",
	 * "?p=123" or "?paged=2" is a different page and must not be.
	 *
	 * @since  1.0.23
	 * @static
	 * @access public
	 * @return boolean
	 */
	public static function has_significant_query_string() {
		if ( empty( $_SERVER['QUERY_STRING'] ) ) {
			return false;
		}

		$parameters = array();

		// parse_str e' PHP puro: questo codice gira prima che WordPress esista.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		parse_str( $_SERVER['QUERY_STRING'], $parameters );

		$tracking = self::tracking_parameters();
		$prefixes = self::tracking_parameter_prefixes();

		foreach ( array_keys( $parameters ) as $name ) {
			if ( in_array( $name, $tracking, true ) ) {
				continue;
			}

			$is_prefixed = false;
			foreach ( $prefixes as $prefix ) {
				if ( 0 === strpos( $name, $prefix ) ) {
					$is_prefixed = true;
					break;
				}
			}

			if ( ! $is_prefixed ) {
				// Basta un parametro non riconosciuto per non fidarsi.
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursive glob.
	 *
	 * @since  1.0.0
	 * @static
	 * @access public
	 * @param  string $pattern
	 * @param  int    $flags
	 * @param  integer $max_deep Max children deep searching.
	 * @return array
	 */
	public static function rglob( $pattern, $flags = 0, $max_deep = 2 ) {
		$files = glob( $pattern, $flags );
		if ( $max_deep <= 0 ) {
			return $files;
		}
		$basename = basename( $pattern );
		foreach ( glob( dirname( $pattern ) . '/*', GLOB_ONLYDIR | GLOB_NOSORT ) as $dir ) {
			$files = array_merge( $files, self::rglob( $dir . '/' . $basename, $flags, $max_deep - 1 ) );
		}
		return $files;
	}

	/**
	 * Return current URL
	 *
	 * @since 1.0.3
	 * @static
	 * @access public
	 * @return string
	 */
	public static function get_url() {
		if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
			return ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}
		return null;
	}

	/**
	 * Detects post ID
	 *
	 * @since 1.0.3
	 * @static
	 * @access public
	 * @return integer
	 */
	public static function detect_post_id() {
		// $comment_post_ID e' un globale di WordPress core, impostato da
		// wp-comments-post.php: il nome non e' una nostra scelta e
		// rinominarlo significherebbe leggere una variabile che non esiste.
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		global $posts, $comment_post_ID, $post_ID;

		if ( $post_ID ) {
			return $post_ID;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		} elseif ( $comment_post_ID ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			return $comment_post_ID;
		} elseif ( ( is_single() || is_page() ) && is_array( $posts ) ) {
			return $posts[0]->ID;
		} elseif ( is_object( $posts ) && property_exists( $posts, 'ID' ) ) {
			return $posts->ID;
		} elseif ( isset( $_REQUEST['p'] ) ) {
			return (int) $_REQUEST['p'];
		}

		return 0;
	}

	/**
	 * Automatically purge all cache.
	 *
	 * @since  1.0.3
	 * @static
	 * @access public
	 * @return boolean
	 */
	public static function purge_cache() {
		$paths = self::cached_paths();

		return self::purge_paths( $paths );
	}

	/**
	 * Purge all paths.
	 *
	 * @since  1.0.7
	 * @static
	 * @param  array $paths
	 * @access public
	 * @return boolean
	 */
	public static function purge_paths( $paths ) {
		if ( empty( $paths ) ) {
			return true;
		}

		$result = true;

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				if ( ! @unlink( $path ) ) {
					$result = false;
				}
			}
		}

		return $result;
	}

	/**
	 * Return all cached paths.
	 *
	 * @since  1.0.4
	 * @static
	 * @access public
	 * @return array
	 */
	public static function cached_paths() {
		$cache_dir = self::get_cache_dir();

		return self::cached_child_paths( $cache_dir, PHP_INT_MAX );
	}

	/**
	 * Return all cached paths by parent path.
	 *
	 * @since  1.0.7
	 * @static
	 * @param  string $parent_path
	 * @param  integer $max_deep Max children deep searching.
	 * @access public
	 * @return array
	 */
	public static function cached_child_paths( $parent_path, $max_deep = 2 ) {
		if ( empty( $parent_path ) || ! is_dir( $parent_path ) ) {
			return array();
		}
		return self::rglob( $parent_path . '*' . DIRECTORY_SEPARATOR . '_index.html', GLOB_NOSORT, $max_deep );
	}

	/**
	 * Return all cached URLs.
	 *
	 * @since  1.0.5
	 * @static
	 * @access public
	 * @return array
	 */
	public static function cached_urls() {
		$cached_paths = self::cached_paths();
		$cached_urls  = array();
		foreach ( $cached_paths as $cached_path ) {
			array_push( $cached_urls, self::path_to_url( $cached_path ) );
		}
		return $cached_urls;
	}

	/**
	 * Automatically purge all page cache on post changes.
	 *
	 * @since  1.0.3
	 * @static
	 * @access public
	 * @param  int $post_id Post id.
	 * @return boolean
	 */
	public static function purge_cache_post( $post_id ) {
		if ( $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );

		// if attachment changed - parent post has to be flushed
		// since there are usually attachments content like title
		// on the page (gallery)
		if ( 'attachment' === $post->post_type ) {
			$post_id = $post->post_parent;
			$post    = get_post( $post_id );
		}

		if ( ! in_array( $post->post_type, array( 'revision', 'attachment' ), true ) &&
			in_array( $post->post_status, array( 'publish' ), true ) ) {

			$urls_with_children    = array();
			$urls_without_children = array();

			$urls_with_children[] = get_permalink( $post_id );

			$page_for_posts = get_option( 'page_for_posts' );
			if ( $page_for_posts ) {
				$urls_without_children[] = get_permalink( $page_for_posts );
			} else {
				$urls_without_children[] = get_home_url();
			}

			$taxonomies = get_post_taxonomies( $post_id );
			$terms      = wp_get_post_terms( $post_id, $taxonomies );
			foreach ( $terms as $term ) {
				$urls_with_children[] = get_term_link( $term, $term->taxonomy );
			}

			$urls_with_children[] = get_author_posts_url( $post->post_author );

			$result = true;
			foreach ( $urls_with_children as $url_with_children ) {
				if ( ! self::purge_cache_url( $url_with_children, 2 ) ) {
					$result = false;
				}
			}

			foreach ( $urls_without_children as $url_without_children ) {
				if ( ! self::purge_cache_url( $url_without_children, 0 ) ) {
					$result = false;
				}
			}

			return $result;
		}
	}

	/**
	 * Automatically purge URL.
	 *
	 * @since  1.0.3
	 * @static
	 * @access public
	 * @param  string $url URL.
	 * @param  integer $max_deep Max children deep searching.
	 * @return boolean
	 */
	public static function purge_cache_url( $url, $max_deep = 0 ) {
		if ( empty( $url ) ) {
			return false;
		}

		$cache_dir = self::get_cache_dir();
		$path      = self::url_to_path( $url );

		if ( ! empty( $path ) ) {
			$paths = array( $cache_dir . $path . '_index.html' );

			if ( $max_deep > 0 ) {
				$paths = array_merge( $paths, self::cached_child_paths( $cache_dir . $path, $max_deep ) );
			}

			return self::purge_paths( $paths );
		}

		return false;
	}

	/**
	 * Invalidates a cached script.
	 *
	 * @since  1.0.13
	 * @static
	 * @access public
	 * @param  string $script The path to the script being invalidated.
	 * @return boolean
	 */
	public static function opcache_invalidate( $script ) {
		if ( function_exists( 'opcache_invalidate' ) ) {
			return @opcache_invalidate( $script, true );
		}
		return false;
	}

	/**
	 * Return true when a post is something that renders every page.
	 *
	 * The Site Editor stores templates, template parts, global styles and
	 * navigation as posts. They have no URL of their own, so purging "their URL"
	 * is meaningless: what changes is every page that uses them.
	 *
	 * @since  1.0.24
	 * @static
	 * @access public
	 * @param  int $post_id
	 * @return boolean
	 */
	public static function post_affects_whole_site( $post_id ) {

		if ( ! function_exists( 'get_post_type' ) ) {
			return false;
		}

		$types = array(
			'wp_template',       // A block theme template.
			'wp_template_part',  // A header or footer.
			'wp_global_styles',  // Colours and typography from the Site Editor.
			'wp_navigation',     // A navigation block's menu.
		);

		return in_array( get_post_type( $post_id ), $types, true );
	}

	/**
	 * Return true when changing this option changes every page on the site.
	 *
	 * Checked from the "updated_option" hook, which fires on every option write,
	 * so it has to stay cheap: an in_array against a short list.
	 *
	 * @since  1.0.24
	 * @static
	 * @access public
	 * @param  string $option
	 * @return boolean
	 */
	public static function is_site_wide_option( $option ) {

		$options = array(
			'blogname',            // Shown in the header of every page.
			'blogdescription',
			'home',
			'siteurl',
			'permalink_structure', // Every link on the site changes.
			'show_on_front',
			'page_on_front',
			'page_for_posts',
			'posts_per_page',      // Repaginates every archive.
			'date_format',
			'time_format',
			'timezone_string',
			'gmt_offset',
			'sticky_posts',        // Reorders the blog page.
			'category_base',
			'tag_base',
		);

		return in_array( $option, $options, true );
	}
}
