<?php

namespace ChubesDocs\Fields;

use ChubesDocs\Core\Codebase;

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
        $github_url = Codebase::get_github_url($term->term_id);
        $wp_url = Codebase::get_wp_url($term->term_id);
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
        self::render_sync_status($term);
    }

    /**
     * Render sync status info box on term edit page.
     */
    private static function render_sync_status(\WP_Term $term): void {
        $status = get_term_meta($term->term_id, 'codebase_sync_status', true);
        $last_sync = get_term_meta($term->term_id, 'codebase_last_sync_time', true);
        $last_sha = get_term_meta($term->term_id, 'codebase_last_sync_sha', true);
        $files_synced = get_term_meta($term->term_id, 'codebase_files_synced', true);
        $error = get_term_meta($term->term_id, 'codebase_sync_error', true);
        $github_url = Codebase::get_github_url($term->term_id);

        if (empty($github_url)) {
            return;
        }
        ?>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e('Sync Status', 'chubes-docs'); ?></th>
            <td>
                <div class="chubes-sync-status-box" style="background: #f0f0f1; padding: 12px; border-radius: 4px;">
                    <?php if ($status === 'success' && $last_sync): ?>
                        <p style="margin: 0 0 8px;">
                            <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                            <strong><?php esc_html_e('Last sync:', 'chubes-docs'); ?></strong>
                            <?php echo esc_html(human_time_diff($last_sync) . ' ' . __('ago', 'chubes-docs')); ?>
                        </p>
                        <?php if ($last_sha): ?>
                            <p style="margin: 0 0 8px;">
                                <strong><?php esc_html_e('Commit:', 'chubes-docs'); ?></strong>
                                <code><?php echo esc_html(substr($last_sha, 0, 7)); ?></code>
                            </p>
                        <?php endif; ?>
                        <?php if ($files_synced): ?>
                            <p style="margin: 0;">
                                <strong><?php esc_html_e('Files synced:', 'chubes-docs'); ?></strong>
                                <?php echo esc_html($files_synced); ?>
                            </p>
                        <?php endif; ?>
                    <?php elseif ($status === 'failed'): ?>
                        <p style="margin: 0 0 8px;">
                            <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                            <strong><?php esc_html_e('Sync failed', 'chubes-docs'); ?></strong>
                        </p>
                        <?php if ($error): ?>
                            <p style="margin: 0; color: #d63638;">
                                <?php echo esc_html($error); ?>
                            </p>
                        <?php endif; ?>
                    <?php elseif ($status === 'syncing'): ?>
                        <p style="margin: 0;">
                            <span class="dashicons dashicons-update" style="color: #2271b1;"></span>
                            <strong><?php esc_html_e('Sync in progress...', 'chubes-docs'); ?></strong>
                        </p>
                    <?php else: ?>
                        <p style="margin: 0;">
                            <span class="dashicons dashicons-clock" style="color: #787c82;"></span>
                            <?php esc_html_e('Never synced', 'chubes-docs'); ?>
                        </p>
                    <?php endif; ?>
                </div>
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
