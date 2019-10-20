<?php

if ( !defined('ABSPATH') ) exit;

class SpeedUp_DropinUtils { 
    
    /**
     * Add advanced-cache.php dropin.
     *
     * @since 1.0.3
     * @static
     * @access private
     * @return boolean
     */
    public static function add()
    {
        if( self::remove() ){
            $source = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dropin' . DIRECTORY_SEPARATOR . 'advanced-cache.php';
            $dest = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php';
            return copy($source, $dest);
        }
        return false;
    }
    
    /**
     * Add advanced-cache.php dropin.
     *
     * @since 1.0.3
     * @static
     * @access private
     * @return boolean
     */
    public static function remove()
    {
        $filename = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php';
        if( file_exists($filename) ){
            return @unlink(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php');
        }
        return true;
    }
}