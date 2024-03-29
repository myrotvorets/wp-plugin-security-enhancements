<?php

namespace Myrotvorets\WordPress\SecEnh;

use WildWolf\Utils\Singleton;
use WP_Error;
use WP_User;

final class Authenticator {
	use Singleton;

	private const ECREDENTIALS = '<strong>Error</strong>: The credentials provided are incorrect.';

	private function __construct() {
		$this->init();
	}

	public function init(): void {
		add_filter( 'authenticate', [ $this, 'authenticate' ], 0, 2 );
		add_filter( 'login_errors', [ $this, 'login_errors' ], PHP_INT_MAX );
		add_filter( 'wp_login_errors', [ $this, 'wp_login_errors' ], PHP_INT_MAX );
	}

	/**
	 * @param null|WP_User|WP_Error $user     WP_User if the user is authenticated.
	 *                                        WP_Error or null otherwise.
	 * @param string                $username Username or email address.
	 * @return null|WP_User|WP_Error
	 */
	public function authenticate( $user, $username ) {
		/** @var WP_Error */
		static $failure = new WP_Error( 'failure', self::ECREDENTIALS );

		if ( ! is_wp_error( $user ) ) {
			$ra  = Utils::get_ip();
			$ua  = Utils::get_ua();
			$acc = Utils::get_server_var( 'HTTP_ACCEPT' );
			$sua = sanitize_text_field( $ua );
			if ( null === $ra || empty( $ua ) || empty( $acc ) || $ua !== $sua ) {
				return $failure;
			}
		}

		if ( $username ) {
			$is_restricted_username = Utils::is_restricted_username( (string) $username );
			if ( $is_restricted_username ) {
				$user = $failure;
			}
		}

		return $user;
	}

	/**
	 * @param string $error Login error message.
	 * @return string
	 */
	public function login_errors( $error ): string {
		global $errors;

		if ( ! is_wp_error( $errors ) ) {
			return (string) $error;
		}

		$codes    = $errors->get_error_codes();
		$triggers = $this->get_triggers();

		/** @var int|string $code */
		foreach ( $codes as $code ) {
			if ( isset( $triggers[ $code ] ) ) {
				$error = self::ECREDENTIALS;
				break;
			}
		}

		return (string) $error;
	}

	public function wp_login_errors( WP_Error $errors ): WP_Error {
		$triggers = $this->get_triggers();
		$codes    = $errors->get_error_codes();
		$found    = false;

		/** @var int|string $code */
		foreach ( $codes as $code ) {
			if ( isset( $triggers[ $code ] ) ) {
				$errors->remove( $code );
				$found = true;
			}
		}

		if ( $found ) {
			$errors->add( 'failure', self::ECREDENTIALS );
		}

		return $errors;
	}

	private function get_triggers(): array {
		return [
			'invalid_username'   => 1,
			'invalid_email'      => 1,
			'incorrect_password' => 1,
			'invalidcombo'       => 1,
		];
	}
}
