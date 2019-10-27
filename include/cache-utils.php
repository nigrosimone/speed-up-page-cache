<?php

if ( !defined('ABSPATH') ) exit;

class SpeedUp_CacheUtils { 
    
    /**
     * Return the HTTP_HOST.
     *
     * @since 1.0.0
     * @static
     * @access public
     * @var null|string
     */
    public static function get_host() 
    {
        $host = ( isset( $_SERVER['HTTP_HOST'] ) ) ? $_SERVER['HTTP_HOST'] : '';
        if( empty($host) ){
            return null;
        }
        if (strpos($host, ':') !== false) {
            $host = strtok($host,':');
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
    public static function get_request($key, $default = null)
    {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
    }
    
    /**
     * Get cache directory
     *
     * @since  1.0.0
     * @static
     * @access public
     * @return string
     */
    public static function get_cache_dir()
    {
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
    public static function url_to_path($url)
    {
        $url_parsed = parse_url($url);
        
        $url_host = isset($url_parsed['host']) ? $url_parsed['host'] : '';
        
        if( empty($url_host) ){
            return null;
        }
        
        $url_path = isset($url_parsed['path']) ? $url_parsed['path'] : '';
        
        $path = str_replace('/', DIRECTORY_SEPARATOR, $url_host . $url_path);
        return trim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
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
    public static function path_to_url($path)
    {
        $cache_dir = self::get_cache_dir();
        $path = str_replace($cache_dir, '', $path);
        $path = str_replace('_index.html', '', $path);
        $path = str_replace('\\', '/', $path);
        
        if ( self::is_https() ){
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
    public static function is_https()
    {
        return ( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] == 443) );
    }
    
    /**
     * Recursive glob.
     *
     * @since  1.0.0
     * @static
     * @access public
     * @param  string $pattern
     * @param  int    $flags
     * @return array
     */
    public static function rglob($pattern, $flags = 0) 
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::rglob($dir.'/'.basename($pattern), $flags));
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
    public static function get_url()
    {
        if( !empty($_SERVER['HTTP_HOST']) ){
            return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
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
    public static function detect_post_id() 
    {
        global $posts, $comment_post_ID, $post_ID;
        
        if ( $post_ID ) {
            return $post_ID;
        } elseif ( $comment_post_ID ) {
            return $comment_post_ID;
        } elseif ( ( is_single() || is_page() ) && is_array( $posts ) ) {
            return $posts[0]->ID;
        } elseif ( is_object( $posts ) && property_exists( $posts, 'ID' ) ) {
            return $posts->ID;
        } elseif ( isset( $_REQUEST['p'] ) ) {
            return (integer) $_REQUEST['p'];
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
    public static function purge_cache()
    {
        $paths = self::cached_paths();
        
        if( empty($paths) ){
            return true;
        }
        
        $result = true;
        
        foreach ($paths as $path){
            if( !@unlink($path) ){
                $result = false;
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
    public static function cached_paths()
    {
        $cache_dir = self::get_cache_dir();
        
        return self::rglob($cache_dir . '*' . DIRECTORY_SEPARATOR . '_index.html', GLOB_NOSORT);
    }
    
    /**
     * Return all cached URLs.
     *
     * @since  1.0.5
     * @static
     * @access public
     * @return array
     */
    public static function cached_urls()
    {
        $cached_paths = self::cached_paths();
        $cached_urls = array();
        foreach ($cached_paths as $cached_path){
            array_push($cached_urls, self::path_to_url($cached_path));
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
    public static function purge_cache_post( $post_id )
    {
        if( $post_id <= 0 ){
            return false;
        }
        
        $post = get_post( $post_id );
        
        // if attachment changed - parent post has to be flushed
        // since there are usually attachments content like title
        // on the page (gallery)
        if ( $post->post_type == 'attachment' ) {
            $post_id = $post->post_parent;
            $post = get_post( $post_id );
        }
        
        if( !in_array( $post->post_type, array( 'revision', 'attachment' ) ) &&
            in_array( $post->post_status, array( 'publish' ) ) ){
                
            $urls = array();
                
            $urls[] = get_permalink( $post_id );
                
            $taxonomies = get_post_taxonomies( $post_id );
            $terms = wp_get_post_terms( $post_id, $taxonomies );
            foreach ( $terms as $term ) {
                $urls[] = get_term_link( $term, $term->taxonomy );
            }
                
            $urls[] = get_author_posts_url( $post->post_author );
            
            $result = true;
            foreach ($urls as $url){
                if( !self::purge_cache_url($url) ){
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
     * @param  int $url URL.
     * @return boolean
     */
    public static function purge_cache_url( $url )
    {
        if( empty($url) ){
            return false;
        }
        
        $cache_dir = self::get_cache_dir();
        $path = self::url_to_path($url);
        
        if( !empty($path) ){
            $filename = $cache_dir . $path . '_index.html';
            if( file_exists($filename) ){
                return @unlink($filename);
            } else {
                return true;
            }
        }
        
        return false;
    }
}