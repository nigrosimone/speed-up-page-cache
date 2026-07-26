<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$speedup_page_cache_manager_file = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'speed-up-page-cache' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'cache-manager.php';

if ( file_exists( $speedup_page_cache_manager_file ) ) {
	require_once $speedup_page_cache_manager_file;

	define( 'SUPC_DROPIN', true );

	$speedup_page_cache_manager = SpeedUp_CacheManager::get_instance();

	$speedup_page_cache_manager->serve_file_cache();
	ob_start( array( $speedup_page_cache_manager, 'ob_start' ) );
}
