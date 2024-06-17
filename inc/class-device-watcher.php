<?php

namespace Myrotvorets\WordPress\SecEnh;

use WildWolf\Utils\Singleton;
use WP_User;

final class Device_Watcher {
	use Singleton;

	public const COOKIE_NAME   = 'psb_dw';
	public const USER_META_KEY = 'psb_dw';
	public const OPTION_SALT   = 'psb_dw_salt';
	public const OPTION_TIME   = 'psb_dw_time';

	private function __construct() {
		$this->init();
	}

	public function init(): void {
		/** @var mixed */
		$salt = get_option( self::OPTION_SALT );
		if ( ! $salt || ! is_string( $salt ) ) {
			$salt = wp_generate_password( 64, true, true );
			update_option( self::OPTION_SALT, $salt );
			update_option( self::OPTION_TIME, time() );
		}

		add_action( 'wp_login', [ $this, 'wp_login' ], 10, 2 );
	}

	/**
	 * @param string  $_user_login Username.
	 * @param WP_User $user        WP_User object of the logged-in user.
	 */
	public function wp_login( $_user_login, WP_User $user ): void {
		if ( false === apply_filters( 'psb_dw_check_user', true, $user ) ) {
			return;
		}

		$salt = (string) get_option( self::OPTION_SALT );
		$time = (int) get_option( self::OPTION_TIME );
		$hash = hash_hmac( 'sha256', (string) $user->ID, $salt );

		if ( $this->verify_cookie( $hash ) ) {
			return;
		}

		$this->set_cookie( $hash );
		if ( ! $this->check_meta( $user->ID ) ) {
			$grace_period = (int) apply_filters( 'psb_dw_grace_period', 2 * WEEK_IN_SECONDS );
			if ( $time + $grace_period < time() ) {
				$this->send_notification( $user );
			}
		}
	}

	private function verify_cookie( string $expected_hash ): bool {
		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		return isset( $_COOKIE[ self::COOKIE_NAME ] ) && $_COOKIE[ self::COOKIE_NAME ] === $expected_hash;
	}

	private function set_cookie( string $hash ): void {
		if ( ! headers_sent() ) {
			$ttl = (int) apply_filters( 'psb_dw_cookie_ttl', 5 * YEAR_IN_SECONDS );
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
			setcookie(
				self::COOKIE_NAME,
				$hash,
				time() + $ttl,
				(string) COOKIEPATH,
				(string) ( COOKIE_DOMAIN ?: '' ),
				is_ssl(),
				true
			);
		}
	}

	private function check_meta( int $user_id ): bool {
		$ra = Utils::get_ip( '' );
		$ua = Utils::get_ua();

		/** @var mixed */
		$arr = get_user_meta( $user_id, self::USER_META_KEY, true );
		if ( ! is_array( $arr ) ) {
			$arr = [];
		}

		$key = (string) apply_filters( 'psb_dw_meta_hash', sha1( "{$ra}|{$ua}" ), $ra, $ua );
		if ( isset( $arr[ $key ] ) ) {
			return true;
		}

		$arr[ $key ] = 1;
		update_user_meta( $user_id, self::USER_META_KEY, $arr );
		return false;
	}

	private function send_notification( WP_User $user ): void {
		if ( false === apply_filters( 'psb_dw_send_notification', true ) ) {
			return;
		}

		$blogname = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$ua       = Utils::get_ua();
		$ip       = Utils::get_ip();

		if ( null !== $ip ) {
			$location = join( ', ', IP_API::describe( IP_API::geolocate( $ip ) ) );
		} else {
			$location = __( 'Unknown location', 'secenh' );
		}

		$location = (string) apply_filters( 'psb_dw_location', $location, $ip );

		// translators: 1: site name; 2: user display name
		$subject = sprintf( __( '[%1$s] SECURITY: %2$s has logged in from an unknown device', 'secenh' ), $blogname, $user->display_name );
		$subject = (string) apply_filters( 'psb_dw_notification_subject', $subject );

		// translators: 1: user disiplay name, 2: site URL, 3: installation date, 4: login, 5: IP, 6: location, 7: user-agent
		$message = __( 'Hello,

This is an automated email to inform you that %1$s has logged into %2$s from a device that we do not recognize or that had last been used before %3$s when this monitoring was first enabled.

It is likely that %1$s has logged in from a new web browser or computer, but there is also a chance that their account has been compromised and someone else has logged into their account.

Username: %4$s
IP Address: %5$s
Guessed Location: %6$s
Browser User Agent: %7$s', 'secenh' );

		$message = sprintf(
			$message,
			$user->display_name,
			home_url(),
			date_i18n( (string) get_option( 'date_format', 'F jS, Y' ), (int) get_option( self::OPTION_TIME ) ),
			$user->user_login,
			Utils::get_ip( __( '<unknown IP>', 'secenh' ) ),
			$location,
			$ua
		);

		$message = (string) apply_filters( 'psb_dw_notification_message', $message );
		/** @var string[] */
		$to = (array) apply_filters( 'psb_dw_notification_to', [ (string) get_option( 'admin_email' ) ] );

		do_action( 'psb_dw_before_notification', $user );
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
		wp_mail( $to, $subject, $message );
		do_action( 'psb_dw_after_notification', $user );
	}
}
