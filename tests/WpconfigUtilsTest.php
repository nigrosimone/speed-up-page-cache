<?php

use PHPUnit\Framework\TestCase;

/**
 * Test della modifica di wp-config.php.
 *
 * E' la cosa piu' rischiosa che il plugin fa: se sbaglia, il sito non si apre
 * piu'. Ogni test scrive un wp-config.php vero nella sandbox e verifica cosa ne
 * resta.
 */
class WpconfigUtilsTest extends TestCase {

    /** @var string */
    private $config_path;

    protected function setUp(): void {
        parent::setUp();
        $this->config_path = SPEEDUP_TEST_ROOT . 'wp-config.php';
        $this->cleanUp();
    }

    protected function tearDown(): void {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp() {
        if (file_exists($this->config_path)) {
            unlink($this->config_path);
        }
    }

    /** Scrive un wp-config.php nella sandbox. */
    private function given(array $lines) {
        file_put_contents($this->config_path, implode(PHP_EOL, $lines));
    }

    private function lines() {
        return explode(PHP_EOL, (string) file_get_contents($this->config_path));
    }

    private function contents() {
        return (string) file_get_contents($this->config_path);
    }

    // -----------------------------------------------------------------------

    public function test_trova_wp_config_nella_root_di_wordpress() {
        $this->given(array('<?php'));

        $this->assertSame($this->config_path, SpeedUp_WpconfigUtils::get_path());
    }

    public function test_non_trova_nulla_se_wp_config_non_esiste() {
        $this->assertNull(SpeedUp_WpconfigUtils::get_path());
    }

    public function test_aggiunge_la_costante_wp_cache() {
        $this->given(array('<?php', "define( 'DB_NAME', 'wordpress' );"));

        $this->assertTrue(SpeedUp_WpconfigUtils::toggle_wp_cache_from_content(true));

        $contents = $this->contents();
        $this->assertStringContainsString('WP_CACHE', $contents);
        $this->assertStringContainsString('true', $contents);
    }

    public function test_la_costante_arriva_subito_dopo_l_apertura_di_php() {
        $this->given(array('<?php', "define( 'DB_NAME', 'wordpress' );"));

        SpeedUp_WpconfigUtils::toggle_wp_cache_from_content(true);

        $lines = $this->lines();
        $this->assertSame('<?php', trim($lines[0]));
        $this->assertStringContainsString('WP_CACHE', $lines[1]);
    }

    public function test_conserva_le_altre_costanti() {
        $this->given(array(
            '<?php',
            "define( 'DB_NAME', 'wordpress' );",
            "define( 'DB_USER', 'utente' );",
            "\$table_prefix = 'wp_';",
            "require_once ABSPATH . 'wp-settings.php';",
        ));

        SpeedUp_WpconfigUtils::toggle_wp_cache_from_content(true);

        $contents = $this->contents();
        foreach (array('DB_NAME', 'DB_USER', 'table_prefix', 'wp-settings.php') as $needle) {
            $this->assertStringContainsString($needle, $contents, "Perso dal wp-config: $needle");
        }
    }

    public function test_non_duplica_la_costante_se_c_e_gia() {
        $this->given(array('<?php', "define( 'WP_CACHE', false );", "define( 'DB_NAME', 'wordpress' );"));

        SpeedUp_WpconfigUtils::toggle_wp_cache_from_content(true);

        $this->assertSame(
            1,
            substr_count($this->contents(), 'WP_CACHE'),
            'Una sola definizione di WP_CACHE, altrimenti PHP emette un avviso di ridefinizione.'
        );
    }

    public function test_puo_disattivare_la_cache() {
        $this->given(array('<?php', "define( 'WP_CACHE', true );"));

        SpeedUp_WpconfigUtils::toggle_wp_cache_from_content(false);

        $this->assertStringContainsString('false', $this->contents());
        $this->assertStringNotContainsString('WP_CACHE", true', $this->contents());
    }

    /**
     * Prima questa riga veniva scartata sempre, presumendo che fosse "<?php".
     * Chi aveva un commento o una riga vuota in cima al proprio wp-config.php se
     * lo vedeva sparire.
     */
    public function test_non_distrugge_la_prima_riga_se_non_e_l_apertura_di_php() {
        $this->given(array(
            '<?php /* Configurazione di un mio hosting - NON RIMUOVERE */',
            "define( 'DB_NAME', 'wordpress' );",
        ));

        SpeedUp_WpconfigUtils::toggle_wp_cache_from_content(true);

        $this->assertStringContainsString(
            'NON RIMUOVERE',
            $this->contents(),
            'Il contenuto della prima riga non deve andare perso.'
        );
    }

    public function test_il_file_resta_php_valido() {
        $this->given(array(
            '<?php',
            "define( 'DB_NAME', 'wordpress' );",
            "\$table_prefix = 'wp_';",
        ));

        SpeedUp_WpconfigUtils::toggle_wp_cache_from_content(true);

        $output = array();
        $code   = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($this->config_path) . ' 2>&1', $output, $code);

        $this->assertSame(0, $code, 'wp-config.php deve restare sintatticamente valido: ' . implode("\n", $output));
    }

    public function test_non_fa_danni_se_wp_config_non_esiste() {
        $this->assertFalse(SpeedUp_WpconfigUtils::toggle_wp_cache_from_content(true));
    }
}
