<?php

/**
 * Plugin Name: API
 * Description: Pobiera dane z REST API WordPress i wyświetla je na stronie głównej
 * Version: 2.0
 * Author: Daria Yemelianenko
 * 
 * Funkcjonalności:
 * - Cache transient (1 godzina)
 * - Automatyczne czyszczenie cache przy zapisie posta
 * - Bezpieczne wyświetlanie (esc_html, wp_kses_post)
 * - Filtrowanie treści tylko na stronie głównej
 */


add_action('wp_enqueue_scripts', 'moje_api_dodaj_css');

function moje_api_dodaj_css()
{
    wp_enqueue_style('api-post', plugins_url('assets/css/style.css', __FILE__), array(), '1.0', 'all');
}

register_activation_hook(__FILE__, 'moja_tworzenie_tabel');

function moja_tworzenie_tabel()
{
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;

    $sql_seriale = "CREATE TABLE {$prefix}seriale (
        id INT NOT NULL AUTO_INCREMENT,
        tytul_pl VARCHAR(255) NOT NULL,
        tytul_en VARCHAR(255) NOT NULL,
        rok_premiery YEAR NOT NULL,
        liczba_sezonow INT NOT NULL,
        opis_pl TEXT NOT NULL,
        opis_en TEXT NOT NULL,
        zdjecie VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    // SQL dla tabeli postacie
    $sql_postacie = "CREATE TABLE {$prefix}postacie (
        id INT NOT NULL AUTO_INCREMENT,
        serial_id INT NOT NULL,
        imie_pl VARCHAR(255) NOT NULL,
        imie_en VARCHAR(255) NOT NULL,
        aktor_pl VARCHAR(255) NOT NULL,
        aktor_en VARCHAR(255) NOT NULL,
        biografia_pl TEXT NOT NULL,
        biografia_en TEXT NOT NULL,
        zdjecie VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    $sql_cytaty = "CREATE TABLE {$prefix}cytaty (
        id INT NOT NULL AUTO_INCREMENT,
        postac_id INT NOT NULL,
        cytat_pl TEXT NOT NULL,
        cytat_en TEXT NOT NULL,
        odcinek VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_seriale);
    dbDelta($sql_postacie);
    dbDelta($sql_cytaty);
}


function moje_aktualny_jezyk()
{

    if (isset($_COOKIE['moje_jezyk']) && in_array($_COOKIE['moje_jezyk'], ['pl', 'en'])) {
        return $_COOKIE['moje_jezyk'];
    }

    $locale = get_locale();
    return (strpos($locale, 'pl') !== false) ? 'pl' : 'en';
}

function moje_przyciski_jezyka()
{
    $aktualny = moje_aktualny_jezyk();

    $klasa_pl = ($aktualny == 'pl') ? 'active' : '';
    $klasa_en = ($aktualny == 'en') ? 'active' : '';

    $wynik = '<div class="jezyki"><ul>';
    $wynik .= '<li><button class="jezyk-btn ' . $klasa_pl . '" data-lang="pl">🇵🇱 Polski</button></li>';
    $wynik .= '<li><button class="jezyk-btn ' . $klasa_en . '" data-lang="en">🇬🇧 English</button></li>';
    $wynik .= '</ul></div>';

    add_action('wp_footer', 'moje_jezyk_js');

    return $wynik;
}
add_shortcode('jezyk', 'moje_przyciski_jezyka');


function moje_jezyk_js()
{
?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.jezyk-btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    const lang = this.getAttribute('data-lang');
                    document.cookie = "moje_jezyk=" + lang + "; path=/; max-age=2592000";
                    location.reload();
                });
            });
        });
    </script>
<?php
}

function moje_pobierz_seriale($id)
{
    global $wpdb;
    $tabela = $wpdb->prefix . 'seriale';

    $sql = $wpdb->prepare("SELECT * FROM {$tabela} WHERE id = %d", $id);
    return $wpdb->get_row($sql, ARRAY_A);
}
function moje_pobierz_wszystkie_seriale()
{
    global $wpdb;
    $tabela = $wpdb->prefix . 'seriale';
    $sql = "SELECT * FROM {$tabela} ORDER BY id ASC";
    return $wpdb->get_results($sql, ARRAY_A);
}

function pobierz_wszystkie_seriale($atts)
{
    if (is_admin() || defined('REST_REQUEST')) {
        return '';
    }

    $atts = shortcode_atts(array('id' => 0), $atts);

    
    if ($atts['id'] > 0) {
        $dane = moje_pobierz_seriale($atts['id']);
        if ($dane && (!empty($dane['tytul_pl']) || !empty($dane['tytul_en']))) {
            if (moje_aktualny_jezyk() == 'pl') {
                $dane['tytul_pl'] = $dane['tytul_pl'];
                $dane['opis_pl'] = $dane['opis_pl'];
            } else {
                $dane['tytul_pl'] = $dane['tytul_en'];
                $dane['opis_pl'] = $dane['opis_en'];
            }
            $wynik = '<div class="api-seriale">';
            $wynik .= '<h2>' . 'ID serialu: ' . esc_html($dane['id']) . '</h2>';
            $wynik .= '<h2>' . esc_html($dane['tytul_pl']) . '</h2>';
            $wynik .= '<div>' . wp_kses_post($dane['opis_pl']) . '</div>';
            $wynik .= '</div>';
            return $wynik;
        }
        return '';
    }

    
    $seriale = moje_pobierz_wszystkie_seriale();
    if (!$seriale) {
        return '';
    }

    $wynik = '<div class="seriale-lista">';
    foreach ($seriale as $serial) {
        $lang = moje_aktualny_jezyk();
        $tytul = ($lang == 'pl') ? $serial['tytul_pl'] : $serial['tytul_en'];
        $opis = ($lang == 'pl') ? $serial['opis_pl'] : $serial['opis_en'];

        $wynik .= '<div class="serial-karta">';
        $wynik .= '<img src="' . esc_url($serial['zdjecie']) . '" alt="' . esc_attr($tytul) . '">';
        $wynik .= '<h3>' . esc_html($tytul) . '</h3>';
        $wynik .= '<p><strong>Rok:</strong> ' . esc_html($serial['rok_premiery']) . '</p>';
        $wynik .= '<p><strong>Sezony:</strong> ' . esc_html($serial['liczba_sezonow']) . '</p>';
        $wynik .= '<p>' . wp_kses_post(substr($opis, 0, 100)) . '...</p>';
        $wynik .= '<a href="#" class="czytaj-wiecej">Czytaj więcej →</a>';
        $wynik .= '</div>';
    }

    return $wynik;
}


add_shortcode('api_seriale', 'pobierz_wszystkie_seriale');


add_action('rest_api_init', 'moje_rejestruj_endpoint_seriale');

function moje_rejestruj_endpoint_seriale()
{
    register_rest_route('moja-api/v1', '/seriale', array(
        'methods' => 'GET',
        'callback' => 'moje_rest_pobierz_seriale', 
        'permission_callback' => '__return_true',
    ));
}

function moje_rest_pobierz_seriale()
{
    $seriale = moje_pobierz_wszystkie_seriale(); 
    return rest_ensure_response($seriale);
}
