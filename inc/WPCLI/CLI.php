<?php

namespace ChubesDocs\WPCLI;

use ChubesDocs\WPCLI\Commands\DocsGetCommand;
use ChubesDocs\WPCLI\Commands\DocsListCommand;
use ChubesDocs\WPCLI\Commands\DocsSearchCommand;
use ChubesDocs\WPCLI\Commands\DocsSyncCommand;
use ChubesDocs\WPCLI\Commands\ProjectEnsureCommand;
use ChubesDocs\WPCLI\Commands\ProjectTreeCommand;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLI {
	public static function register(): void {
		\WP_CLI::add_command( 'chubes project ensure', [ ProjectEnsureCommand::class, 'run' ] );
		\WP_CLI::add_command( 'chubes project tree', [ ProjectTreeCommand::class, 'run' ] );
		\WP_CLI::add_command( 'chubes docs sync', [ DocsSyncCommand::class, 'run' ] );
		\WP_CLI::add_command( 'chubes docs list', [ DocsListCommand::class, 'run' ] );
		\WP_CLI::add_command( 'chubes docs get', [ DocsGetCommand::class, 'run' ] );
		\WP_CLI::add_command( 'chubes docs search', [ DocsSearchCommand::class, 'run' ] );
	}
}
