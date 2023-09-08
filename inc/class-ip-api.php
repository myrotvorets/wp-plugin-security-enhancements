<?php

namespace Myrotvorets\WordPress\SecEnh;

/**
 * @psalm-type GeolocateResponse = array{ip: string, country: string, cc: string, region: string, city: string, isp: string, org: string, 'as': string, mobile: bool|null, proxy: bool|null, hosting: bool|null }
 * @package Myrotvorets\WordPress\SecEnh
 */
abstract class IP_API {
	public const CACHE_GROUP = 'ipapi';

	/**
	 * @psalm-return GeolocateResponse|null
	 */
	public static function geolocate( string $ip ): ?array {
		$key = self::get_cache_key( $ip );

		/** @psalm-var GeolocateResponse|false */
		$response = wp_cache_get( $key, self::CACHE_GROUP );
		if ( ! is_array( $response ) ) {
			$endpoint = sprintf( 'http://ip-api.com/json/%s?fields=17034779', rawurlencode( $ip ) );
			$params   = [ 'redirection' => 0 ];
			$json     = self::make_request( $endpoint, $params );
			return self::decode_entry( $json );
		}

		return $response;
	}

	/**
	 * @param string[] $ips
	 * @psalm-return GeolocateResponse[]
	 */
	public static function batch_geolocate( array $ips ): array {
		/** @psalm-var GeolocateResponse[] */
		$result = [];
		foreach ( $ips as $key => $ip ) {
			$cache_key = self::get_cache_key( $ip );

			/** @psalm-var GeolocateResponse|false */
			$r = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( is_array( $r ) ) {
				$result[ $ip ] = $r;
				unset( $ips[ $key ] );
			}
		}

		if ( ! empty( $ips ) ) {
			$endpoint = 'http://ip-api.com/batch?fields=17034779';
			$params   = [
				'body'        => (string) wp_json_encode( array_values( $ips ) ),
				'headers'     => [ 'Content-Type: application/json' ],
				'redirection' => 0,
			];

			$json = self::make_request( $endpoint, $params );
			if ( is_array( $json ) ) {
				/** @var mixed $entry */
				foreach ( $json as $entry ) {
					$data = self::decode_entry( $entry );
					if ( $data ) {
						$ip            = $data['ip'];
						$result[ $ip ] = $data;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @psalm-param GeolocateResponse|null $geo 
	 * @psalm-return string[]
	 */
	public static function describe( ?array $geo ): array {
		if ( null === $geo ) {
			return [];
		}

		$yes_no_maybe = static function ( ?bool $param ): string {
			if ( null === $param ) {
				return __( 'N/A', 'secenh' );
			}

			return $param ? __( 'Yes', 'secenh' ) : __( 'No', 'secenh' );
		};

		$result = [
			// translators: 1: country
			sprintf( __( 'Country: %s', 'secenh' ), $geo['country'] ),
			// translators: 1: region
			$geo['region'] ? sprintf( __( 'Region: %s', 'secenh' ), $geo['region'] ) : false,
			// translators: 1: city
			sprintf( __( 'City: %s', 'secenh' ), $geo['city'] ),
			// translators: 1: ISP
			sprintf( __( 'ISP: %s', 'secenh' ), $geo['isp'] ?: __( 'N/A', 'secenh' ) ),
			// translators: 1: organization
			sprintf( __( 'Organization: %s', 'secenh' ), $geo['org'] ?: __( 'N/A', 'secenh' ) ),
			// translators: 1: yes, no, or N/A
			sprintf( __( 'Mobile network: %s', 'secenh' ), $yes_no_maybe( $geo['mobile'] ) ),
			// translators: 1: yes, no, or N/A
			sprintf( __( 'TOR/Proxy/VPN: %s', 'secenh' ), $yes_no_maybe( $geo['proxy'] ) ),
			// translators: 1: yes, no, or N/A
			sprintf( __( 'Hosting: %s', 'secenh' ), $yes_no_maybe( $geo['hosting'] ) ),
		];

		return array_filter( $result );
	}

	public static function get_cache_key( string $ip ): string {
		return sprintf( 'ip:%s', strtolower( $ip ) );
	}

	/**
	 * @psalm-param array{
	 *   method?: string,
	 *   timeout?: float,
	 *   redirection?: int,
	 *   httpversion?: string,
	 *   user-agent?: string,
	 *   reject_unsafe_urls?: bool,
	 *   blocking?: bool,
	 *   headers?: string|array,
	 *   cookies?: array,
	 *   body?: string|array,
	 *   compress?: bool,
	 *   decompress?: bool,
	 *   sslverify?: bool,
	 *   sslcertificates?: string,
	 *   stream?: bool,
	 *   filename?: string,
	 *   limit_response_size?: int,
	 * } $params
	 */
	private static function make_request( string $endpoint, array $params ): ?array {
		$response = wp_remote_post( $endpoint, $params ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get

		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			if ( 200 === $code && ! empty( $body ) ) {
				/** @var mixed */
				$json = json_decode( $body, true );
				if ( is_array( $json ) ) {
					return $json;
				}
			}
		}

		return null;
	}

	/**
	 * @param mixed $entry 
	 * @psalm-return null|GeolocateResponse 
	 */
	private static function decode_entry( $entry ): ?array {
		if ( is_array( $entry ) && ! empty( $entry['status'] ) && 'success' === $entry['status'] && ! empty( $entry['query'] ) ) {
			/** @psalm-var GeolocateResponse */
			$data = [
				'ip'      => (string) $entry['query'],
				'country' => $entry['country'] ?? '',
				'cc'      => $entry['countryCode'] ?? '',
				'region'  => $entry['regionName'] ?? '',
				'city'    => $entry['city'] ?? '',
				'isp'     => $entry['isp'] ?? '',
				'org'     => $entry['org'] ?? '',
				'as'      => $entry['as'] ?? '',
				'mobile'  => isset( $entry['mobile'] ) ? (bool) $entry['mobile'] : null,
				'proxy'   => isset( $entry['proxy'] ) ? (bool) $entry['proxy'] : null,
				'hosting' => isset( $entry['hosting'] ) ? (bool) $entry['hosting'] : null,
			];

			$key = self::get_cache_key( $data['ip'] );
			wp_cache_set( $key, $data, self::CACHE_GROUP, DAY_IN_SECONDS );

			return $data;
		}

		return null;
	}
}
