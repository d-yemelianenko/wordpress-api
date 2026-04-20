# Series Encyclopedia – WordPress Plugin

WordPress plugin that adds series database with REST API, shortcodes, and OMDB integration.

## Features
- Custom database tables (`seriale`, `postacie`, `cytaty`)
- REST API: `GET /wp-json/moja-api/v1/serie`
- Shortcode `[api_seriale]` – list of all series
- Shortcode `[pokaz_serial]` – single series details
- OMDB API integration (IMDb ratings and posters)
- Bilingual (PL/EN) with cookie persistence

## Installation
1. Copy `moje-api` folder to `/wp-content/plugins/`
2. Activate plugin
3. Add `[api_seriale]` to any page
4. Create page "Series Details" with `[pokaz_serial]`

## Screenshots
<img width="1142" height="797" alt="screenshot png" src="https://github.com/user-attachments/assets/d8d5a9d2-505c-43c7-adfd-a17779e7cb60" />

## Requirements
- WordPress 6.0+
- PHP 7.4+
- OMDB API key (add to `wp-config.php`: `define('MS_OMDB_API_KEY', 'your_key');`)

## Author
Daria Yemelianenko
