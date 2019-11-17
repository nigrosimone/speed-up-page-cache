<?php

if ( !defined('ABSPATH') ) exit;

class SpeedUp_AdminNotice { 
    
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR = 'error';
    
    const MESSAGE_SUCCESS_FLUSHED = 'Cache flushed!';
    const MESSAGE_ERROR_FLUSHED = 'Cache flush failsed!';
    const MESSAGE_ERROR_DROPIN = 'The dropin is not installed. Please manually copy the <code>/wp-content/plugins/speed-up-page-cache/dropin/advanced-cache.php</code> file into the <code>/wp-content/</code> directory';
    const MESSAGE_ERROR_WPCACHE = '<code>WP_CACHE</code> is not enabled. Please manually set the <code>define(\'WP_CACHE\', true);</code> costant into the <code>wp-config.php</code>';
    const MESSAGE_ERROR_CONFIG = 'Config are not loaded';
    const MESSAGE_SUCCESS_CONFIGSAVE = 'Config updated!';
    const MESSAGE_ERROR_CONFIGSAVE = 'Config save failsed!';
    
    /**
     * Notice message
     * 
     * @since  1.0.5
     * @access private
     * @var string
     */
    private $message = '';
    
    /**
     * Notice type
     * 
     * @since  1.0.5
     * @access private
     * @var string
     */
    private $type = 'success';
    
    /**
     * Notice dismissible
     *
     * @since  1.0.5
     * @access private
     * @var boolean
     */
    private $dismissible = true;
    
    /**
     * Constructor
     *
     * @since  1.0.5
     * @access public
     * @param  string  $message
     * @param  string  $type
     * @param  boolean $dismissible
     * @return SpeedUp_AdminNotice
     */
    public function __construct($message, $type = 'success', $dismissible = true)
    {
        $this->message = $message;
        $this->type = $type;
        $this->dismissible = $dismissible;
    }
    
    /**
     * Add action.
     *
     * @since 1.0.5
     * @access public
     */
    public function add_action()
    {
        add_action( 'admin_notices', array($this, 'print'));
        add_action( 'network_admin_notices', array($this, 'print')); 
    }
    
    /**
     * Render notice.
     *
     * @since 1.0.5
     * @access public
     * @return string
     */
    public function render()
    {
        $html = '';
        $html .= '<div class="notice notice-'. $this->type .' '. ($this->dismissible ? 'is-dismissible' : 'inline'). '">';
        $html .= '<p>'. $this->message .'</p>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Print notice.
     *
     * @since 1.0.5
     * @access public
     */
    public function print()
    {
        echo $this->render();
    }
    
    /**
     * Render success notice.
     *
     * @since 1.0.5
     * @static
     * @access public
     * @param  string  $message
     * @param  boolean $dismissible
     * @return SpeedUp_AdminNotice
     */
    public static function success_notice($message, $dismissible = true)
    {
        return new self($message, self::TYPE_SUCCESS, $dismissible);
    }
    
    /**
     * Render error notice.
     *
     * @since 1.0.5
     * @static
     * @access public
     * @param  string  $message
     * @param  boolean $dismissible
     * @return SpeedUp_AdminNotice
     */
    public static function error_notice($message, $dismissible = true)
    {
        return new self($message, self::TYPE_ERROR, $dismissible);
    }
}