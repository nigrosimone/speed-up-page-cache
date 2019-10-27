<?php

if ( !defined('ABSPATH') ) exit;

class SpeedUp_AdminManager {
    
    /**
     * Instance of the object.
     *
     * @since  1.0.3
     * @static
     * @access public
     * @var null|SpeedUp_AdminManager
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
     * @since  1.0.3
     * @access public
     * @return SpeedUp_AdminManager
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
     * @since  1.0.3
     * @access private
     * @return SpeedUp_AdminManager
     */
    private function __construct()
    {
        $this->config = SpeedUp_ConfigManager::get_instance();
        
        // admin menu
        add_action( 'wp_before_admin_bar_render', array($this, 'wp_before_admin_bar_render') );
        add_action( 'admin_bar_menu', array($this, 'admin_bar_menu'), 150 );
        add_action( 'admin_menu', array($this, 'admin_menu') );
        add_action( 'wp_update_nav_menu', array($this, 'render_admin_purge_page') );
        add_action( 'init', array($this, 'init') );
    }
    
    /**
     * init hook.
     *
     * @since 1.0.3
     * @access public
     */
    public function init()
    {
        if( is_admin() ){
            $this->process_action();
            $this->process_notice();
            $this->process_config();
        }
    }
    
    /**
     * wp_before_admin_bar_render hook.
     *
     * @since 1.0.3
     * @access public
     */
    public function wp_before_admin_bar_render()
    {
        global $wp_admin_bar;
        
        $wp_admin_bar->add_menu( array(
            'id' => 'supc_admin_page_menu',
            'parent' => false,
            'title' => 'Page Cache',
            'href' => admin_url('admin.php?page=speed-up-page-cache'),
        ) );
    }
    
    /**
     * admin_bar_menu hook.
     *
     * @since 1.0.3
     * @access public
     */
    public function admin_bar_menu()
    {
        global $wp_admin_bar;
        
        if ( current_user_can( 'manage_options' ) ) {
            
            $wp_admin_bar->add_menu( array(
                'id' => 'supc_flush_all',
                'parent' => 'supc_admin_page_menu',
                'title' => 'Purge All Caches',
                'href' => wp_nonce_url( network_admin_url(
                    'admin.php?page=speed-up-page-cache&amp;supc_action=supc_flush_all' ), 
                    'supc' )
            ));
            
            $post_id = SpeedUp_CacheUtils::detect_post_id();
            if( $post_id > 0 ){
                $wp_admin_bar->add_menu( array(
                    'id' => 'supc_flush_post',
                    'parent' => 'supc_admin_page_menu',
                    'title' => 'Purge Current Page',
                    'href' => wp_nonce_url( network_admin_url(
                        'admin.php?page=speed-up-page-cache&amp;supc_action=supc_flush_post&amp;post_id=' . $post_id ), 
                        'supc' )
                ));
            } else {
                if( !is_admin() ){
                    $url = SpeedUp_CacheUtils::get_url();
                    if( !empty($url) ){
                        $wp_admin_bar->add_menu( array(
                            'id' => 'supc_flush_post',
                            'parent' => 'supc_admin_page_menu',
                            'title' => 'Purge Current Page',
                            'href' => wp_nonce_url( network_admin_url(
                                'admin.php?page=speed-up-page-cache&amp;supc_action=supc_flush_url&amp;url=' . urlencode($url) ), 
                                'supc' )
                        ));
                    }
                }
            }
            
            $wp_admin_bar->add_menu( array(
                'id' => 'supc_admin',
                'parent' => 'supc_admin_page_menu',
                'title' => 'Option',
                'href' => network_admin_url('options-general.php?page=speed-up-page-cache' )
            ));
        }
    }
    
    /**
     * admin_menu hook.
     *
     * @since 1.0.3
     * @access public
     */
    public function admin_menu()
    {
        add_submenu_page( 'options-general.php', 'Page Cache', 'Page Cache', 'manage_options', 'speed-up-page-cache', array( $this, 'render_admin_purge_page' ));
    }
    
    /**
     * Process action.
     *
     * @since 1.0.3
     * @access private
     */
    private function process_action()
    {
        $action = SpeedUp_CacheUtils::get_request('supc_action');
        
        if( $action ){
            if ( current_user_can( 'manage_options' ) ) {

                $nonce = SpeedUp_CacheUtils::get_request('_wpnonce');
                
                if ( !wp_verify_nonce($nonce, 'supc' ) ){
                    wp_nonce_ays( 'supc' );
                }
                
                $result = null;
                
                switch ($action) {
                    case 'supc_flush_all':
                        $result = SpeedUp_CacheUtils::purge_cache();
                        break;
                    case 'supc_flush_post':
                        $post_id = SpeedUp_CacheUtils::get_request('post_id');
                        $result = SpeedUp_CacheUtils::purge_cache_post($post_id);
                        break;
                    case 'supc_flush_url':
                        $url = SpeedUp_CacheUtils::get_request('url');
                        $url = urldecode($url);
                        $result = SpeedUp_CacheUtils::purge_cache_url($url);
                        break;
                }
                
                if( true === $result ){
                    $this->redirect_admin(array('supc_notice' => 'notice_flush_success'));
                } else if ( false === $result ) {
                    $this->redirect_admin(array('supc_notice' => 'notice_flush_failed'));
                }
            }
        }
    }
    
    /**
     * Process notice.
     *
     * @since 1.0.3
     * @access private
     */
    private function process_notice()
    {
        $notice = SpeedUp_CacheUtils::get_request('supc_notice');
        
        if( $notice ){
            if ( current_user_can( 'manage_options' ) ) {
                switch ($notice) {
                   case 'notice_flush_success':
                       SpeedUp_AdminNotice::success_notice(SpeedUp_AdminNotice::MESSAGE_SUCCESS_FLUSHED, true)->add_action();
                   break;
                   case 'notice_flush_failed':
                       SpeedUp_AdminNotice::error_notice(SpeedUp_AdminNotice::MESSAGE_ERROR_FLUSHED, true)->add_action();
                   break;
                   case 'notice_config_success':
                       SpeedUp_AdminNotice::success_notice(SpeedUp_AdminNotice::MESSAGE_SUCCESS_CONFIGSAVE, true)->add_action();
                   break;
                   case 'notice_config_failed':
                       SpeedUp_AdminNotice::error_notice(SpeedUp_AdminNotice::MESSAGE_ERROR_CONFIGSAVE, true)->add_action();
                   break;
                }
            }
        }
    }
    
    /**
     * Process config.
     *
     * @since 1.0.5
     * @access private
     */
    private function process_config()
    {
        $config = SpeedUp_CacheUtils::get_request('supc_config');
        
        if( $config ){
            if ( current_user_can( 'manage_options' ) ) {
                
                $nonce = SpeedUp_CacheUtils::get_request('_wpnonce');
                
                if ( !wp_verify_nonce($nonce, 'supc' ) ){
                    wp_nonce_ays( 'supc' );
                }
                
                $cron_recurrence = SpeedUp_CacheUtils::get_request('cron_recurrence');
                $cache_exception_urls = SpeedUp_CacheUtils::get_request('cache_exception_urls');
                
                if( empty($cache_exception_urls) ){
                    $cache_exception_urls = array();
                } else {
                    $cache_exception_urls = explode("\r\n", $cache_exception_urls);
                }
                
                $new_config = array(
                    'cron_recurrence' => $cron_recurrence,
                    'cache_exception_urls' => $cache_exception_urls
                );
                
                if( $this->config->save($new_config) ){
                    $this->redirect_admin(array('supc_notice' => 'notice_config_success'));
                } else {
                    $this->redirect_admin(array('supc_notice' => 'notice_config_failed'));
                }
            }
        }
    }
    
    /**
     * Render admin page.
     *
     * @since 1.0.3
     * @access public
     */
    public function render_admin_purge_page()
    {
        $cached_urls = SpeedUp_CacheUtils::cached_urls();
        
        $html = '';
        
        $html .= '<div class="wrap">';
        $html .= '<h1>Speed Up - Page Cache</h1>';
         
        if( !defined('SUPC_DROPIN') || !SUPC_DROPIN ){
            $html .= SpeedUp_AdminNotice::error_notice(SpeedUp_AdminNotice::MESSAGE_ERROR_DROPIN, false)->render();
        }
        if( !defined('WP_CACHE') || !WP_CACHE ){
            $html .= SpeedUp_AdminNotice::error_notice(SpeedUp_AdminNotice::MESSAGE_ERROR_WPCACHE, false)->render();
        }
        if( !$this->config->loaded() ){
            $html .= SpeedUp_AdminNotice::error_notice(SpeedUp_AdminNotice::MESSAGE_ERROR_CONFIG, false)->render();
        }
        
        if ( current_user_can( 'manage_options' ) ) {
            $html .= '<section class="pattern" id="formelementsinput">';
            $html .= '<h2>Purge method</h2>';
            $html .= '<form method="post" action="">';
            $html .= wp_nonce_field( 'supc' );
            $html .= '<input type="hidden" name="supc_action" value="supc_flush_all" />';
            $html .= '<input type="hidden" name="supc_redirect" value="'. esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ) .'" />';
            $html .= '<input style="border: none; box-shadow: none" type="text" name="" value="PURGE ALL THE CACHES" class="regular-text" readonly="readonly">';
            $html .= '<input cass="button-secondary" type="submit" value="Purge All Caches"><br />';
            $html .= '<span class="description">Purge all the cached pages.</span>';
            $html .= '</form>';
            $html .= '<br />';
            $html .= '<form method="post" action="">';
            $html .= wp_nonce_field( 'supc' );
            $html .= '<input type="hidden" name="supc_action" value="supc_flush_post" />';
            $html .= '<input type="hidden" name="supc_redirect" value="'. esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ) .'" />';
            $html .= '<input type="text" name="post_id" value="" placeholder="POST ID" class="regular-text">';
            $html .= '<input cass="button-secondary" type="submit" value="Purge Post Cache"><br />';
            $html .= '<span class="description">Purge the post\'s page cache and all the taxonomy pages correlated to the the post (authors pages, tags pages, categories pages).</span>';
            $html .= '</form>';
            $html .= '<br />';
            $html .= '<form method="post" action="">';
            $html .= wp_nonce_field( 'supc' );
            $html .= '<input type="hidden" name="supc_action" value="supc_flush_url" />';
            $html .= '<input type="hidden" name="supc_redirect" value="'. esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ) .'" />';
            $html .= '<input type="text" name="url" value="" placeholder="PAGE URL" class="regular-text">';
            $html .= '<input cass="button-secondary" type="submit" value="Purge URL Cache"><br />';
            $html .= '<span class="description">Purge only the inserted URL.</span>';
            $html .= '</form>';
            
            if( !empty($cached_urls) ){
                $html .= '<br />';
                $html .= '<form method="post" action="">';
                $html .= wp_nonce_field( 'supc' );
                $html .= '<input type="hidden" name="supc_action" value="supc_flush_url" />';
                $html .= '<input type="hidden" name="supc_redirect" value="'. esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ) .'" />';
                $html .= '<select name="url"  class="regular-text">';
                foreach ($cached_urls as $cached_url){
                    $html .= '<option>'. $cached_url . '</option>';
                }
                $html .= '</select>';
                $html .= '<input cass="button-secondary" type="submit" value="Purge URL Cache"><br />';
                $html .= '<span class="description">Purge only the selected URL (only cached URL are selectable).</span>';
                $html .= '</form>';
            }
            
            $html .= '</section><br />';
       
            $html .= '<section class="pattern" id="formelementsinput">';
            $html .= '<h2>Config</h2>';
            $html .= '<form method="post" action="">';
            $html .= wp_nonce_field( 'supc' );
            $html .= '<input type="hidden" name="supc_config" value="1" />';
            $html .= '<input type="hidden" name="supc_redirect" value="'. esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ) .'" />';
            
            $cron_recurrence = $this->config->get('cron_recurrence');
            $html .= '<label for="cron_recurrence">Recurrence of cache purge: </label>';
            $html .= '<select name="cron_recurrence">';
            $html .= '<option disabled>-- SELECT --</option>';
            $html .= '<option '. ($cron_recurrence === 'hourly' ? 'selected' : '') .'>hourly</option>';
            $html .= '<option '. ($cron_recurrence === 'daily' ? 'selected' : '') .'>daily</option>';
            $html .= '<option '. ($cron_recurrence === 'twicedaily' ? 'selected' : '') .'>twicedaily</option>';
            $html .= '</select><br />';
            $html .= '<span class="description">Choose the schedule recurrence for automatic chache purge.</span>';
            $html .= '<br /><br />';
            
            $cache_exception_urls = $this->config->get('cache_exception_urls');
            $html .= '<label for="textarea">Cache exception urls: </label><br />';
            $html .= '<textarea name="cache_exception_urls" rows="10" cols="80">';
            if( is_array($cache_exception_urls) ){
                $html .= implode("\r\n", $cache_exception_urls);
            }
            $html .= '</textarea><br />';
            $html .= '<span class="description">Choose url do not cache (one for line).</span>';
            $html .= '<br /><br />';
            
            $html .= '<br /><input cass="button-primary" type="submit" value="Save"><br />';
            $html .= '</form>';
            $html .= '</section><br />';
        }
        
        $html .= '</div>'; // end wrap
        
        echo $html;
    }
    
    /**
     * Redirects when in WP Admin
     * 
     * @since 1.0.3
     * @access public
     * @param $params array of query parameters
     */
     public function redirect_admin($params = array()) 
     {
        $url = SpeedUp_CacheUtils::get_request( 'supc_redirect' );
 
        if ( $url == '' ) {
            if ( !empty( $_SERVER['HTTP_REFERER'] ) ) {
                $url = $_SERVER['HTTP_REFERER'];
            } else {
                $url = 'admin.php';
            }
        }
        
        if( false === strpos($url, '?') ){
            $url .= '?';
        } else {
            $url .= '&';
        }
        
        $url .= http_build_query($params);
        
        @header( 'Location: ' . $url );
        exit();
    }
}