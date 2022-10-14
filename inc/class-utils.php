<?php

namespace Myrotvorets\WordPress\SecEnh;

// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__

abstract class Utils {
	public static function is_restricted_username( string $username ): bool {
		return (bool) apply_filters( 'psb_is_restricted_username', false, $username );
	}

	/**
	 * @param null|string $fallback
	 * @return null|string
	 * @psalm-template T of string|null
	 * @psalm-param T $fallback
	 * @psalm-return string|T
	 */
	public static function get_ip( ?string $fallback = null ): ?string {
		$remote_addr = static::get_server_var_as_string( 'REMOTE_ADDR' );
		if ( $remote_addr ) {
			$ip = filter_var( $remote_addr, FILTER_VALIDATE_IP, [ 'options' => [ 'default' => '' ] ] );
			if ( ! empty( $ip ) && inet_pton( $ip ) !== false ) {
				return $ip;
			}
		}

		return $fallback;
	}

	public static function get_server_var( string $key ): string {
		return wp_unslash( static::get_server_var_as_string( $key ) );
	}

	private static function get_server_var_as_string( string $key ): string {
		// phpcs:ignore WordPressVIPMinimum.Variables, WordPress.Security
		return isset( $_SERVER[ $key ] ) && is_scalar( $_SERVER[ $key ] ) ? (string) $_SERVER[ $key ] : '';
	}

	public static function get_ua(): string {
		return self::get_server_var( 'HTTP_USER_AGENT' );
	}

	public static function get_request_method(): string {
		return strtoupper( self::get_server_var( 'REQUEST_METHOD' ) );
	}

	public static function log( string $prefix, int $facility, int $priority, string $message ): void {
		// phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- false positive
		openlog( $prefix, LOG_PERROR | LOG_PID, 'Windows' === PHP_OS_FAMILY ? LOG_USER : $facility );
		syslog( $priority, $message );
		closelog();

		do_action( 'secenh_log', $message, $prefix, $facility, $priority );
	}
}
