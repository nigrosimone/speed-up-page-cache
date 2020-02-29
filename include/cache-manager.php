<?php
require_once 'cache-utils.php';
require_once 'config-manager.php';

if ( !defined('ABSPATH') ) exit;

class SpeedUp_CacheManager {
    
    /**
     * Instance of the object.
     *
     * @since  1.0.0
     * @static
     * @access public
     * @var null|SpeedUp_CacheManager
     */
    public static $instance = null;
    
    /**
     * Instance of SpeedUp_ConfigManager.
     *
     * @since  1.0.5
     * @access private
     * @var null|SpeedUp_ConfigManager
     */
    private $config = null;
    
    
    /**
     * Access the single instance of this class.
     *
     * @since  1.0.0
     * @access public
     * @return SpeedUp_CacheManager
     */
    public static function get_instance() 
    {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     *
     * @since  1.0.0
     * @access private
     * @return SpeedUp_CacheManager
     */
    private function __construct()
    {
        $this->config = SpeedUp_ConfigManager::get_instance();
    }
    
    /**
     * Output buffering.
     *
     * @since  1.0.0
     * @access public
     * @return string
     */
    public function ob_start($buffer) 
    {
        // Check if request is cacheable
        if ( !$this->cacheable() ) {
            return $buffer;
        }
        
        // Don't cache non html buffer
        if (stripos($buffer, '</html>') === false) {
            $this->sendHeaderMiss('response not html');
            return $buffer;
        }
        
        // Don't cache buffer with DONOTCACHEPAGE string
        if (stripos($buffer, 'DONOTCACHEPAGE') !== false) {
            $this->sendHeaderMiss('response contains DONOTCACHEPAGE string');
            return $buffer;
        }
        
        $url_path = $this->get_url_path();
        
        if( empty($url_path) ){
            return $buffer;
        }
        
        $cache_dir = SpeedUp_CacheUtils::get_cache_dir();
        
        $path = $cache_dir . $url_path;
        
        // Make sure we can read/write files to cache dir
        if ( !is_dir( $path ) ) {
            if ( !@mkdir( $path, 0777, true ) ) {
                return $buffer;
            }
        }
        
        @file_put_contents( $path . '_index.html', $buffer . "\n<!-- Cache served by Speed Up - Page Cache, last modified: " . gmdate( 'D, d M Y H:i:s', time() ) . " GMT -->\n" );
        
        return $buffer;
    }
    
    /**
     * Optionally serve cache and exit
     *
     * @since 1.0
     * @access public
     * @return void
     */
    public function serve_file_cache() 
    {
        // Check if request is cacheable
        if ( !$this->cacheable() ) {
            return;
        }
        
        $url_path = $this->get_url_path();
        
        if( empty($url_path) ){
            return;
        }
        
        $cache_dir = SpeedUp_CacheUtils::get_cache_dir();
        
        $path = $cache_dir . $url_path . '_index.html';
        
        if ( @file_exists( $path ) && @is_readable( $path ) ) {
            header('x-supc-managedby: PHP');
            @readfile( $path );
            exit;
        }
    }
    
    /**
     * Check if request is cacheable.
     * 
     * @since 1.0.0
     * @access private
     * @return boolean
     */
    private function cacheable()
    {
        global $post;
        
        // Cache only HTTP GET request
        if ( !isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'GET') {
            $this->sendHeaderMiss('REQUEST_METHOD not GET');
            return false;
        }
        
        // Don't cache when URL query string are defined
        if ($_SERVER['QUERY_STRING'] !== '') {
            $this->sendHeaderMiss('QUERY_STRING not empty');
            return false;
        }
        
        // Don't cache when DONOTCACHEPAGE is true
        if( defined('DONOTCACHEPAGE') && DONOTCACHEPAGE ){
            $this->sendHeaderMiss('DONOTCACHEPAGE costant is true');
            return false;
        }
        
        // Don't cache during installing
        if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ){
            $this->sendHeaderMiss('WP_INSTALLING costant is true');
            return false;
        }
        
        // Don't cache ajax request
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ){
            $this->sendHeaderMiss('DOING_AJAX costant is true');
            return false;
        }
        
        // Don't cache WordPress cron request
        if ( defined('DOING_CRON') && DOING_CRON ) {
            $this->sendHeaderMiss('DOING_CRON costant is true');
            return false;
        }
        
        // Don't cache WordPress admin
        if ( defined('WP_ADMIN') ) {
            $this->sendHeaderMiss('WP_ADMIN costant defined');
            return false;
        }
        
        // Don't cache when is load only the half of WordPress
        if ( defined('SHORTINIT') && SHORTINIT ) {
            $this->sendHeaderMiss('SHORTINIT costant is true');
            return false;
        }
        
        // Don't cache Atom Publishing Protocol request
        if ( defined( 'APP_REQUEST' ) && APP_REQUEST ) {
            $this->sendHeaderMiss('APP_REQUEST costant is true');
            return false;
        }
        
        // Don't cache XML-RPC API
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            $this->sendHeaderMiss('XMLRPC_REQUEST costant is true');
            return false;
        }
        
        // Don't cache in console mode
        if ( PHP_SAPI === 'cli' ) {
            $this->sendHeaderMiss('PHP_SAPI is cli');
            return false;
        }
        
        // Don't cache if session is defined
        if ( defined( 'SID' ) && SID != '' ) {
            $this->sendHeaderMiss('SID costant not empty');
            return false;
        }
        
        // Don't cache if user is logged
        if ( $this->has_cookie() ) {
            $this->sendHeaderMiss(' request ha rejected COOKIES');
            return false;
        }
        
        // Don't cache admin page
        if ( function_exists('is_admin') && is_admin() ) {
            $this->sendHeaderMiss('is_admin return true');
            return false;
        }
        
        // Don't cache password protected.
        if ( isset($post) && !empty($post->post_password) ) {
            $this->sendHeaderMiss('post has password');
            return false;
        }
        
        // check if SUPC_CACHEABLE is false
        if( defined( 'SUPC_CACHEABLE' ) && SUPC_CACHEABLE === false ){
            $this->sendHeaderMiss('SUPC_CACHEABLE costant is false');
            return false;
        }
        
        // check if wp-login.php page
        if( strpos($_SERVER["SCRIPT_NAME"], 'wp-login.php') !== false ){
            $this->sendHeaderMiss('wp-login.php not cacheable');
            return false;
        }
        
        $url = SpeedUp_CacheUtils::get_url();
        
        // check if filter is false
        if( apply_filters('speed_up_page_cache_cacheable', $url) === false ){
            $this->sendHeaderMiss('speed_up_page_cache_cacheable filter return false');
            return false;
        }
        
        // check if current url is in cache_exception_urls
        $cache_exception_urls = $this->config->get('cache_exception_urls');
        if( $url && is_array($cache_exception_urls) && in_array($url, $cache_exception_urls) ){
            $this->sendHeaderMiss('url is in cache_exception_urls');
            return false;
        }
        
        // check if 404
        if( function_exists( 'is_404' ) && is_404() ){
            $this->sendHeaderMiss('404 page not found');
            return false;
        }
        
        // check if rest request
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            $this->sendHeaderMiss('rest request');
            return false;
        }
        
        // check if feed
        if( function_exists( 'is_feed' ) && is_feed() ){
            $this->sendHeaderMiss('feed request');
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if request has cookie.
     *
     * @since 1.0.0
     * @access private
     * @return boolean
     */
    private function has_cookie()
    {
        // Don't cache if user has cookie.
        if ( ! empty( $_COOKIE ) ) {
            
            $wp_cookies = array( 'comment_author', 'wp-postpass', 'wordpress_logged_in', 'wptouch_switch_toggle' );
            
            foreach ( $_COOKIE as $key => $value ) {
                foreach ( $wp_cookies as $cookie ) {
                    if ( strpos( strtolower($key), $cookie ) !== false ) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    /**
     * Send the miss header.
     *
     * @since  1.0.2
     * @access private
     * @return string
     */
    private function sendHeaderMiss($reason)
    {
        if( !headers_sent() ){
            header('x-supc-miss: ' . $reason );
        }
    }
    
    /**
     * Get URL path for caching
     *
     * @since  1.0.0
     * @access private
     * @return string
     */
    private function get_url_path() 
    {
        $host = SpeedUp_CacheUtils::get_host();
        if( empty($host) ){
            return null;
        }
        $uri = strtok($_SERVER['REQUEST_URI'], '?');
        $path = str_replace('/', DIRECTORY_SEPARATOR, $host . $uri);
        return trim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
