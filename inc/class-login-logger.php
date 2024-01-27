<?php

namespace Myrotvorets\WordPress\SecEnh;

use WildWolf\Utils\Singleton;
use WP_Error;
use WP_User;

final class Login_Logger {
	use Singleton;

	private function __construct() {
		$this->init();
	}

	public function init(): void {
		add_filter( 'authenticate', [ $this, 'authenticate' ], PHP_INT_MAX, 2 );
		add_action( 'wp_login', [ $this, 'wp_login' ], PHP_INT_MAX );
		add_action( 'wp_login_failed', [ $this, 'wp_login_failed' ], 0, 1 );
	}

	/**
	 * @param null|WP_User|WP_Error $user     WP_User if the user is authenticated.
	 *                                        WP_Error or null otherwise.
	 * @param string                $username Username or email address.
	 * @return null|WP_User|WP_Error
	 */
	public function authenticate( $user, $username ) {
		$message = sprintf( 'Login attempt: %s', $this->construct_message( (string) $username ) );
		Utils::log( 'wp-login-logger', LOG_AUTH, LOG_INFO, $message );
		return $user;
	}

	/**
	 * @param string $login
	 */
	public function wp_login( $login ): void {
		$message = sprintf( 'Login successful: %s', $this->construct_message( (string) $login ) );
		Utils::log( 'wp-login-logger', LOG_AUTH, LOG_INFO, $message );
	}

	/**
	 * @param string $login
	 */
	public function wp_login_failed( $login ): void {
		$user = Auth_Utils::get_user_by_login_or_email( (string) $login );

		if ( $user ) {
			$message = sprintf( 'Login failed: %s', $this->construct_message( $user->user_login ) );
		} else {
			$message = sprintf( 'Login failed for non-existing user: %s', $this->construct_message( (string) $login ) );
		}

		$ip = Utils::get_ip();
		if ( null !== $ip ) {
			$geo = IP_API::describe( IP_API::geolocate( $ip ) );
			if ( ! empty( $geo ) ) {
				$message .= ', ' . join( ', ', $geo );
			}
		}

		Utils::log( 'wp-login-logger', LOG_AUTH, LOG_WARNING, $message );
	}

	private function construct_message( string $login ): string {
		$ip   = Utils::get_ip( '<unknown IP>' );
		$site = get_bloginfo( 'url' );
		$ua   = Utils::get_ua();
		$uri  = Utils::get_server_var( 'REQUEST_URI' );

		return sprintf( 'site: %1$s, login: %2$s, IP: %3$s, UA: %4$s, URI: %5$s', $site, $login, $ip, $ua, $uri );
	}
}
