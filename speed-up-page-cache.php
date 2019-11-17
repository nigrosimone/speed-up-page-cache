<?php
/*
 * Plugin Name: Speed Up - Page Cache
 * Plugin URI: http://wordpress.org/plugins/speed-up-page-cache/
 * Description: A simple page caching plugin.
 * Version: 1.0.10
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
        add_action( 'clean_post_cache', array($this, 'on_post_change'), 0 );
        add_action( 'wp_trash_post', array($this, 'on_post_change'), 0 );
        add_action( 'publish_post', array($this, 'on_post_change'), 0, 2 );
        add_action( 'switch_theme', array($this, 'on_change'), 0 );
        add_action( 'wp_update_nav_menu', array($this, 'on_change'), 0 );
        add_action( 'edit_user_profile_update', array($this, 'on_change'), 0 );
        add_action( 'edited_term', array($this, 'on_change'), 0 );
        
        // cron job
        add_action( 'supc_purge_cache', array( $this, 'purge_cache' ) );
        add_action( 'init', array( $this, 'schedule_events' ) );
        add_filter( 'cron_schedules', array($this, 'cron_schedules') );
  
        // others action
        add_action( 'supc_purge_cache_post', array( $this, 'on_post_change' ) );
        add_action( 'supc_save_config', array( $this, 'supc_save_config' ) );
        
        // filter
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
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
        static $_CACHE_ON_POST_CHANGE = array();
        
        if( isset($_CACHE_ON_POST_CHANGE[$post_id]) ){
            return $_CACHE_ON_POST_CHANGE[$post_id];
        }
        
        $_CACHE_ON_POST_CHANGE[$post_id] = SpeedUp_CacheUtils::purge_cache_post($post_id);
        
        return $_CACHE_ON_POST_CHANGE[$post_id];
    }
    
    /**
     * supc_save_config hook.
     *
     * @since  1.0.6
     * @access public
     */
    public function supc_save_config()
    {
        $this->unschedule_events();
        $this->schedule_events();
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
     * Unschedule events
     *
     * @since 1.0.6
     * @access public
     */
    public function unschedule_events() 
    {
        $timestamp = wp_next_scheduled( 'supc_purge_cache' );
        wp_unschedule_event( $timestamp, 'supc_purge_cache' );
    }
    
    /**
     * cron_schedules filter.
     *
     * @since 1.0.6
     * @access public
     */
    public function cron_schedules($schedules)
    {
        if( !isset($schedules['weekly']) ){
            $schedules['weekly'] = array(
                'interval' => DAY_IN_SECONDS * 7,
                'display' => __('Once week')
            );
        }
        if( !isset($schedules['montly']) ){
            $schedules['montly'] = array(
                'interval' => DAY_IN_SECONDS * 30,
                'display' => __('Once month')
            );
        }
        return $schedules;
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
     * plugin_row_meta filter.
     *
     * @since 1.0.7
     * @access public
     * @param  array  $plugin_meta All met data to a plugin.
	 * @param  string $plugin_file The main file of the plugin with the meta data.
     * @return boolean
     */
    public function plugin_row_meta($plugin_meta, $plugin_file)
    {
        if ( plugin_basename( __FILE__ ) === $plugin_file ) {
            $plugin_meta[] = '<a href="'. network_admin_url('options-general.php?page=speed-up-page-cache' ) . '">Settings</a>';
            $plugin_meta[] = '&hearts; <a href="http://paypal.me/snwp" target="_blank">Donate</a>';
        }
        return $plugin_meta;
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
        return $this->config->delete();
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
        return $this->config->create();
    }
}

// Init
SpeedUp_PageCache::get_instance();
SpeedUp_AdminManager::get_instance();