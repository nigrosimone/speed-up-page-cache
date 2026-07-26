<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SpeedUp_WpconfigUtils {

	/**
	 * Returns wp-config.php path
	 *
	 * @since 1.0.3
	 * @static
	 * @access public
	 * @return string
	 */
	public static function get_path() {
		$wp_config = 'wp-config.php';
		$search    = array(
			ABSPATH . $wp_config,
			dirname( ABSPATH ) . DIRECTORY_SEPARATOR . $wp_config,
		);
		foreach ( $search as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}
		return null;
	}

	/**
	 * Toggle WP_CACHE on or off in wp-config.php
	 *
	 * @since  1.0.3
	 * @static
	 * @param  boolean $status Status of cache.
	 * @access public
	 * @return boolean
	 */
	public static function toggle_wp_cache_from_content( $status ) {
		if ( defined( 'WP_CACHE' ) && WP_CACHE === $status ) {
			return true;
		}

		$config_path = self::get_path();

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
		array_unshift( $config_file, '<?php', 'define( "WP_CACHE", ' . $status_string . ' ); // Added by Speed Up - Page Cache' );

		if ( ! @file_put_contents( $config_path, implode( PHP_EOL, $config_file ) ) ) {
			return false;
		}

		SpeedUp_CacheUtils::opcache_invalidate( $config_path );

		return true;
	}
}
