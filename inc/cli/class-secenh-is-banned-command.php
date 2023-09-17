<?php

namespace Myrotvorets\WordPress\SecEnh;

use WP_CLI;

class SecEnh_Is_Banned_Command {
	public static function register(): void {
		if ( wp_using_ext_object_cache() ) {
			WP_CLI::add_command(
				'secenh is-banned',
				__CLASS__,
				[ 'shortdesc' => 'Check if an IP or a User-Agent is banned.' ]
			);

			WP_CLI::add_command(
				'secenh is-banned ip',
				[ __CLASS__, 'is_banned_ip' ],
				[
					'shortdesc' => 'Check if an IP address is banned.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'ip',
							'description' => 'IP address to check.',
							'required'    => true,
							'repeating'   => true,
						],
					],
				]
			);

			WP_CLI::add_command(
				'secenh is-banned ua',
				[ __CLASS__, 'is_banned_ua' ],
				[
					'shortdesc' => 'Check if a User-Agent is banned.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'ua',
							'description' => 'User-Agent to ban (exact match).',
							'required'    => true,
						],
					],
				]
			);
		}
	}

	/**
	 * @param string[] $args
	 */
	public static function is_banned_ip( array $args ): void {
		$args = array_unique( $args );
		foreach ( $args as $ip ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE ) ) {
				if ( Banhammer::is_ip_banned( $ip ) ) {
					// translators: 1: IP address
					WP_CLI::log( sprintf( __( '"%s" is banned', 'secenh' ), $ip ) );
				} else {
					// translators: 1: IP address
					WP_CLI::log( sprintf( __( '"%s" is not banned', 'secenh' ), $ip ) );
				}
			} else {
				// translators: 1: IP address
				WP_CLI::warning( sprintf( __( '"%s" is not a valid/allowed IP address', 'secenh' ), $ip ) );
			}
		}
	}

	/**
	 * @param string[] $args
	 */
	public static function is_banned_ua( array $args ): void {
		$ua = $args[0];
		if ( Banhammer::is_ua_banned( $ua ) ) {
			// translators: 1: user-agent
			WP_CLI::log( sprintf( __( 'User-Agent "%s" is banned', 'secenh' ), $ua ) );
		} else {
			// translators: 1: user-agent
			WP_CLI::log( sprintf( __( 'User-Agent "%s" is not banned', 'secenh' ), $ua ) );
		}
	}
}
