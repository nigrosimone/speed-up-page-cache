<?php
/*
 * Plugin Name: Speed Up - Page Cache
 * Plugin URI: http://wordpress.org/plugins/speed-up-page-cache/
 * Description: A simple page caching plugin.
 * Version: 1.0.5
 * Author: Simone Nigro
 * Author URI: https://profiles.wordpress.org/nigrosimone
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH'))
    exit();

require_once 'include/admin-manager.php';
require_once 'include/admin-notice.php';
require_once 'include/cache-utils.php';
require_once 'include/htaccess-utils.php';
require_once 'include/wpconfig-utils.php';
require_once 'include/dropin-utils.php';
require_once 'include/config-manager.php';

class SpeedUp_PageCache
{
    const PLUGIN_NAME = 'Speed Up - Page Cache';

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
     * Instance of SpeedUp_ConfigManager.
     *
     * @since 1.0.5
     * @access private
     * @var null|SpeedUp_ConfigManager
     */
    private $config = null;

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
        $this->config = SpeedUp_ConfigManager::get_instance();
        
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
            
            // create the config file
            $this->config_create();
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
            
            // remove the config file
            $this->config_remove();
        }
    }
    
    /**
     * Automatically purge all cache.
     *
     * @since  1.0.0
     * @access public
     * @return boolean
     */
    public function purge_cache()
    {
        return SpeedUp_CacheUtils::purge_cache();
    }
    
    /**
     * Automatically purge all page cache on post changes.
     * 
     * @since  1.0.0
     * @access public
     * @param  int $post_id Post id.
     * @return boolean
     */
    public function on_post_change( $post_id ) 
    {
        return SpeedUp_CacheUtils::purge_cache_post($post_id);
    }
    
    /**
     * Setup cron jobs.
     *
     * @since 1.0.0
     * @access public
     */
    public function schedule_events() 
    {
        if (!wp_next_scheduled ( 'supc_purge_cache' )) {
            wp_schedule_event( time(), $this->config->get('cron_recurrence', 'daily'), 'supc_purge_cache' );
        }
    }
    
    /**
     * WordPress core changes.
     *
     * @since 1.0.0
     * @access public
     * @return boolean
     */
    public function on_change() 
    {
        return $this->purge_cache();
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
        return SpeedUp_DropinUtils::add();
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
        return SpeedUp_DropinUtils::remove();
    }
    
    
    /**
     * Add the htaccess rule.
     *
     * @since  1.0.0
     * @access private
     * @return boolean
     */
    private function htaccess_add_rules() 
    {
        return SpeedUp_HtaccessUtils::toggle_rulse_from_content(true);
    }
    
    /**
     * Remove the htaccess rule.
     *
     * @since  1.0.0
     * @access private
     * @return boolean
     */
    private function htaccess_remove_rules() 
    {
        return SpeedUp_HtaccessUtils::toggle_rulse_from_content(false);
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
        return SpeedUp_WpconfigUtils::toggle_wp_cache_from_content(false);
    }

    /**
     * Add WP_CACHE to wp-config.php
     *
     * @since 1.0.0
     * @access private
     * @return boolean
     */
    private function wp_config_add_wp_cache()
    {
        return SpeedUp_WpconfigUtils::toggle_wp_cache_from_content(true);
    }
    
    /**
     * Remove config file
     *
     * @since 1.0.5
     * @access private
     * @return boolean
     */
    private function config_remove()
    {
        return SpeedUp_ConfigManager::get_instance()->delete();
    }
    
    /**
     * Create config file
     *
     * @since 1.0.5
     * @access private
     * @return boolean
     */
    private function config_create()
    {
        return SpeedUp_ConfigManager::get_instance()->create();
    }
}

// Init
SpeedUp_PageCache::get_instance();
SpeedUp_AdminManager::get_instance();