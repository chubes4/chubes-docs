<?php
/**
 * Settings Page
 *
 * Admin settings page for GitHub sync configuration.
 * Located under Documentation → Settings.
 */

namespace ChubesDocs\Admin;

use ChubesDocs\Sync\CronSync;

class SettingsPage {

	const PAGE_SLUG = 'chubes-docs-settings';
	const OPTION_GROUP = 'chubes_docs_settings';

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
			__( 'Docs Settings', 'chubes-docs' ),
			__( 'Settings', 'chubes-docs' ),
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

		add_settings_section(
			'chubes_docs_github_section',
			__( 'GitHub Sync', 'chubes-docs' ),
			[ __CLASS__, 'render_section' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'chubes_docs_github_pat',
			__( 'Personal Access Token', 'chubes-docs' ),
			[ __CLASS__, 'render_pat_field' ],
			self::PAGE_SLUG,
			'chubes_docs_github_section'
		);

		add_settings_field(
			'chubes_docs_github_test',
			__( 'Connection Test', 'chubes-docs' ),
			[ __CLASS__, 'render_test_field' ],
			self::PAGE_SLUG,
			'chubes_docs_github_section'
		);

		add_settings_field(
			'chubes_docs_sync_interval',
			__( 'Sync Interval', 'chubes-docs' ),
			[ __CLASS__, 'render_interval_field' ],
			self::PAGE_SLUG,
			'chubes_docs_github_section'
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
		esc_html_e( 'Configure automatic sync of documentation from GitHub repositories.', 'chubes-docs' );
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
					esc_html__( 'Current token: %s', 'chubes-docs' ),
					'<code>' . esc_html( $masked ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>
		<p class="description">
			<?php esc_html_e( 'GitHub Personal Access Token with repo read access.', 'chubes-docs' ); ?>
			<a href="https://github.com/settings/tokens" target="_blank" rel="noopener">
				<?php esc_html_e( 'Create token', 'chubes-docs' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render test connection field.
	 */
	public static function render_test_field(): void {
		?>
		<button type="button" class="button button-secondary" id="chubes-docs-test-token">
			<?php esc_html_e( 'Test GitHub Connection', 'chubes-docs' ); ?>
		</button>
		<div id="chubes-docs-test-results" style="margin-top: 10px;"></div>
		<?php
	}

	/**
	 * Render interval field.
	 */
	public static function render_interval_field(): void {
		$value = get_option( CronSync::OPTION_INTERVAL, 'twicedaily' );
		$intervals = [
			'hourly'     => __( 'Hourly', 'chubes-docs' ),
			'twicedaily' => __( 'Twice Daily', 'chubes-docs' ),
			'daily'      => __( 'Daily', 'chubes-docs' ),
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
				esc_html__( 'Next sync: %s', 'chubes-docs' ),
				esc_html( wp_date( 'M j, Y \a\t g:ia', $next ) )
			);
			echo '</p>';
		}
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

			<?php settings_errors( 'chubes_docs_messages' ); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Settings', 'chubes-docs' ) );
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Manual Sync', 'chubes-docs' ); ?></h2>
			<p><?php esc_html_e( 'Run a sync for all configured projects now.', 'chubes-docs' ); ?></p>
			<button type="button" class="button button-secondary" id="chubes-docs-sync-all">
				<?php esc_html_e( 'Sync All Now', 'chubes-docs' ); ?>
			</button>
			<span id="chubes-docs-sync-status"></span>
		</div>
		<?php
	}
}
