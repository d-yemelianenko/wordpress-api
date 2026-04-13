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
function pobierz_dane_z_api($post_id)
{
    $klucz = 'api_post_' . $post_id;
    $cache = get_transient($klucz);
    if ($cache !== false) {
        return $cache;
    }
    $response = wp_remote_get('http://wordpress-api/?rest_route=/wp/v2/posts/' . $post_id);
    if (is_wp_error($response)) {
        error_log('API Błąd: ' . $response->get_error_message());
        return false;
    } else {
        $body = $response['body'];
        $data = json_decode($body, true);

        if ($data && !empty($data['id'])) {
            set_transient($klucz, $data, 3600);
        }
        return $data;
    }
};

add_action('save_post', 'wyczysc_cache_postu');

function wyczysc_cache_postu($post_id)
{
    $klucz = 'api_post_' . $post_id;
    delete_transient($klucz);
}

function dodaj_api_do_postow($content)
{
     if (is_admin() || defined('REST_REQUEST') || wp_doing_ajax()) {
        return $content;
    }

    if (is_home() && is_main_query()) {
        $post_id = get_the_ID();
        $dane = pobierz_dane_z_api($post_id);
        if (($dane && !empty($dane['title']['rendered']) && $dane['id'] == $post_id)) {
            $wynik = '<div class="api-post">';
            $wynik .= esc_html($dane['id']);
            $wynik .=  '<a href="' . esc_url($dane['link']) . '" target="_blank">Czytaj więcej</a><br>';
            $wynik .= '<h2>' . esc_html($dane['title']['rendered']) . '</h2>';
            $wynik .= '<div>' . wp_kses_post($dane['content']['rendered']) . '</div>';
            $wynik .= '</div>';
            $content = $wynik . $content;
            return $content;
        }
    }
    return $content;
}

add_filter('the_content', 'dodaj_api_do_postow');

add_action('wp_enqueue_scripts', 'moje_api_dodaj_css');

function moje_api_dodaj_css()
{
    wp_enqueue_style('api-post', plugins_url('assets/css/style.css', __FILE__), array(), '1.0', 'all');
}


function wyswietl_api_post($atts)
{
    if (is_admin() || defined('REST_REQUEST')) {
        return '';
    }
    $atts = shortcode_atts(array(
        'id' => 0,
    ), $atts);

    if ($atts['id'] > 0) {
        $dane = pobierz_dane_z_api($atts['id']);
        if ($dane && !empty($dane['title']['rendered'])) {
            $wynik = '<div class="api-post">';
            $wynik .= '<h2>' . 'ID postu: ' . esc_html($dane['id']) . '</h2>';
            $wynik .=  '<a href="' . esc_url($dane['link']) . '" target="_blank">Czytaj więcej</a><br>';
            $wynik .= '<h2>' . esc_html($dane['title']['rendered']) . '</h2>';
            $wynik .= '<div>' . wp_kses_post($dane['content']['rendered']) . '</div>';
            $wynik .= '</div>';
            return $wynik;
        } else {
            return '';
        }
    }
    return '';
}

add_shortcode('api_post', 'wyswietl_api_post');


