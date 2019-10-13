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
    public static function get_host() {
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
     * Get cache directory
     *
     * @since  1.0.0
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
     * Recursive glob.
     *
     * @since  1.0.0
     * @access private
     * @param  string $pattern
     * @param  int    $flags
     * @return array
     */
    public static function rglob($pattern, $flags = 0) {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::rglob($dir.'/'.basename($pattern), $flags));
        }
        return $files;
    }
}