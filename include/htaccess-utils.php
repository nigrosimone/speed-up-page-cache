<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SpeedUp_HtaccessUtils {

	const HTACCESS_SECTION_START = '# BEGIN Speed Up - Page Cache';
	const HTACCESS_SECTION_END   = '# END Speed Up - Page Cache';

	/**
	 * Returns .htaccess path
	 *
	 * @since 1.0.3
	 * @static
	 * @access public
	 * @return string
	 */
	public static function get_path() {
		$search = array(
			ABSPATH . '.htaccess',
			dirname( ABSPATH ) . DIRECTORY_SEPARATOR . '.htaccess',
		);
		foreach ( $search as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}
		return null;
	}

	/**
	 * Remove the htaccess rule and add if $add.
	 *
	 * @since  1.0.3
	 * @static
	 * @access public
	 * @param  boolean $add Add the ruel.
	 * @return boolean
	 */
	public static function toggle_rulse_from_content( $add ) {
		$htaccess_path = self::get_path();

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
		if ( ! empty( $old_lines ) && is_array( $old_lines ) ) {

			$speed_up_directives = null;

			// loop over the htaccess lines
			for ( $i = 0, $e = count( $old_lines ); $i < $e; $i++ ) {

				$line = $old_lines[ $i ];

				// when we find the first line of Speed Up directives
				if ( strpos( $line, self::HTACCESS_SECTION_START ) === 0 ) {
					$speed_up_directives = true;
				}

				// remove the line if is in a Speed Up section
				if ( true === $speed_up_directives ) {
					unset( $old_lines[ $i ] );
				}

				// when we find the last line of Speed Up directives
				if ( strpos( $line, self::HTACCESS_SECTION_END ) === 0 ) {
					$speed_up_directives = false;
					break; // end of operation, exit for
				}
			}

			if ( ! is_null( $speed_up_directives ) ) {
				// broken htaccess!
				if ( true === $speed_up_directives ) {
					return false;
				}
			}

			// reindex
			$new_lines = array_values( $old_lines );

			// add the new line at the beginning
			if ( $add ) {
				if ( ! isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
					return false;
				}

				$root_dir  = str_replace( '\\', '/', $_SERVER['DOCUMENT_ROOT'] );
				$cache_dir = str_replace( '\\', '/', SpeedUp_CacheUtils::get_cache_dir() );

				if ( empty( $root_dir ) || empty( $cache_dir ) ) {
					return false;
				}

				$cache_path = str_replace( $root_dir, '', $cache_dir );

				$my_lines   = array();
				$my_lines[] = self::HTACCESS_SECTION_START;
				$my_lines[] = '<IfModule mod_rewrite.c>';
				$my_lines[] = 'RewriteEngine On';
				$my_lines[] = 'RewriteBase /';
				$my_lines[] = 'RewriteCond %{REQUEST_METHOD} =GET';
				$my_lines[] = 'RewriteCond %{QUERY_STRING} =""';
				$my_lines[] = 'RewriteCond %{HTTP_COOKIE} !(comment_author|wp\-postpass|wordpress_logged_in|wptouch_switch_toggle) [NC]';
				$my_lines[] = 'RewriteCond %{REQUEST_URI} \/$';
				$my_lines[] = 'RewriteCond %{HTTP_HOST} ([^:]+)';
				$my_lines[] = 'RewriteCond %{DOCUMENT_ROOT}' . $cache_path . '%1/%{REQUEST_URI}/_index.html -f';
				$my_lines[] = 'RewriteRule ^(.*) "' . $cache_path . '%1/%{REQUEST_URI}/_index.html" [L]';
				$my_lines[] = '</IfModule>';
				$my_lines[] = self::HTACCESS_SECTION_END;
				$new_lines  = array_merge( $my_lines, $old_lines );
			}

			return @file_put_contents( $htaccess_path, implode( PHP_EOL, $new_lines ) );
		}

		return false;
	}
}
