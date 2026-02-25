<?php

namespace DocSync\WPCLI;

use DocSync\WPCLI\Commands\DocsGetCommand;
use DocSync\WPCLI\Commands\DocsListCommand;
use DocSync\WPCLI\Commands\DocsSearchCommand;
use DocSync\WPCLI\Commands\DocsSyncCommand;
use DocSync\WPCLI\Commands\ProjectEnsureCommand;
use DocSync\WPCLI\Commands\ProjectTreeCommand;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLI {
	public static function register(): void {
		\WP_CLI::add_command( 'docsync project ensure', [ ProjectEnsureCommand::class, 'run' ] );
		\WP_CLI::add_command( 'docsync project tree', [ ProjectTreeCommand::class, 'run' ] );
		\WP_CLI::add_command( 'docsync docs sync', [ DocsSyncCommand::class, 'run' ] );
		\WP_CLI::add_command( 'docsync docs list', [ DocsListCommand::class, 'run' ] );
		\WP_CLI::add_command( 'docsync docs get', [ DocsGetCommand::class, 'run' ] );
		\WP_CLI::add_command( 'docsync docs search', [ DocsSearchCommand::class, 'run' ] );
	}
}
