<?php

/**
 * Plugin Name: Series Encyclopedia
 * Description: Custom database tables, REST API, shortcodes for series management with PL/EN support
 * Version: 2.0
 * Author: Daria Yemelianenko
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MS_COOKIE_EXPIRY', 30 * DAY_IN_SECONDS);

function ms_enqueue_public_styles()
{
    wp_enqueue_style('api-post', plugins_url('assets/css/style.css', __FILE__), array(), '1.0', 'all');
}

add_action('wp_enqueue_scripts', 'ms_enqueue_public_styles');

/**
 * Create database tables on plugin activation
 *
 * @since 1.0.0
 * @return void
 */
function ms_create_db()
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

register_activation_hook(__FILE__, 'ms_create_db');

function ms_get_current_language()
{

    if (isset($_COOKIE['ms_current_language']) && in_array($_COOKIE['ms_current_language'], ['pl', 'en'])) {
        return $_COOKIE['ms_current_language'];
    }

    $locale = get_locale();
    return (strpos($locale, 'pl') !== false) ? 'pl' : 'en';
}

function ms_translate_text($tekst_pl, $tekst_en)
{
    $lang = ms_get_current_language();
    return ($lang == 'pl') ? $tekst_pl : $tekst_en;
}

function ms_language_switcher()
{
    $aktualny = ms_get_current_language();

    $klasa_pl = ($aktualny == 'pl') ? 'active' : '';
    $klasa_en = ($aktualny == 'en') ? 'active' : '';

    $wynik = '<div class="jezyki"><ul>';
    $wynik .= '<li><button class="jezyk-btn ' . $klasa_pl . '" data-lang="pl">🇵🇱 PL</button></li>';
    $wynik .= '<li><button class="jezyk-btn ' . $klasa_en . '" data-lang="en">🇬🇧 EN</button></li>';
    $wynik .= '</ul></div>';

    add_action('wp_footer', 'ms_language_switcher_script');

    return $wynik;
}
add_shortcode('lang', 'ms_language_switcher');

function ms_language_switcher_script()
{
?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.jezyk-btn');
            const COOKIE_EXPIRY = <?php echo MS_COOKIE_EXPIRY; ?>;
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    const lang = this.getAttribute('data-lang');
                    document.cookie = "ms_current_language=" + lang + "; path=/; max-age=" + COOKIE_EXPIRY;
                    location.reload();
                });
            });
        });
    </script>
<?php
}

function ms_get_series_by_id($id)
{
    global $wpdb;
    $tabela = $wpdb->prefix . 'seriale';

    $sql = $wpdb->prepare("SELECT * FROM {$tabela} WHERE id = %d", $id);
    return $wpdb->get_row($sql, ARRAY_A);
}
function ms_get_all_series()
{
    global $wpdb;
    $tabela = $wpdb->prefix . 'seriale';
    $sql = "SELECT * FROM {$tabela} ORDER BY id ASC";
    return $wpdb->get_results($sql, ARRAY_A);
}
function ms_get_series_localized_text($serial)
{
    $lang = ms_get_current_language();

    return [
        'tytul' => ($lang == 'pl') ? $serial['tytul_pl'] : $serial['tytul_en'],
        'opis' => ($lang == 'pl') ? $serial['opis_pl'] : $serial['opis_en'],
    ];
}

function ms_get_series_details_url($serial)
{
    return home_url('/szczegoly-serialu/?id=' . $serial['id']);
}

function ms_shortcode_series_list()
{
    if (is_admin() || defined('REST_REQUEST')) {
        return '';
    }

    $seriale = ms_get_all_series();
    if (!$seriale) {
        return '';
    }

    $wynik = '<h1 class="ms-page-title">' . esc_html(ms_translate_text('Encyklopedia seriali', 'Series Encyclopedia')) . '</h1>';
    $wynik .= '<div class="seriale-lista">';
    foreach ($seriale as $serial) {
        $localized_text = ms_get_series_localized_text($serial);
        $tytul = $localized_text['tytul'];
        $opis = $localized_text['opis'];
        $url_serialu = ms_get_series_details_url($serial);
        $czytaj_wiecej = ms_translate_text('Czytaj więcej →', 'Read more →');
        $short_description = wp_trim_words($opis, 15, '...');
        $rating = ms_fetch_series_rating($tytul, $serial['rok_premiery']);

        $wynik .= '<div class="serial-karta">';
        $wynik .= '<img src="' . esc_url($serial['zdjecie']) . '" alt="' . esc_attr($tytul) . '">';
        $wynik .= '<h2>' . esc_html($tytul) . '</h2>';
        $wynik .= '<p>⭐ IMDb: ' . esc_html($rating) . '/10</p>';
        $wynik .= '<p>' . esc_html($short_description) . '</p>';
        $wynik .= '<a href="' . esc_url($url_serialu) . '" class="czytaj-wiecej">' . esc_html($czytaj_wiecej) . '</a>';
        $wynik .= '</div>';
    }

    return $wynik;
}

add_shortcode('api_seriale', 'ms_shortcode_series_list');

function ms_register_series_endpoint()
{
    register_rest_route('moja-api/v1', '/seriale', array(
        'methods' => 'GET',
        'callback' => 'ms_rest_get_series',
        // Public data – no authentication required
        'permission_callback' => '__return_true',
    ));
}

add_action('rest_api_init', 'ms_register_series_endpoint');

function ms_rest_get_series()
{
    $seriale = ms_get_all_series();
    return rest_ensure_response($seriale);
}

function ms_shortcode_single_series($atts)
{
    if (is_admin() || defined('REST_REQUEST')) {
        return '';
    }

    $id = isset($atts['id']) ? intval($atts['id']) : 0;

    if ($id == 0 && isset($_GET['id'])) {
        $id = intval($_GET['id']);
    }

    if ($id <= 0) {
        return '';
    }

    $dane = ms_get_series_by_id($id);

    if (!$dane) {
        return '<p>Serial nie znaleziony.</p>';
    }

    $localized_text = ms_get_series_localized_text($dane);
    $tytul = $localized_text['tytul'];
    $opis = $localized_text['opis'];
    $url_listy = home_url('/');
    $rok_label = ms_translate_text('Rok:', 'Year:');
    $sezony_label = ms_translate_text('Sezony:', 'Seasons:');
    $powrot = ms_translate_text('← Powrót do listy seriali', '← Back to series list');

    $wynik = '<div class="serial-szczegoly">';
    $wynik .= '<img src="' . esc_url($dane['zdjecie']) . '" alt="' . esc_attr($tytul) . '" class="zdjecie-szczegoly">';
    $wynik .= '<h1>' . esc_html($tytul) . '</h1>';
    $wynik .= '<p><strong>' . esc_html($rok_label) . '</strong> ' . esc_html($dane['rok_premiery']) . '</p>';
    $wynik .= '<p><strong>' . esc_html($sezony_label) . '</strong> ' . esc_html($dane['liczba_sezonow']) . '</p>';
    $wynik .= '<div>' . wp_kses_post($opis) . '</div>';
    $wynik .= '<a href="' . esc_url($url_listy) . '" class="powrot-link">' . esc_html($powrot) . '</a>';
    $wynik .= '</div>';

    return $wynik;
}

add_shortcode('pokaz_serial', 'ms_shortcode_single_series');

function ms_dynamic_series_title($title, $post_id)
{
    if (is_admin()) return $title;

    $post = get_post($post_id);
    if ($post && has_shortcode($post->post_content, 'pokaz_serial')) {
        if (isset($_GET['id']) && intval($_GET['id']) > 0) {
            $id = intval($_GET['id']);
            $dane = ms_get_series_by_id($id);
            if ($dane) {
                $lang = ms_get_current_language();
                return ($lang == 'pl') ? $dane['tytul_pl'] : $dane['tytul_en'];
            }
        }
    }
    return $title;
}

add_filter('the_title', 'ms_dynamic_series_title', 10, 2);

function ms_fetch_series_rating($title, $year)
{
    $api_key = defined('MS_OMDB_API_KEY') ? MS_OMDB_API_KEY : '';
    if (empty($api_key)) {
        return 'Brak klucza API';
    }

    $cache_key = 'omdb_rating_' . sanitize_title($title) . '_' . $year;
    $cached = get_transient($cache_key);
    if (false !== $cached) {
        return $cached;
    }

    $url = add_query_arg(['t' => $title,'y' => $year,'apikey' => $api_key,], 'http://www.omdbapi.com/');

    $response = wp_remote_get($url, ['timeout' => 5]);

    if (is_wp_error($response)) {
        error_log('OMDB API błąd: ' . $response->get_error_message());
        return 'Brak oceny';
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['imdbRating']) && $data['imdbRating'] !== 'N/A') {
        $rating = $data['imdbRating'];
        set_transient($cache_key, $rating, DAY_IN_SECONDS);
        return $rating;
    }

    return 'Brak oceny';
}
