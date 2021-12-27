<?php

namespace Myrotvorets\WordPress\SecEnh;

use WildWolf\Utils\Singleton;

final class Plugin {
	use Singleton;

	private function __construct() {
		add_action( 'init', [ $this, 'init' ] );
	}

	public function init(): void {
		Authenticator::instance();
		Content::instance();
		Device_Watcher::instance();
		Location_Watcher::instance();
		Login_Limiter::instance();
		Login_Logger::instance();
		REST::instance();
	}
}
