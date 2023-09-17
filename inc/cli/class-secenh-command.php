<?php

namespace Myrotvorets\WordPress\SecEnh;

use WP_CLI;

class SecEnh_Command {
	public static function register(): void {
		WP_CLI::add_command(
			'secenh',
			__CLASS__,
			[
				'shortdesc' => 'Security Enhancements plugin',
			]
		);

		SecEnh_IPAPI_Command::register();
		SecEnh_Ban_Command::register();
		SecEnh_Is_Banned_Command::register();
	}
}
