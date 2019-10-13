<?php 
if ( !defined('ABSPATH') ) exit;

$speedUpcacheManagerFile = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'speed-up-page-cache' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'cache-manager.php';

if( file_exists( $speedUpcacheManagerFile ) ){
    require_once $speedUpcacheManagerFile;
    
    $speedUpcacheManagerObject = SpeedUp_CacheManager::get_instance();
    
    $speedUpcacheManagerObject->serve_file_cache();
    ob_start( array($speedUpcacheManagerObject, 'ob_start') );
}
