<?php
/**
 * Abilities API Main Entry Point
 *
 * Registers the docsync ability category and coordinates initialization
 * of all ability domains (sync, codebase inspection, etc.).
 */

namespace DocSync\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Abilities {

	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_categories' ] );
		add_action( 'wp_abilities_api_init', [ SyncAbilities::class, 'register' ] );
		add_action( 'wp_abilities_api_init', [ ProjectAbilities::class, 'register' ] );
		add_action( 'wp_abilities_api_init', [ SearchAbilities::class, 'register' ] );
		add_action( 'wp_abilities_api_init', [ DocsAbilities::class, 'register' ] );
	}

	public static function register_categories(): void {
		wp_register_ability_category( 'docsync', [
			'label'       => __( 'DocSync', 'docsync' ),
			'description' => __( 'Documentation sync and management abilities', 'docsync' ),
		] );
	}
}
