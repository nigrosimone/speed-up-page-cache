<?php

use PHPUnit\Framework\TestCase;

/**
 * Test della traduzione fra URL e percorso su disco.
 *
 * E' il cuore della cache: la chiave e' il percorso, e due URL diversi che
 * finiscono sullo stesso percorso significano un visitatore che riceve la pagina
 * di un altro.
 */
class CacheUtilsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT'], $_SERVER['HTTP_HOST']);
    }

    // -----------------------------------------------------------------------
    // url_to_path
    // -----------------------------------------------------------------------

    public function test_traduce_un_url_in_un_percorso() {
        $path = SpeedUp_CacheUtils::url_to_path('https://example.test/blog/articolo/');

        $this->assertStringContainsString('example.test', $path);
        $this->assertStringContainsString('blog', $path);
        $this->assertStringContainsString('articolo', $path);
    }

    public function test_url_diversi_danno_percorsi_diversi() {
        $uno = SpeedUp_CacheUtils::url_to_path('https://example.test/pagina-uno/');
        $due = SpeedUp_CacheUtils::url_to_path('https://example.test/pagina-due/');

        $this->assertNotSame($uno, $due);
    }

    /**
     * Due domini sullo stesso WordPress non devono condividere la cache:
     * sarebbe un visitatore che vede il sito sbagliato.
     */
    public function test_domini_diversi_non_condividono_il_percorso() {
        $uno = SpeedUp_CacheUtils::url_to_path('https://uno.test/pagina/');
        $due = SpeedUp_CacheUtils::url_to_path('https://due.test/pagina/');

        $this->assertNotSame($uno, $due);
    }

    public function test_la_query_string_non_entra_nel_percorso() {
        $senza = SpeedUp_CacheUtils::url_to_path('https://example.test/pagina/');
        $con   = SpeedUp_CacheUtils::url_to_path('https://example.test/pagina/?utm_source=x');

        $this->assertSame($senza, $con);
    }

    public function test_nessun_percorso_senza_host() {
        $this->assertNull(SpeedUp_CacheUtils::url_to_path('/solo-un-percorso/'));
        $this->assertNull(SpeedUp_CacheUtils::url_to_path(''));
    }

    public function test_il_percorso_termina_con_un_separatore() {
        $path = SpeedUp_CacheUtils::url_to_path('https://example.test/pagina/');

        $this->assertStringEndsWith(DIRECTORY_SEPARATOR, $path);
    }

    public function test_il_percorso_non_inizia_con_un_separatore() {
        $path = SpeedUp_CacheUtils::url_to_path('https://example.test/pagina/');

        $this->assertStringStartsNotWith(DIRECTORY_SEPARATOR, $path);
    }

    // -----------------------------------------------------------------------
    // Schema della richiesta
    // -----------------------------------------------------------------------

    public function test_riconosce_https_dalla_variabile_di_ambiente() {
        $_SERVER['HTTPS'] = 'on';

        $this->assertTrue(SpeedUp_CacheUtils::is_https());
    }

    public function test_off_non_significa_https() {
        // Alcuni server impostano HTTPS a "off" invece di non impostarlo.
        $_SERVER['HTTPS'] = 'off';

        $this->assertFalse(SpeedUp_CacheUtils::is_https());
    }

    public function test_riconosce_https_dalla_porta() {
        $_SERVER['SERVER_PORT'] = '443';

        $this->assertTrue(SpeedUp_CacheUtils::is_https());
    }

    public function test_senza_indicazioni_non_e_https() {
        $this->assertFalse(SpeedUp_CacheUtils::is_https());
    }

    // -----------------------------------------------------------------------
    // Lettura della richiesta
    // -----------------------------------------------------------------------

    public function test_legge_un_valore_dalla_richiesta() {
        $_REQUEST['una_chiave'] = 'un valore';

        $this->assertSame('un valore', SpeedUp_CacheUtils::get_request('una_chiave'));

        unset($_REQUEST['una_chiave']);
    }

    public function test_restituisce_il_valore_di_riserva_se_la_chiave_manca() {
        $this->assertSame('riserva', SpeedUp_CacheUtils::get_request('chiave_che_non_esiste', 'riserva'));
        $this->assertNull(SpeedUp_CacheUtils::get_request('chiave_che_non_esiste'));
    }
}
