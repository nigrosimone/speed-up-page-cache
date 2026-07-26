<?php
/**
 * Bootstrap dei test.
 *
 * Il plugin gira in gran parte prima che WordPress sia caricato, quindi le sue
 * classi di utilita' dipendono da poco: una sandbox su filesystem che fa da root
 * di WordPress e qualche stub bastano a esercitarle davvero, scrivendo file veri.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

define('SPEEDUP_TEST_ROOT', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'speed-up-page-cache-tests' . DIRECTORY_SEPARATOR);

if (!is_dir(SPEEDUP_TEST_ROOT)) {
    mkdir(SPEEDUP_TEST_ROOT, 0777, true);
}

define('ABSPATH', SPEEDUP_TEST_ROOT);
define('WP_CONTENT_DIR', SPEEDUP_TEST_ROOT . 'wp-content');
define('DAY_IN_SECONDS', 86400);

if (!is_dir(WP_CONTENT_DIR)) {
    mkdir(WP_CONTENT_DIR, 0777, true);
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        return is_dir($target) || mkdir($target, 0777, true);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/\\') . '/';
    }
}

/** Tipo restituito da get_post_type(), controllato dai test. */
$GLOBALS['speedup_post_type'] = 'post';

if (!function_exists('get_post_type')) {
    function get_post_type($post = null) {
        return $GLOBALS['speedup_post_type'];
    }
}

// Le classi di utilita' non hanno bisogno di altro: si caricano da sole.
require_once dirname(__DIR__) . '/include/cache-utils.php';
require_once dirname(__DIR__) . '/include/htaccess-utils.php';
require_once dirname(__DIR__) . '/include/wpconfig-utils.php';
