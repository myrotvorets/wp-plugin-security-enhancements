<?php

namespace Myrotvorets\WordPress\SecEnh;

use IXR_Error;
use WildWolf\Utils\Singleton;
use WP_Error;
use WP_User;

final class Login_Limiter {
	use Singleton;

	private const CACHE_GROUP = 'login-limiter';

	private function __construct() {
		$this->init();
	}

	public function init(): void {
		if ( wp_using_ext_object_cache() ) {
			add_action( 'wp_login_failed', [ $this, 'wp_login_failed' ] );
			add_action( 'wp_login', [ $this, 'wp_login' ] );
			add_filter( 'authenticate', [ $this, 'authenticate' ], 50, 3 );
			add_action( 'login_form_login', [ $this, 'login_form_login' ] );
			add_filter( 'xmlrpc_login_error', [ $this, 'xmlrpc_login_error' ], 10, 2 );
		}
	}

	/**
	 * @param string $username_or_email
	 */
	public function wp_login_failed( $username_or_email ): void {
		$ip = Utils::get_ip();
		if ( $ip ) {
			list( $key1, $key2 ) = $this->get_cache_keys( (string) $username_or_email, $ip );

			wp_cache_add( $key1, 0, self::CACHE_GROUP, 10 * MINUTE_IN_SECONDS );
			wp_cache_add( $key2, 0, self::CACHE_GROUP, HOUR_IN_SECONDS );

			wp_cache_incr( $key1, 1, self::CACHE_GROUP );
			wp_cache_incr( $key2, 1, self::CACHE_GROUP );
		}
	}

	/**
	 * @param string $username
	 */
	public function wp_login( $username ): void {
		$ip = Utils::get_ip();
		if ( $ip ) {
			list( $key1, $key2 ) = $this->get_cache_keys( (string) $username, $ip );

			$val = wp_cache_decr( $key1, 1, self::CACHE_GROUP );
			if ( 0 === $val ) {
				wp_cache_delete( $key1, self::CACHE_GROUP );
			}

			$val = wp_cache_decr( $key2, 1, self::CACHE_GROUP );
			if ( 0 === $val ) {
				wp_cache_delete( $key2, self::CACHE_GROUP );
			}
		}
	}

	/**
	 * @param null|WP_User|WP_Error $user     WP_User if the user is authenticated.
	 *                                        WP_Error or null otherwise.
	 * @param string                $username Username or email address.
	 * @param string                $password User password
	 * @return null|WP_User|WP_Error
	 */
	public function authenticate( $user, $username, $password ) {
		if ( ! empty( $username ) && ! empty( $password ) ) {
			$ip = Utils::get_ip();

			if ( $ip ) {
				$limited = $this->check_limits( (string) $username, $ip );
				if ( is_wp_error( $limited ) ) {
					return $limited;
				}
			}
		}

		return $user;
	}

	public function login_form_login(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( 'POST' === Utils::get_request_method() && ! empty( $_POST['log'] ) && is_scalar( $_POST['log'] ) ) {
			$ip = Utils::get_ip();
			if ( $ip ) {
				$username = sanitize_user( wp_unslash( (string) $_POST['log'] ) );
				$limited  = $this->check_limits( $username, $ip );
				if ( is_wp_error( $limited ) ) {
					login_header( __( 'Error' ), '', $limited );
					login_footer();
					exit();
				}
			}
		}
	}

	public function xmlrpc_login_error( IXR_Error $error, WP_Error $user ): IXR_Error {
		if ( 'login_limit_exceeded' == $user->get_error_code() ) {
			$error = new IXR_Error( 429, $user->get_error_message() );
		}

		return $error;
	}

	/**
	 * @psalm-return array{string, string}
	 */
	private function get_cache_keys( string $username, string $ip ): array {
		return [
			"{$ip}|{$username}",
			$ip,
		];
	}

	private function check_limits( string $username, string $ip ): ?WP_Error {
		if ( apply_filters( 'psb_is_whitelisted_ip', false, $ip ) ) {
			return null;
		}

		list( $key1, $key2 ) = $this->get_cache_keys( $username, $ip );

		$threshold1 = (int) apply_filters( 'psb_ip_username_login_threshold', 3 );
		$threshold2 = (int) apply_filters( 'psb_ip_login_threshold', 10 );

		$values = wp_cache_get_multiple( [ $key1, $key2 ], self::CACHE_GROUP );

		if ( $values[ $key1 ] >= $threshold1 || $values[ $key2 ] >= $threshold2 ) {
			Utils::log( 'wp-login-rate-limiter', LOG_AUTH, LOG_WARNING, sprintf( 'Login rate-limit: %s', $this->construct_message() ) );
			return new WP_Error( 'login_limit_exceeded', __( 'Login limit exceeded.', 'secenh' ) );
		}

		return null;
	}

	private function construct_message(): string {
		$ip   = Utils::get_ip();
		$site = get_bloginfo( 'url' );
		$ua   = Utils::get_ua();
		$uri  = Utils::get_server_var( 'REQUEST_URI' );

		$message = sprintf( 'site: %1$s, IP: %2$s, UA: %3$s, URI: %4$s', $site, $ip ?? '<unknown IP>', $ua, $uri );
		if ( $ip ) {
			$geo = IP_API::describe( IP_API::geolocate( $ip ) );
			if ( ! empty( $geo ) ) {
				$message .= ', ' . join( ', ', $geo );
			}
		}

		return $message;
	}
}
