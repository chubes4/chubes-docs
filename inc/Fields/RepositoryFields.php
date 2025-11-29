<?php

namespace ChubesDocs\Fields;

class RepositoryFields {

    public static function init(): void {
        add_action('codebase_add_form_fields', [self::class, 'add_fields']);
        add_action('codebase_edit_form_fields', [self::class, 'edit_fields'], 10, 2);
        add_action('created_codebase', [self::class, 'save_fields']);
        add_action('edited_codebase', [self::class, 'save_fields']);
    }

    public static function add_fields(): void {
        ?>
        <div class="form-field">
            <label for="codebase_github_url"><?php esc_html_e('GitHub URL', 'chubes-docs'); ?></label>
            <input type="url" name="codebase_github_url" id="codebase_github_url" value="">
            <p class="description"><?php esc_html_e('GitHub repository URL', 'chubes-docs'); ?></p>
        </div>
        <div class="form-field">
            <label for="codebase_wp_url"><?php esc_html_e('WordPress.org URL', 'chubes-docs'); ?></label>
            <input type="url" name="codebase_wp_url" id="codebase_wp_url" value="">
            <p class="description"><?php esc_html_e('WordPress.org plugin or theme URL', 'chubes-docs'); ?></p>
        </div>
        <?php
    }

    public static function edit_fields(\WP_Term $term): void {
        $github_url = chubes_get_codebase_github_url($term->term_id);
        $wp_url = chubes_get_codebase_wp_url($term->term_id);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="codebase_github_url"><?php esc_html_e('GitHub URL', 'chubes-docs'); ?></label></th>
            <td>
                <input type="url" name="codebase_github_url" id="codebase_github_url" value="<?php echo esc_attr($github_url); ?>">
                <p class="description"><?php esc_html_e('GitHub repository URL', 'chubes-docs'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="codebase_wp_url"><?php esc_html_e('WordPress.org URL', 'chubes-docs'); ?></label></th>
            <td>
                <input type="url" name="codebase_wp_url" id="codebase_wp_url" value="<?php echo esc_attr($wp_url); ?>">
                <p class="description"><?php esc_html_e('WordPress.org plugin or theme URL', 'chubes-docs'); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function save_fields(int $term_id): void {
        if (isset($_POST['codebase_github_url'])) {
            $github_url = esc_url_raw(wp_unslash($_POST['codebase_github_url']));
            update_term_meta($term_id, 'codebase_github_url', $github_url);
        }

        if (isset($_POST['codebase_wp_url'])) {
            $wp_url = esc_url_raw(wp_unslash($_POST['codebase_wp_url']));
            update_term_meta($term_id, 'codebase_wp_url', $wp_url);
        }
    }
}
