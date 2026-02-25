<?php
/**
 * Central Plugin Configuration
 *
 * Single source of truth for all plugin identifiers. Every namespace,
 * prefix, slug, and handle in the plugin derives from these constants.
 *
 * @package DocSync
 * @since 1.0.0
 */

namespace DocSync;

/**
 * Plugin configuration constants.
 *
 * Changing SLUG and PREFIX here will cascade through the entire plugin:
 * REST namespace, WP-CLI commands, Abilities, option names, hook names,
 * cron hooks, asset handles, admin page slugs, and JS variable names.
 */
class PluginConfig {

	/**
	 * Plugin slug (kebab-case). Used for:
	 * - REST namespace ({SLUG}/v1)
	 * - WP-CLI command namespace (wp {SLUG})
	 * - Abilities category
	 * - Asset handles
	 * - Admin page slugs
	 * - Text domain
	 */
	const SLUG = 'docsync';

	/**
	 * Plugin prefix (snake_case with trailing underscore). Used for:
	 * - Option names ({PREFIX}github_pat)
	 * - Hook/filter names ({PREFIX}documentation_args)
	 * - Cron hook names ({PREFIX}github_sync)
	 * - Settings groups ({PREFIX}settings)
	 */
	const PREFIX = 'docsync_';

	/**
	 * REST API namespace.
	 */
	const REST_NAMESPACE = 'docsync/v1';

	/**
	 * PHP namespace (for reference only — actual namespace is in each file).
	 */
	const PHP_NAMESPACE = 'DocSync';

	/**
	 * Human-readable plugin name.
	 */
	const NAME = 'DocSync';

	/**
	 * GitHub API User-Agent string.
	 */
	const USER_AGENT = 'DocSync/1.0';

	/**
	 * Helper: get a prefixed option name.
	 *
	 * @param string $key Option key without prefix.
	 * @return string Full option name.
	 */
	public static function option( string $key ): string {
		return self::PREFIX . $key;
	}

	/**
	 * Helper: get a prefixed hook/filter name.
	 *
	 * @param string $key Hook key without prefix.
	 * @return string Full hook name.
	 */
	public static function hook( string $key ): string {
		return self::PREFIX . $key;
	}

	/**
	 * Helper: get an ability slug.
	 *
	 * @param string $key Ability key.
	 * @return string Full ability slug (e.g., docsync/sync-docs).
	 */
	public static function ability( string $key ): string {
		return self::SLUG . '/' . $key;
	}

	/**
	 * Helper: get a prefixed asset handle.
	 *
	 * @param string $key Handle key.
	 * @return string Full handle (e.g., docsync-sync).
	 */
	public static function handle( string $key ): string {
		return self::SLUG . '-' . $key;
	}
}
