<?php
require_once 'cache-utils.php';

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
    public function init(){
        if( is_admin() ){
            $this->process_action();
            $this->process_notice();
        }
    }
    
    /**
     * wp_before_admin_bar_render hook.
     *
     * @since 1.0.3
     * @access public
     */
    public function wp_before_admin_bar_render(){
        global $wp_admin_bar;
        
        $wp_admin_bar->add_menu( array(
            'id' => 'supc_admin_page_menu',
            'parent' => false,
            'title' => 'Cache',
            'href' => admin_url('admin.php?page=speed-up-page-cache'),
        ) );
    }
    
    /**
     * admin_bar_menu hook.
     *
     * @since 1.0.3
     * @access public
     */
    public function admin_bar_menu(){
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
        }
    }
    
    /**
     * admin_menu hook.
     *
     * @since 1.0.3
     * @access public
     */
    public function admin_menu(){
        add_submenu_page( 'options-general.php', 'Speed Up - Page Cache', 'Speed Up - Page Cache', 'manage_options', 'speed-up-page-cache', array( $this, 'render_admin_purge_page' ));
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
                       add_action( 'admin_notices', array($this, 'admin_notices_flush_success'));
                       add_action( 'network_admin_notices', array($this, 'admin_notices_flush_success'));
                   break;
                   case 'notice_flush_failed':
                       add_action( 'admin_notices', array($this, 'admin_notices_flush_failed'));
                       add_action( 'network_admin_notices', array($this, 'admin_notices_flush_failed'));
                   break;
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
        $html = '';
            
        $html .= '<ul>';
        if( defined('SUPC_DROPIN') && SUPC_DROPIN ){
            $html .= '<li>DROPIN: OK</li>';
        } else {
            $html .= '<li>DROPIN: not installed</li>';
        }
            
        if( defined('WP_CACHE') && WP_CACHE ){
            $html .= 'WP_CACHE: OK';
        } else {
            $html .= 'WP_CACHE: not setted to true';
        }
        $html .= '</ul>';
            
            
        echo $html;
    }
    
    /**
     * admin_notices hook for success flush.
     *
     * @since 1.0.3
     * @access public
     */
    public function admin_notices_flush_success(){
        $html = '';
        $html .= '<div class="notice notice-success is-dismissible">';
        $html .= '<p>'. __( 'Cache flushed!', 'speed-up-page-cache' ) .'</p>';
        $html .= '</div>';
        echo $html;
    }
    
    /**
     * admin_notices hook for failed flush.
     *
     * @since 1.0.3
     * @access public
     */
    public function admin_notices_flush_failed(){
        $html = '';
        $html .= '<div class="notice notice-error is-dismissible">';
        $html .= '<p>'. __( 'Cache flush failed!', 'speed-up-page-cache' ) .'</p>';
        $html .= '</div>';
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