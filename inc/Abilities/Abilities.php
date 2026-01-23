<?php
/**
 * Abilities API Main Entry Point
 *
 * Registers the chubes ability category and coordinates initialization
 * of all ability domains (sync, codebase inspection, etc.).
 */

namespace ChubesDocs\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Abilities {

	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_categories' ] );
		add_action( 'wp_abilities_api_init', [ SyncAbilities::class, 'register' ] );
		add_action( 'wp_abilities_api_init', [ CodebaseAbilities::class, 'register' ] );
		add_action( 'wp_abilities_api_init', [ SearchAbilities::class, 'register' ] );
	}

	public static function register_categories(): void {
		wp_register_ability_category( 'chubes', [
			'label'       => __( 'Chubes', 'chubes-docs' ),
			'description' => __( 'Core abilities for chubes.net documentation system', 'chubes-docs' ),
		] );
	}
}
