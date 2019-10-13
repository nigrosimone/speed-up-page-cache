<?php
/*
 * Plugin Name: Speed Up - Page Cache
 * Plugin URI: http://wordpress.org/plugins/speed-up-page-cache/
 * Description: A simple page caching plugin.
 * Version: 1.0.2
 * Author: Simone Nigro
 * Author URI: https://profiles.wordpress.org/nigrosimone
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH'))
    exit();

require_once 'include/cache-utils.php';

class SpeedUp_PageCache
{
    const PLUGIN_NAME = 'Speed Up - Page Cache';

    private static $HTACCESS_SECTION_START = null;
    private static $HTACCESS_SECTION_END   = null;

    /**
     * Instance of the object.
     *
     * @since 1.0.0
     * @static
     * @access public
     * @var null|SpeedUp_PageCache
     */
    public static $instance = null;

    /**
     * Access the single instance of this class.
     *
     * @since 1.0.0
     * @access public
     * @return SpeedUp_PageCache
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     * @access private
     * @return SpeedUp_PageCache
     */
    private function __construct()
    {
        self::$HTACCESS_SECTION_START = '# BEGIN '.self::PLUGIN_NAME;
        self::$HTACCESS_SECTION_END   = '# END '.self::PLUGIN_NAME;
        
        add_action('deactivate_' . plugin_basename(__FILE__), array($this, 'deactivate'));
        add_action('activate_' . plugin_basename(__FILE__), array($this, 'activate'));
        
        // when post status is changed to draft - it looses its URL
        // so we need to flush before update is happened
        add_action( 'pre_post_update', array( $this, 'on_post_change'), 0 );
        add_action( 'wp_trash_post', array($this, 'on_post_change'), 0 );
        add_action( 'publish_post', array($this, 'on_post_change'), 0, 2 );
        add_action( 'switch_theme', array($this, 'on_change'), 0 );
        add_action( 'wp_update_nav_menu', array($this, 'on_change'), 0 );
        add_action( 'edit_user_profile_update', array($this, 'on_change'), 0 );
        add_action( 'edited_term', array($this, 'on_change'), 0 );
        
        // cron job
        add_action( 'supc_purge_cache', array( $this, 'purge_cache' ) );
        add_action( 'init', array( $this, 'schedule_events' ) );
  
        // others action
        add_action( 'supc_purge_cache_post', array( $this, 'on_post_change' ) );
    }

    /**
     * Plugin activate.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function activate()
    {
        // copy dropin
        if( $this->dropin_add() ){
        
            // set WP_CACHE to true
            $this->wp_config_add_wp_cache();
        
            // add htaccess rule
            $this->htaccess_add_rules();
        }
    }

    /**
     * Plugin deactivate.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function deactivate()
    {
        // set WP_CACHE to false
        if( $this->wp_config_remove_wp_cache() ){
        
            // remove dropin
            $this->dropin_remove();
                
            // remove htaccess rule
            $this->htaccess_remove_rules();
        }
    }
    
    /**
     * Automatically purge all cache.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function purge_cache(){
        $cache_dir = SpeedUp_CacheUtils::get_cache_dir();
        
        $paths = SpeedUp_CacheUtils::rglob($cache_dir . '*' . DIRECTORY_SEPARATOR . '_index.html', GLOB_NOSORT);
        
        foreach ($paths as $path){
            @unlink($path);
        }
    }
    
    /**
     * Automatically purge all page cache on post changes.
     * 
     * @since  1.0.0
     * @access public
     * @param  int $post_id Post id.
     * @return void
     */
    public function on_post_change( $post_id ) 
    {
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
            
            $cache_dir = SpeedUp_CacheUtils::get_cache_dir();
            
            foreach ($urls as $url){ 
                $path = SpeedUp_CacheUtils::url_to_path($url);
                @unlink($cache_dir . $path . '_index.html');
            }
        } 
    }
    
    /**
     * Setup cron jobs.
     *
     * @since 1.0.0
     * @access public
     */
    public function schedule_events() {
        if (!wp_next_scheduled ( 'supc_purge_cache' )) {
            wp_schedule_event( time(), 'daily', 'supc_purge_cache' );
        }
    }
    
    /**
     * WordPress core changes.
     *
     * @since 1.0.0
     * @access public
     */
    public function on_change() {
        $this->purge_cache();
    }
    
    /**
     * Add advanced-cache.php dropin.
     *
     * @since 1.0.0
     * @access private
     * @return boolean
     */
    private function dropin_add()
    {
        if( $this->dropin_remove() ){
            $source = __DIR__ . DIRECTORY_SEPARATOR . 'dropin' . DIRECTORY_SEPARATOR . 'advanced-cache.php';
            $dest = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php';
            return copy($source, $dest);
        }
        return false;
    }
    
    /**
     * Add advanced-cache.php dropin.
     *
     * @since 1.0.0
     * @access private
     * @return boolean
     */
    private function dropin_remove()
    {
        $filename = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php';
        if( file_exists($filename) ){
            return @unlink(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php');
        }
        return true;
    }
    
    
    /**
     * Add the htaccess rule.
     *
     * @since  1.0.0
     * @access private
     * @return boolean
     */
    private function htaccess_add_rules() {
        return $this->toggle_rulse_from_htaccess_content(true);
    }
    
    /**
     * Remove the htaccess rule.
     *
     * @since  1.0.0
     * @access private
     * @return boolean
     */
    private function htaccess_remove_rules() {
        return $this->toggle_rulse_from_htaccess_content(false);
    }
    
    /**
     * Remove the htaccess rule and add if $add.
     *
     * @since  1.0.0
     * @access private
     * @param  boolean $add Add the ruel.
     * @return boolean
     */
    private function toggle_rulse_from_htaccess_content($add) {
        
        if( empty(self::$HTACCESS_SECTION_START) || empty(self::$HTACCESS_SECTION_END) ){
            return false;
        }
        
        $htaccess_path = $this->htaccess_path();
        
        // Couldn't find htaccess.
        if ( ! $htaccess_path ) {
            return false;
        }
        
        $config_file_string = @file_get_contents( $htaccess_path );
        
        // htaccess file is empty. Maybe couldn't read it?
        if ( empty( $config_file_string ) ) {
            return false;
        }
        
        $old_lines = explode( PHP_EOL, $config_file_string );
        
        // remove my rules
        if( !empty($old_lines) && is_array($old_lines) ){
            
            $speed_up_directives = null;
            
            // loop over the htaccess lines
            for($i = 0, $e = count($old_lines); $i < $e; $i++) {
                
                $line = $old_lines[$i];
                
                // when we find the first line of Speed Up directives
                if( strpos($line, self::$HTACCESS_SECTION_START) === 0 ) {
                    $speed_up_directives = true;
                }
                
                // remove the line if is in a Speed Up section
                if( $speed_up_directives === true ){
                    unset($old_lines[$i]);
                }
                
                // when we find the last line of Speed Up directives
                if( strpos($line, self::$HTACCESS_SECTION_END) === 0 ) {
                    $speed_up_directives = false;
                    break; // end of operation, exit for
                }
            }
            
            if( !is_null($speed_up_directives) ){
                // broken htaccess!
                if( $speed_up_directives === true ){
                    return false;
                }
            }
            
            // reindex
            $new_lines = array_values($old_lines);
            
            // add the new line at the beginning
            if( $add ){
                if( !isset($_SERVER['DOCUMENT_ROOT']) ){
                    return false;
                }
                
                $root_dir  = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
                $cache_dir = str_replace('\\', '/', SpeedUp_CacheUtils::get_cache_dir());
                
                if( empty($root_dir) || empty($cache_dir) ){
                    return false;
                }
                
                $cache_path = str_replace($root_dir, '', $cache_dir);
                
                $my_lines  = array();
                $my_lines[] = self::$HTACCESS_SECTION_START;
                $my_lines[] = '<IfModule mod_rewrite.c>';
                $my_lines[] = 'RewriteEngine On';
                $my_lines[] = 'RewriteBase /';
                $my_lines[] = 'RewriteCond %{REQUEST_METHOD} =GET';
                $my_lines[] = 'RewriteCond %{QUERY_STRING} =""';
                $my_lines[] = 'RewriteCond %{HTTP_COOKIE} !(comment_author|wp\-postpass|wordpress_logged_in|wptouch_switch_toggle) [NC]';
                $my_lines[] = 'RewriteCond %{REQUEST_URI} \/$';
                $my_lines[] = 'RewriteCond %{HTTP_HOST} ([^:]+)';
                $my_lines[] = 'RewriteCond %{DOCUMENT_ROOT}'. $cache_path .'%1/%{REQUEST_URI}/_index.html -f';
                $my_lines[] = 'RewriteRule ^(.*) "'. $cache_path .'%1/%{REQUEST_URI}/_index.html" [L]';
                $my_lines[] = '</IfModule>';
                $my_lines[] = self::$HTACCESS_SECTION_END;
                $new_lines = array_merge($my_lines, $old_lines);
            }
            
            return @file_put_contents( $htaccess_path, implode( PHP_EOL, $new_lines ) );
        }
        
        return false;
    }
    
    /**
     * Remove WP_CACHE from wp-config.php
     *
     * @since 1.0.0
     * @access private
     * @return boolean
     */
    private function wp_config_remove_wp_cache()
    {
        return $this->toggle_wp_cache_from_wp_config_content(false);
    }

    /**
     * Add WP_CACHE to wp-config.php
     *
     * @since 1.0.0
     * @access private
     * @param string $config_data wp-config.php content
     * @return boolean
     */
    private function wp_config_add_wp_cache()
    {
        return $this->toggle_wp_cache_from_wp_config_content(true);
    }

    /**
	 * Toggle WP_CACHE on or off in wp-config.php
	 *
	 * @param  boolean $status Status of cache.
	 * @access private
	 * @since  1.0.0
	 * @return boolean
	 */
    private function toggle_wp_cache_from_wp_config_content( $status ) {

		if ( defined( 'WP_CACHE' ) && WP_CACHE === $status ) {
			return true;
		}
		
		$config_path = $this->wp_config_path();

		// Couldn't find wp-config.php.
		if ( ! $config_path ) {
			return false;
		}

		$config_file_string = @file_get_contents( $config_path );

		// Config file is empty. Maybe couldn't read it?
		if ( empty( $config_file_string ) ) {
			return false;
		}

		$config_file = explode( PHP_EOL, $config_file_string );

		// remove all WP_CACHE constant line
		$match = null;
		foreach ( $config_file as $key => $line ) {
			if ( ! preg_match( '/^\s*define\(\s*(\'|")([A-Z_]+)(\'|")(.*)/', $line, $match ) ) {
				continue;
			}

			if ( 'WP_CACHE' === $match[2] ) {
				unset( $config_file[ $key ] );
			}
		}

		$status_string = ( $status ) ? 'true' : 'false';

		array_shift( $config_file );
		array_unshift( $config_file, '<?php', 'define( "WP_CACHE", '. $status_string .' ); // ' . self::PLUGIN_NAME );

		if ( ! @file_put_contents( $config_path, implode( PHP_EOL, $config_file ) ) ) {
			return false;
		}
		
		if (function_exists('opcache_reset')) {
		    opcache_reset();
		}

		return true;
	}

    /**
     * Returns wp-config.php path
     *
     * @since 1.0.0
     * @access private
     * @return string
     */
    private function wp_config_path()
    {
        $wp_config = 'wp' . '-' . 'config'. '.' . 'php';
        $search = array(
            ABSPATH . $wp_config,
            dirname( ABSPATH ) . DIRECTORY_SEPARATOR . $wp_config
        );
        foreach ( $search as $path ) {
            if ( file_exists( $path ) ) {
                return $path;
            }
        }
        return null;
    }
    
    /**
     * Returns .htaccess path
     *
     * @since 1.0.0
     * @access private
     * @return string
     */
    private function htaccess_path()
    {
        $search = array(
            ABSPATH . '.htaccess',
            dirname( ABSPATH ) . DIRECTORY_SEPARATOR . '.htaccess'
        );
        foreach ( $search as $path ) {
            if ( file_exists( $path ) ) {
                return $path;
            }
        }
        return null;
    }
}

// Init
SpeedUp_PageCache::get_instance();