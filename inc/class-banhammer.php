<?php

namespace Myrotvorets\WordPress\SecEnh;

use WildWolf\Utils\Singleton;
use WildWolf\WordPress\WP_Request_Context;

final class Banhammer {
	use Singleton;

	public const CACHE_GROUP = 'banhammer';

	private function __construct() {
		$this->check_request();
	}

	public function check_request(): void {
		self::check_ip();
		self::check_ua();
	}

	/**
	 * @psalm-return never
	 */
	public static function goodbye( string $value, string $criterion ): void {
		http_response_code( 403 );
		header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
		header( 'Expires: Sat, 24 Aug 1991 00:00:00 GMT' );
		header( 'Connection: close' );

		echo 'สีซอให้ควายฟัง', PHP_EOL;
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		$message = sprintf( 'Site %1$s: blocking request basing on "%2$s" = "%3$s"', home_url(), $criterion, $value );
		Utils::log( 'wp-banhammer', LOG_USER, LOG_WARNING, $message );

		exit();
	}

	public static function get_ip_key( string $ip ): string {
		return sprintf( 'ip:%s', strtolower( $ip ) );
	}

	public static function get_ua_key( string $ua ): string {
		return sprintf( 'ua:%s', $ua );
	}

	public static function ban_by_ip( string $ip, int $ttl = 86400 ): void {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		wp_cache_set( self::get_ip_key( $ip ), true, self::CACHE_GROUP, $ttl );
		self::check_ip();
	}

	public static function ban_by_ua( string $ua, int $ttl = 86400 ): void {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		wp_cache_set( self::get_ua_key( $ua ), true, self::CACHE_GROUP, $ttl );
		self::check_ua();
	}

	public static function ban_by_ip_range( string $from, string $to ): void {
		$from = inet_pton( $from );
		$to   = inet_pton( $to );

		if ( false === $from || false === $to || strlen( $from ) !== strlen( $to ) ) {
			return;
		}

		$ips = self::get_all_ips();

		foreach ( $ips as $ip => $reason ) {
			$bin = inet_pton( $ip );
			if ( false !== $bin && strlen( $bin ) === strlen( $from ) && $from <= $bin && $bin <= $to ) {
				self::goodbye( $ip, $reason );
			}
		}
	}

	public static function ban_by_header( string $header, string $value ): void {
		if ( self::is_web_request() ) {
			$key = str_replace( '-', '_', strtoupper( $header ) );
			if ( 'HTTP_' !== substr( $key, 0, strlen( 'HTTP_' ) ) ) {
				$key = 'HTTP_' . $key;
			}

			$val = Utils::get_server_var( $key );
			if ( $val === $value ) {
				self::goodbye( $value, $header );
			}
		}
	}

	public static function is_ip_banned( string $ip ): bool {
		return wp_cache_get( self::get_ip_key( $ip ), self::CACHE_GROUP ) !== false;
	}

	public static function is_ua_banned( string $ua ): bool {
		return wp_cache_get( self::get_ua_key( $ua ), self::CACHE_GROUP ) !== false;
	}

	public static function unban_ip( string $ip ): void {
		wp_cache_delete( self::get_ip_key( $ip ), self::CACHE_GROUP );
	}

	public static function unban_ua( string $ua ): void {
		wp_cache_delete( self::get_ua_key( $ua ), self::CACHE_GROUP );
	}

	private static function check_ip(): void {
		if ( self::is_web_request() ) {
			$ips = self::get_all_ips();
			foreach ( $ips as $ip => $field ) {
				$key = self::get_ip_key( $ip );
				if ( true === wp_cache_get( $key, self::CACHE_GROUP ) ) {
					self::goodbye( $ip, $field );
				}
			}
		}
	}

	/**
	 * @return string[]
	 * @psalm-return array<string,string>
	 */
	public static function get_all_ips(): array {
		/** @var string[] */
		static $headers = [
			'HTTP_X_PROXYUSER_IP',
			'HTTP_TRUE_CLIENT_IP',
			'HTTP_X_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
		];

		$ips = [];

		$ip = Utils::get_ip();
		if ( null !== $ip && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			$ips[ $ip ] = 'IP';
		}

		foreach ( $headers as $header ) {
			$ip = trim( Utils::get_server_var( $header ) );
			if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				$ips[ $ip ] = $header;
			}
		}

		$xff = explode( ',', Utils::get_server_var( 'HTTP_X_FORWARDED_FOR' ) );
		$xff = array_map( 'trim', $xff );
		foreach ( $xff as $ip ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				$ips[ $ip ] = 'HTTP_X_FORWARDED_FOR';
			}
		}

		$forwarded = Utils::get_server_var( 'HTTP_X_FORWARDED' );
		$matches   = [];
		preg_match_all( '/for\\s*=\\s*("[^"]++"|[^,;\s]+)/i', $forwarded, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $ip ) {
				if ( $ip ) {
					$m = [];
					if ( '"' === $ip[0] && preg_match( '/^"\[([^]]+)]/', $ip, $m ) ) {
						$ip = $m[1];
					} else {
						$ip = explode( ':', $ip )[0];
					}
				}

				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					$ips[ $ip ] = 'HTTP_X_FORWARDED';
				}
			}
		}

		return $ips;
	}

	private static function check_ua(): void {
		if ( self::is_web_request() ) {
			$ua  = Utils::get_ua();
			$key = self::get_ua_key( $ua );
			if ( true === wp_cache_get( $key, self::CACHE_GROUP ) ) {
				self::goodbye( $ua, 'User-Agent' );
			}
		}
	}

	private static function is_web_request(): bool {
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		return isset( $_SERVER['REMOTE_ADDR'] ) && ! WP_Request_Context::is_installing() && ! WP_Request_Context::is_wp_cli() && ! WP_Request_Context::is_cron();
	}
}
