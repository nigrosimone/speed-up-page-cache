<?php

use PHPUnit\Framework\TestCase;

/**
 * Test delle decisioni su quando svuotare tutta la cache.
 *
 * Sbagliare in un verso lascia pagine vecchie in giro; sbagliare nell'altro
 * svuota la cache a ogni scrittura di un'opzione, cioe' continuamente. I test
 * coprono entrambi i versi.
 */
class InvalidationTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['speedup_post_type'] = 'post';
    }

    // -----------------------------------------------------------------------
    // Contenuti del Site Editor: non hanno un URL, ma cambiano ogni pagina
    // -----------------------------------------------------------------------

    /**
     * @dataProvider tipiCheCambianoTuttoIlSito
     */
    public function test_i_contenuti_del_site_editor_svuotano_tutto($tipo) {
        $GLOBALS['speedup_post_type'] = $tipo;

        $this->assertTrue(
            SpeedUp_CacheUtils::post_affects_whole_site(1),
            "Salvare un $tipo deve svuotare tutta la cache: non ha un URL proprio."
        );
    }

    public function tipiCheCambianoTuttoIlSito() {
        return array(
            'template'        => array('wp_template'),
            'parte template'  => array('wp_template_part'),
            'stili globali'   => array('wp_global_styles'),
            'navigazione'     => array('wp_navigation'),
        );
    }

    /**
     * @dataProvider tipiConUnUrlProprio
     */
    public function test_i_contenuti_normali_non_svuotano_tutto($tipo) {
        $GLOBALS['speedup_post_type'] = $tipo;

        $this->assertFalse(
            SpeedUp_CacheUtils::post_affects_whole_site(1),
            "Un $tipo ha un URL proprio: va svuotato solo quello."
        );
    }

    public function tipiConUnUrlProprio() {
        return array(
            'articolo'          => array('post'),
            'pagina'            => array('page'),
            'allegato'          => array('attachment'),
            'tipo personalizzato' => array('prodotto'),
        );
    }

    // -----------------------------------------------------------------------
    // Opzioni che cambiano ogni pagina
    // -----------------------------------------------------------------------

    /**
     * @dataProvider opzioniDiTuttoIlSito
     */
    public function test_le_opzioni_del_sito_svuotano_tutto($opzione) {
        $this->assertTrue(
            SpeedUp_CacheUtils::is_site_wide_option($opzione),
            "Cambiare $opzione cambia ogni pagina del sito."
        );
    }

    public function opzioniDiTuttoIlSito() {
        return array(
            'titolo del sito'   => array('blogname'),
            'motto'             => array('blogdescription'),
            'indirizzo'         => array('home'),
            'permalink'         => array('permalink_structure'),
            'pagina iniziale'   => array('page_on_front'),
            'cosa in home'      => array('show_on_front'),
            'post per pagina'   => array('posts_per_page'),
            'formato data'      => array('date_format'),
            'fuso orario'       => array('timezone_string'),
            'post in evidenza'  => array('sticky_posts'),
            'base categorie'    => array('category_base'),
        );
    }

    /**
     * Il verso opposto conta quanto il primo: "updated_option" scatta a ogni
     * scrittura di opzione, e svuotare la cache per ognuna vorrebbe dire non
     * averla affatto.
     *
     * @dataProvider opzioniDaIgnorare
     */
    public function test_le_altre_opzioni_non_svuotano_nulla($opzione) {
        $this->assertFalse(
            SpeedUp_CacheUtils::is_site_wide_option($opzione),
            "Cambiare $opzione non deve svuotare la cache."
        );
    }

    public function opzioniDaIgnorare() {
        return array(
            'contatore di un plugin'   => array('un_plugin_contatore'),
            'ultimo controllo update'  => array('_site_transient_update_core'),
            'email amministratore'     => array('admin_email'),
            'opzione del tema'         => array('theme_mods_twentytwentyfour'),
            'ruolo predefinito'        => array('default_role'),
            'cron'                     => array('cron'),
        );
    }
}
