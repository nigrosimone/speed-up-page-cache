<?php

use PHPUnit\Framework\TestCase;

/**
 * Test del riconoscimento dei parametri di tracciamento.
 *
 * Un errore in un verso costa cache mancata; un errore nell'altro verso serve a
 * un visitatore la pagina di un altro. La seconda e' molto piu' grave, quindi i
 * test insistono sui parametri che DEVONO impedire la cache.
 */
class QueryStringTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        unset($_SERVER['QUERY_STRING']);
    }

    private function withQuery($query) {
        $_SERVER['QUERY_STRING'] = $query;
        return SpeedUp_CacheUtils::has_significant_query_string();
    }

    // -----------------------------------------------------------------------
    // Nessuna query string
    // -----------------------------------------------------------------------

    public function test_senza_query_string_la_pagina_e_cacheabile() {
        $this->assertFalse(SpeedUp_CacheUtils::has_significant_query_string());
    }

    public function test_query_string_vuota_e_cacheabile() {
        $this->assertFalse($this->withQuery(''));
    }

    // -----------------------------------------------------------------------
    // Parametri di solo tracciamento: la pagina resta la stessa
    // -----------------------------------------------------------------------

    /**
     * @dataProvider parametriDiTracciamento
     */
    public function test_i_parametri_di_tracciamento_non_impediscono_la_cache($query) {
        $this->assertFalse($this->withQuery($query), "Dovrebbe essere cacheabile: ?$query");
    }

    public function parametriDiTracciamento() {
        return array(
            'Google Ads'           => array('gclid=EAIaIQobChMI'),
            'Google Analytics'     => array('utm_source=newsletter&utm_medium=email&utm_campaign=lancio'),
            'utm con id'           => array('utm_id=123&utm_source=x'),
            'Facebook'             => array('fbclid=IwAR0abcdef'),
            'Microsoft Ads'        => array('msclkid=abc123'),
            'LinkedIn'             => array('li_fat_id=xyz'),
            'TikTok'               => array('ttclid=abc'),
            'Instagram'            => array('igshid=MTIzNA'),
            'Mailchimp'            => array('mc_cid=abc&mc_eid=def'),
            'HubSpot'              => array('_hsenc=abc&_hsmi=42'),
            'HubSpot Ads prefisso' => array('hsa_cam=1&hsa_grp=2'),
            'Matomo'               => array('mtm_source=x&mtm_campaign=y'),
            'Piwik prefisso'       => array('pk_campaign=x&pk_kwd=y'),
            'Yandex'               => array('yclid=123'),
            'misti'                => array('utm_source=fb&fbclid=abc&gclid=def'),
        );
    }

    // -----------------------------------------------------------------------
    // Parametri che cambiano la pagina: NON deve essere servita dalla cache
    // -----------------------------------------------------------------------

    /**
     * @dataProvider parametriCheCambianoLaPagina
     */
    public function test_i_parametri_che_cambiano_la_pagina_impediscono_la_cache($query) {
        $this->assertTrue(
            $this->withQuery($query),
            "NON deve essere servita dalla cache: ?$query"
        );
    }

    public function parametriCheCambianoLaPagina() {
        return array(
            'ricerca'              => array('s=cercami'),
            'post per id'          => array('p=123'),
            'pagina per id'        => array('page_id=42'),
            'paginazione'          => array('paged=2'),
            'anteprima'            => array('preview=true'),
            'risposta a commento'  => array('replytocom=99'),
            'categoria'            => array('cat=5'),
            'ordinamento prodotti' => array('orderby=price'),
            'filtro personalizzato' => array('colore=rosso'),
            'lingua'               => array('lang=en'),
            'valuta'              => array('currency=usd'),
        );
    }

    /**
     * Il caso piu' insidioso: un parametro di tracciamento insieme a uno che
     * cambia la pagina. Basta il secondo per non fidarsi.
     */
    public function test_un_parametro_significativo_annulla_i_tracciamenti() {
        $this->assertTrue($this->withQuery('utm_source=newsletter&s=cercami'));
        $this->assertTrue($this->withQuery('fbclid=abc&paged=3'));
        $this->assertTrue($this->withQuery('gclid=x&utm_source=y&orderby=date'));
    }

    /**
     * Un parametro che comincia per "utm" ma non e' un parametro utm: il
     * confronto e' sul prefisso "utm_", non su "utm".
     */
    public function test_non_confonde_un_nome_che_inizia_per_utm() {
        $this->assertTrue($this->withQuery('utmost=1'));
    }

    public function test_un_parametro_senza_valore_conta_comunque() {
        $this->assertTrue($this->withQuery('debug'));
        $this->assertFalse($this->withQuery('fbclid'));
    }
}
