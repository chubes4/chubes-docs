<?php

namespace ChubesDocs\WPCLI;

use ChubesDocs\WPCLI\Commands\ProjectEnsureCommand;
use ChubesDocs\WPCLI\Commands\ProjectTreeCommand;
use ChubesDocs\WPCLI\Commands\DocsSyncCommand;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLI {
	public static function register(): void {
		\WP_CLI::add_command( 'chubes project ensure', [ ProjectEnsureCommand::class, 'run' ] );
		\WP_CLI::add_command( 'chubes project tree', [ ProjectTreeCommand::class, 'run' ] );
		\WP_CLI::add_command( 'chubes docs sync', [ DocsSyncCommand::class, 'run' ] );
	}
}
