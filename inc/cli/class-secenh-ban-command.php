<?php

namespace Myrotvorets\WordPress\SecEnh;

use WP_CLI;

class SecEnh_Ban_Command {
	public static function register(): void {
		if ( wp_using_ext_object_cache() ) {
			WP_CLI::add_command(
				'secenh ban',
				__CLASS__,
				[ 'shortdesc' => 'Ban IPs or User-Agents.' ]
			);

			WP_CLI::add_command(
				'secenh unban',
				__CLASS__,
				[ 'shortdesc' => 'Unban IPs or User-Agents.' ]
			);

			WP_CLI::add_command(
				'secenh ban ip',
				[ __CLASS__, 'ban_ip' ],
				[
					'shortdesc' => 'Ban IP address.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'ip',
							'description' => 'IP address to ban.',
							'required'    => true,
							'repeating'   => true,
						],
						[
							'type'        => 'assoc',
							'name'        => 'ttl',
							'description' => 'Ban duration in seconds.',
							'default'     => 86400,
							'required'    => false,
						],
					],
				]
			);

			WP_CLI::add_command(
				'secenh ban ua',
				[ __CLASS__, 'ban_ua' ],
				[
					'shortdesc' => 'Ban User-Agent.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'ua',
							'description' => 'User-Agent to ban (exact match).',
							'required'    => true,
						],
						[
							'type'        => 'assoc',
							'name'        => 'ttl',
							'description' => 'Ban duration in seconds.',
							'default'     => 86400,
							'required'    => false,
						],
					],
				]
			);

			WP_CLI::add_command(
				'secenh unban ip',
				[ __CLASS__, 'unban_ip' ],
				[
					'shortdesc' => 'Unban IP address.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'ip',
							'description' => 'IP address to unban.',
							'required'    => true,
							'repeating'   => true,
						],
					],
				]
			);

			WP_CLI::add_command(
				'secenh unban ua',
				[ __CLASS__, 'unban_ua' ],
				[
					'shortdesc' => 'Unban User-Agent.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'ua',
							'description' => 'User-Agent to unban (exact match).',
							'required'    => true,
						],
					],
				]
			);
		}
	}

	/**
	 * @param string[] $args
	 * @param mixed[] $assoc
	 * @psalm-param array{ttl: scalar} $assoc
	 */
	public static function ban_ip( array $args, array $assoc ): void {
		$ttl = (int) $assoc['ttl'];
		if ( $ttl < 0 ) {
			// translators: 1: ban duration
			WP_CLI::error( __( '"%d" is not a valid ban duration', 'secenh' ), $ttl );
			return;
		}

		$banned = 0;
		$args   = array_unique( $args );
		foreach ( $args as $ip ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE ) ) {
				++$banned;
				Banhammer::ban_by_ip( $ip, $ttl );
				// translators: 1: IP address
				WP_CLI::log( sprintf( __( 'Banned %s', 'secenh' ), $ip ) );
			} else {
				// translators: 1: IP address
				WP_CLI::warning( sprintf( __( '"%s" is not a valid/allowed IP address', 'secenh' ), $ip ) );
			}
		}

		if ( $banned ) {
			// translators: 1: number of banned IPs
			WP_CLI::success( sprintf( _n( '%u address banned', '%u addresses banned', $banned, 'secenh' ), $banned ) );
		} else {
			WP_CLI::error( __( 'no addresses banned', 'secenh' ) );
		}
	}

	/**
	 * @param string[] $args
	 * @param mixed[] $assoc
	 * @psalm-param array{ttl: scalar} $assoc
	 */
	public static function ban_ua( array $args, array $assoc ): void {
		$ttl = (int) $assoc['ttl'];
		if ( $ttl < 0 ) {
			// translators: 1: ban duration
			WP_CLI::error( __( '"%d" is not a valid ban duration', 'secenh' ), $ttl );
			return;
		}

		$ua = $args[0];
		Banhammer::ban_by_ua( $ua, $ttl );
		// translators: 1: user-agent
		WP_CLI::success( sprintf( __( 'User-Agent "%s" banned', 'secenh' ), $ua ) );
	}

	/**
	 * @param string[] $args
	 */
	public static function unban_ip( array $args ): void {
		$unbanned = 0;
		$args     = array_unique( $args );
		foreach ( $args as $ip ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				if ( Banhammer::is_ip_banned( $ip ) ) {
					++$unbanned;
					Banhammer::unban_ip( $ip );
					// translators: 1: IP address
					WP_CLI::log( sprintf( __( 'Unbanned %s', 'secenh' ), $ip ) );
				} else {
					// translators: 1: IP address
					WP_CLI::warning( sprintf( __( '"%s" is not banned', 'secenh' ), $ip ) );
				}
			} else {
				// translators: 1: IP address
				WP_CLI::warning( sprintf( __( '"%s" is not a valid IP address', 'secenh' ), $ip ) );
			}
		}

		if ( $unbanned ) {
			// translators: 1: number of unbanned IPs
			WP_CLI::success( sprintf( _n( '%u address unbanned', '%u addresses unbanned', $unbanned, 'secenh' ), $unbanned ) );
		} else {
			WP_CLI::error( __( 'no addresses unbanned', 'secenh' ) );
		}
	}

	/**
	 * @param string[] $args
	 */
	public static function unban_ua( array $args ): void {
		$ua = $args[0];
		if ( Banhammer::is_ua_banned( $ua ) ) {
			Banhammer::unban_ua( $ua );
			// translators: 1: user-agent
			WP_CLI::success( sprintf( __( 'User-Agent "%s" unbanned', 'secenh' ), $ua ) );
		} else {
			// translators: 1: user-agent
			WP_CLI::error( sprintf( __( 'User-Agent "%s" is not banned', 'secenh' ), $ua ) );
		}
	}
}
