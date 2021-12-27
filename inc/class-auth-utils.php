<?php

namespace Myrotvorets\WordPress\SecEnh;

use WP_User;

abstract class Auth_Utils {
	public static function get_user_by_login_or_email( string $login ): ?WP_User {
		$user = get_user_by( 'login', $login );
		if ( false === $user && false !== strpos( $login, '@', 1 ) ) {
			$user = get_user_by( 'email', $login );
		}

		return $user ?: null;
	}
}
