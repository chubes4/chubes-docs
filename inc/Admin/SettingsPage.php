<?php
/**
 * Settings Page
 *
 * Admin settings page for GitHub sync configuration.
 * Located under Documentation → Settings.
 */

namespace DocSync\Admin;

use DocSync\Api\Controllers\SyncTriggerController;
use DocSync\Sync\CronSync;

class SettingsPage {

	const PAGE_SLUG = 'docsync-settings';
	const OPTION_GROUP = 'docsync_settings';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	/**
	 * Add settings submenu under Documentation.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=documentation',
			__( 'Docs Settings', 'docsync' ),
			__( 'Settings', 'docsync' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Register settings fields.
	 */
	public static function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			CronSync::OPTION_PAT,
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		register_setting(
			self::OPTION_GROUP,
			CronSync::OPTION_INTERVAL,
			[
				'type'              => 'string',
				'default'           => 'twicedaily',
				'sanitize_callback' => [ __CLASS__, 'sanitize_interval' ],
			]
		);

		register_setting(
			self::OPTION_GROUP,
			SyncTriggerController::OPTION_SYNC_TOKEN,
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		register_setting(
			self::OPTION_GROUP,
			SyncTriggerController::OPTION_WEBHOOK_SECRET,
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		add_settings_section(
			'docsync_github_section',
			__( 'GitHub Sync', 'docsync' ),
			[ __CLASS__, 'render_section' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'docsync_github_pat',
			__( 'Personal Access Token', 'docsync' ),
			[ __CLASS__, 'render_pat_field' ],
			self::PAGE_SLUG,
			'docsync_github_section'
		);

		add_settings_field(
			'docsync_github_test',
			__( 'Connection Test', 'docsync' ),
			[ __CLASS__, 'render_test_field' ],
			self::PAGE_SLUG,
			'docsync_github_section'
		);

		add_settings_field(
			'docsync_sync_interval',
			__( 'Sync Interval', 'docsync' ),
			[ __CLASS__, 'render_interval_field' ],
			self::PAGE_SLUG,
			'docsync_github_section'
		);

		// Sync Trigger section.
		add_settings_section(
			'docsync_sync_trigger_section',
			__( 'Sync Trigger', 'docsync' ),
			[ __CLASS__, 'render_sync_trigger_section' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'docsync_sync_token',
			__( 'Sync Token', 'docsync' ),
			[ __CLASS__, 'render_sync_token_field' ],
			self::PAGE_SLUG,
			'docsync_sync_trigger_section'
		);

		add_settings_field(
			'docsync_webhook_secret',
			__( 'Webhook Secret', 'docsync' ),
			[ __CLASS__, 'render_webhook_secret_field' ],
			self::PAGE_SLUG,
			'docsync_sync_trigger_section'
		);
	}

	/**
	 * Sanitize interval option.
	 *
	 * @param string $value Input value.
	 * @return string Sanitized interval.
	 */
	public static function sanitize_interval( string $value ): string {
		$allowed = [ 'hourly', 'twicedaily', 'daily' ];

		if ( ! in_array( $value, $allowed, true ) ) {
			return 'twicedaily';
		}

		$current = get_option( CronSync::OPTION_INTERVAL, 'twicedaily' );
		if ( $value !== $current ) {
			CronSync::reschedule( $value );
		}

		return $value;
	}

	/**
	 * Render section description.
	 */
	public static function render_section(): void {
		echo '<p>';
		esc_html_e( 'Configure automatic sync of documentation from GitHub repositories.', 'docsync' );
		echo '</p>';
	}

	/**
	 * Render PAT field.
	 */
	public static function render_pat_field(): void {
		$value = get_option( CronSync::OPTION_PAT, '' );
		$masked = ! empty( $value ) ? str_repeat( '*', 8 ) . substr( $value, -4 ) : '';
		?>
		<input
			type="password"
			id="<?php echo esc_attr( CronSync::OPTION_PAT ); ?>"
			name="<?php echo esc_attr( CronSync::OPTION_PAT ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="new-password"
		/>
		<?php if ( ! empty( $masked ) ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: masked token */
					esc_html__( 'Current token: %s', 'docsync' ),
					'<code>' . esc_html( $masked ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>
		<p class="description">
			<?php esc_html_e( 'GitHub Personal Access Token with repo read access.', 'docsync' ); ?>
			<a href="https://github.com/settings/tokens" target="_blank" rel="noopener">
				<?php esc_html_e( 'Create token', 'docsync' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render test connection field.
	 */
	public static function render_test_field(): void {
		?>
		<button type="button" class="button button-secondary" id="docsync-test-token">
			<?php esc_html_e( 'Test GitHub Connection', 'docsync' ); ?>
		</button>
		<div id="docsync-test-results" style="margin-top: 10px;"></div>
		<?php
	}

	/**
	 * Render interval field.
	 */
	public static function render_interval_field(): void {
		$value = get_option( CronSync::OPTION_INTERVAL, 'twicedaily' );
		$intervals = [
			'hourly'     => __( 'Hourly', 'docsync' ),
			'twicedaily' => __( 'Twice Daily', 'docsync' ),
			'daily'      => __( 'Daily', 'docsync' ),
		];
		?>
		<select
			id="<?php echo esc_attr( CronSync::OPTION_INTERVAL ); ?>"
			name="<?php echo esc_attr( CronSync::OPTION_INTERVAL ); ?>"
		>
			<?php foreach ( $intervals as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
		$next = CronSync::get_next_scheduled();
		if ( $next ) {
			echo '<p class="description">';
			printf(
				/* translators: %s: formatted date/time */
				esc_html__( 'Next sync: %s', 'docsync' ),
				esc_html( wp_date( 'M j, Y \a\t g:ia', $next ) )
			);
			echo '</p>';
		}
	}

	/**
	 * Render sync trigger section description.
	 */
	public static function render_sync_trigger_section(): void {
		$endpoint = rest_url( 'docsync/v1/sync' );
		echo '<p>';
		printf(
			/* translators: %s: REST endpoint URL */
			esc_html__( 'Configure authentication for the sync trigger endpoint. Any HTTP client can POST to %s to trigger a project sync.', 'docsync' ),
			'<code>' . esc_html( $endpoint ) . '</code>'
		);
		echo '</p>';
		echo '<p class="description">';
		esc_html_e( 'Configure at least one of the options below. Bearer token works with any caller. Webhook secret validates GitHub push event signatures.', 'docsync' );
		echo '</p>';
	}

	/**
	 * Render sync token field.
	 */
	public static function render_sync_token_field(): void {
		$value = get_option( SyncTriggerController::OPTION_SYNC_TOKEN, '' );
		$masked = ! empty( $value ) ? str_repeat( '*', 8 ) . substr( $value, -4 ) : '';
		?>
		<input
			type="password"
			id="<?php echo esc_attr( SyncTriggerController::OPTION_SYNC_TOKEN ); ?>"
			name="<?php echo esc_attr( SyncTriggerController::OPTION_SYNC_TOKEN ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="new-password"
		/>
		<?php if ( ! empty( $masked ) ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: masked token */
					esc_html__( 'Current token: %s', 'docsync' ),
					'<code>' . esc_html( $masked ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>
		<p class="description">
			<?php esc_html_e( 'Bearer token for authenticating sync requests. Use with: Authorization: Bearer <token>', 'docsync' ); ?>
		</p>
		<?php
	}

	/**
	 * Render webhook secret field.
	 */
	public static function render_webhook_secret_field(): void {
		$value = get_option( SyncTriggerController::OPTION_WEBHOOK_SECRET, '' );
		$masked = ! empty( $value ) ? str_repeat( '*', 8 ) . substr( $value, -4 ) : '';
		?>
		<input
			type="password"
			id="<?php echo esc_attr( SyncTriggerController::OPTION_WEBHOOK_SECRET ); ?>"
			name="<?php echo esc_attr( SyncTriggerController::OPTION_WEBHOOK_SECRET ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="new-password"
		/>
		<?php if ( ! empty( $masked ) ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: masked secret */
					esc_html__( 'Current secret: %s', 'docsync' ),
					'<code>' . esc_html( $masked ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>
		<p class="description">
			<?php esc_html_e( 'GitHub webhook secret for validating x-hub-signature-256 headers. Set the same value in your GitHub repo webhook settings.', 'docsync' ); ?>
		</p>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'docsync_messages' ); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Settings', 'docsync' ) );
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Manual Sync', 'docsync' ); ?></h2>
			<p><?php esc_html_e( 'Run a sync for all configured projects now.', 'docsync' ); ?></p>
			<button type="button" class="button button-secondary" id="docsync-sync-all">
				<?php esc_html_e( 'Sync All Now', 'docsync' ); ?>
			</button>
			<span id="docsync-sync-status"></span>
		</div>
		<?php
	}
}
