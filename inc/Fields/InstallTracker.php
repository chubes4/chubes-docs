<?php

namespace ChubesDocs\Fields;

use ChubesDocs\Core\Codebase;

class InstallTracker {

    private const CACHE_EXPIRATION = DAY_IN_SECONDS;

    public static function init(): void {
        add_action('chubes_docs_update_installs', [self::class, 'update_all_installs']);

        if (!wp_next_scheduled('chubes_docs_update_installs')) {
            wp_schedule_event(time(), 'daily', 'chubes_docs_update_installs');
        }
    }

    public static function update_all_installs(): void {
        $terms = get_terms([
            'taxonomy'   => Codebase::TAXONOMY,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return;
        }

        foreach ($terms as $term) {
            $wp_url = Codebase::get_wp_url($term->term_id);
            if (!empty($wp_url)) {
                self::fetch_and_store_installs($term->term_id, $wp_url);
            }
        }
    }

    public static function fetch_and_store_installs(int $term_id, string $wp_url): void {
        $slug = self::extract_slug_from_wp_url($wp_url);
        if (!$slug) {
            return;
        }

        $type = self::detect_type_from_url($wp_url);
        $api_url = $type === 'theme'
            ? "https://api.wordpress.org/themes/info/1.2/?action=theme_information&slug={$slug}"
            : "https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug={$slug}";

        $response = wp_remote_get($api_url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            return;
        }

        $installs = 0;
        if ($type === 'theme' && isset($data['active_installs'])) {
            $installs = (int) $data['active_installs'];
        } elseif ($type === 'plugin' && isset($data['active_installs'])) {
            $installs = (int) $data['active_installs'];
        }

        update_term_meta($term_id, 'codebase_installs', $installs);
        update_term_meta($term_id, 'codebase_installs_updated', time());
    }

    private static function extract_slug_from_wp_url(string $url): ?string {
        if (preg_match('#wordpress\.org/(plugins|themes)/([^/]+)#', $url, $matches)) {
            return $matches[2];
        }
        return null;
    }

    private static function detect_type_from_url(string $url): string {
        if (strpos($url, '/themes/') !== false) {
            return 'theme';
        }
        return 'plugin';
    }
}
