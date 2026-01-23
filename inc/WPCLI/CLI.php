<?php

namespace ChubesDocs\WPCLI;

use ChubesDocs\WPCLI\Commands\CodebaseEnsureCommand;
use ChubesDocs\WPCLI\Commands\CodebaseTreeCommand;
use ChubesDocs\WPCLI\Commands\DocsSyncCommand;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLI {
	public static function register(): void {
		\WP_CLI::add_command( 'chubes codebase ensure', [ CodebaseEnsureCommand::class, 'run' ] );
		\WP_CLI::add_command( 'chubes codebase tree', [ CodebaseTreeCommand::class, 'run' ] );
		\WP_CLI::add_command( 'chubes docs sync', [ DocsSyncCommand::class, 'run' ] );
	}
}
