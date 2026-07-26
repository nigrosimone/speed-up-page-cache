<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SpeedUp_ConfigManager {

	/**
	 * Instance of the object.
	 *
	 * @since  1.0.5
	 * @static
	 * @access public
	 * @var null|SpeedUp_ConfigManager
	 */
	public static $instance = null;

	/**
	 * Default config
	 *
	 * @since 1.0.5
	 * @access private
	 * @var array
	 */
	private $defaults = array(
		'cron_recurrence'      => 'daily',
		'cache_exception_urls' => array(),
	);

	/**
	 * Current config
	 *
	 * @since 1.0.5
	 * @access private
	 * @var array
	 */
	private $config = array();

	/**
	 * Current config
	 *
	 * @since 1.0.5
	 * @access private
	 * @var boolean
	 */
	private $loaded = false;


	/**
	 * Access the single instance of this class.
	 *
	 * @since  1.0.5
	 * @access public
	 * @return SpeedUp_ConfigManager
	 */
	public static function get_instance() {
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
	 * @return SpeedUp_ConfigManager
	 */
	private function __construct() {
		$filename = $this->get_config_path();

		if ( file_exists( $filename ) ) {
			$config       = require_once $filename;
			$this->config = array_merge( $this->defaults, $config );
			$this->loaded = true;
		}
	}

	/**
	 * Return true if config are loaded.
	 *
	 * @since 1.0.5
	 * @access public
	 * @return boolean
	 */
	public function loaded() {
		return $this->loaded;
	}

	/**
	 * Save config.
	 *
	 * @since 1.0.5
	 * @access public
	 * @param  array $config
	 * @return boolean
	 */
	public function save( $config ) {
		$filename = $this->get_config_path();

		$config = array_merge( $this->config, $config );

		if ( is_array( $config ) ) {
			// var_export genera qui il contenuto di un file PHP di configurazione:
			// non e' una traccia di debug lasciata per sbaglio.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			$config_file_string = "<?php\n\rif ( !defined('ABSPATH') ) exit;\n\rreturn " . var_export( $config, true ) . "; \n\r";

			if ( ! @file_put_contents( $filename, $config_file_string ) ) {
				return false;
			}

			SpeedUp_CacheUtils::opcache_invalidate( $filename );

			$this->config = $config;

			do_action( 'supc_save_config', $config );

			return true;
		}

		return false;
	}

	/**
	 * Return config value.
	 *
	 * @since 1.0.5
	 * @access public
	 * @param string $key
	 * @param mixed $fallback
	 * @return mixed
	 */
	public function get( $key, $fallback = null ) {
		return isset( $this->config[ $key ] ) ? $this->config[ $key ] : $fallback;
	}

	/**
	 * Return the config file path.
	 *
	 * @since 1.0.5
	 * @access public
	 * @return string
	 */
	public function get_config_path() {
		$config_dir = SpeedUp_CacheUtils::get_cache_dir();
		return $config_dir . 'config.php';
	}

	/**
	 * Create the config file.
	 *
	 * @since 1.0.5
	 * @return boolean
	 */
	public function create() {
		return $this->save( $this->defaults );
	}

	/**
	 * Create the config file.
	 *
	 * @since 1.0.5
	 * @return boolean
	 */
	public function delete() {
		$filename = self::get_config_path();

		if ( file_exists( $filename ) ) {
			return @unlink( $filename );
		}

		return true;
	}
}
