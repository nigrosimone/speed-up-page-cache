<?php

use PHPUnit\Framework\TestCase;

/**
 * Test delle correzioni di robustezza.
 *
 * Riguardano cose che non si notano guardando il sito: una pagina troncata
 * risponde 200 e nei log sembra tutto a posto.
 */
class HardeningTest extends TestCase {

    /** @var string */
    private $dir;

    protected function setUp(): void {
        parent::setUp();
        $this->dir = SPEEDUP_TEST_ROOT . 'hardening' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
        $this->cleanUp();
    }

    protected function tearDown(): void {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp() {
        foreach (glob($this->dir . '*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach (glob($this->dir . '.*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Scrittura atomica
    // -----------------------------------------------------------------------

    public function test_scrive_il_contenuto() {
        $path = $this->dir . '_index.html';

        $this->assertTrue(SpeedUp_CacheUtils::write_atomic($path, '<html>ciao</html>'));
        $this->assertSame('<html>ciao</html>', file_get_contents($path));
    }

    public function test_sostituisce_un_file_esistente() {
        $path = $this->dir . '_index.html';
        file_put_contents($path, 'vecchio');

        SpeedUp_CacheUtils::write_atomic($path, 'nuovo');

        $this->assertSame('nuovo', file_get_contents($path));
    }

    /**
     * Il senso della scrittura atomica: durante l'operazione chi legge deve
     * vedere il file vecchio per intero, non quello nuovo a metà.
     */
    public function test_non_lascia_file_temporanei_in_giro() {
        $path = $this->dir . '_index.html';

        SpeedUp_CacheUtils::write_atomic($path, str_repeat('x', 100000));

        $residui = array_merge(glob($this->dir . '*.tmp'), glob($this->dir . '.*.tmp'));
        $this->assertSame(array(), $residui, 'Il file temporaneo deve essere stato rinominato, non abbandonato.');
    }

    public function test_il_file_temporaneo_non_finisce_nell_elenco_della_cache() {
        // L'elenco cerca "*/_index.html": un file temporaneo non deve
        // comparire come se fosse una pagina in cache.
        $path = $this->dir . '_index.html';
        SpeedUp_CacheUtils::write_atomic($path, '<html></html>');

        $trovati = glob($this->dir . '_index.html');

        $this->assertCount(1, $trovati);
    }

    public function test_fallisce_senza_esplodere_se_la_cartella_non_esiste() {
        $path = $this->dir . 'non-esiste' . DIRECTORY_SEPARATOR . '_index.html';

        $this->assertFalse(SpeedUp_CacheUtils::write_atomic($path, 'x'));
    }

    // -----------------------------------------------------------------------
    // Percorsi che escono dalla cartella della cache
    // -----------------------------------------------------------------------

    /**
     * @dataProvider percorsiPericolosi
     */
    public function test_riconosce_i_percorsi_che_escono_dalla_cache($percorso) {
        $this->assertTrue(
            SpeedUp_CacheUtils::path_escapes_cache_dir($percorso),
            "Doveva essere rifiutato: $percorso"
        );
    }

    public function percorsiPericolosi() {
        return array(
            'risalita semplice'   => array('/../'),
            'risalita doppia'     => array('/../../wp-config.php'),
            'risalita in mezzo'   => array('/blog/../../etc/'),
            'url completo'        => array('https://example.test/../'),
        );
    }

    /**
     * @dataProvider percorsiLegittimi
     */
    public function test_lascia_passare_i_percorsi_normali($percorso) {
        $this->assertFalse(
            SpeedUp_CacheUtils::path_escapes_cache_dir($percorso),
            "Doveva passare: $percorso"
        );
    }

    public function percorsiLegittimi() {
        return array(
            'radice'                 => array('/'),
            'pagina'                 => array('/chi-siamo/'),
            'annidata'               => array('/blog/2026/07/un-articolo/'),
            'con punto nel nome'     => array('/versione-1.2/'),
            'con trattini'           => array('/un-titolo-lungo-cosi/'),
        );
    }

    public function test_url_to_path_rifiuta_una_risalita() {
        $this->assertNull(SpeedUp_CacheUtils::url_to_path('https://example.test/../../etc/'));
    }
}
