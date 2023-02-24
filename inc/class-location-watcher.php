<?php

namespace Myrotvorets\WordPress\SecEnh;

use WildWolf\Utils\Singleton;
use WP_User;

/**
 * @psalm-import-type GeolocateResponse from IP_API
 */
final class Location_Watcher {
	use Singleton;

	public const USER_META_KEY = 'psb_lw';

	private string $ip = '';
	/** @psalm-var GeolocateResponse|null|false */
	private $location = false;

	private function __construct() {
		$this->init();
	}

	public function init(): void {
		$ip_address = Utils::get_ip();
		if ( $ip_address ) {
			$this->ip = $ip_address;
			add_action( 'wp_login', [ $this, 'wp_login' ], 10, 2 );
			add_filter( 'attach_session_information', [ $this, 'attach_session_information' ] );
		}
	}

	/**
	 * @param string  $_user_login Username.
	 * @param WP_User $user        WP_User object of the logged-in user.
	 */
	public function wp_login( $_user_login, WP_User $user ): void {
		if ( false === apply_filters( 'psb_lw_check_user', true, $user ) ) {
			return;
		}

		if ( apply_filters( 'psb_is_whitelisted_ip', false, $this->ip ) ) {
			return;
		}

		if ( false === $this->location ) {
			$this->location = IP_API::geolocate( $this->ip );
		}

		if ( ! $this->location ) {
			$message = sprintf( 'Sign-on to %1$s by %3$s from an unknown new location: IP: %2$s', home_url(), $this->ip, $user->user_login );
			Utils::log( 'wp-location-watcher', LOG_AUTH, LOG_WARNING, $message );
			return;
		}

		if ( ! $this->check_meta( $user->ID, $this->location ) ) {
			$this->send_notification( $user, $this->location );
			$message = sprintf( 'Sign-on to %1$s by %4$s from a new location: IP: %2$s, %3$s', home_url(), $this->ip, join( ', ', IP_API::describe( $this->location ) ), $user->user_login );
			Utils::log( 'wp-location-watcher', LOG_AUTH, LOG_WARNING, $message );
		}
	}

	/**
	 * @param mixed[] $info
	 * @return mixed[]
	 */
	public function attach_session_information( array $info ): array {
		if ( false === $this->location ) {
			$this->location = IP_API::geolocate( $this->ip );
		}

		if ( $this->location ) {
			$info['Location'] = join( ', ', IP_API::describe( $this->location ) );
		}

		return $info;
	}

	/**
	 * @psalm-param GeolocateResponse $location
	 */
	private function check_meta( int $user_id, array $location ): bool {
		/** @var mixed */
		$arr = get_user_meta( $user_id, self::USER_META_KEY, true );
		if ( ! is_array( $arr ) ) {
			$arr = [];
		}

		$key = join( "\0", [ $location['country'], $location['city'], $location['isp'] ] );
		$res = isset( $arr[ $key ] );

		$arr[ $key ] = time();
		update_user_meta( $user_id, self::USER_META_KEY, $arr );

		return $res;
	}

	/**
	 * @psalm-param GeolocateResponse $location
	 */
	private function send_notification( WP_User $user, array $location ): void {
		if ( false === apply_filters( 'psb_lw_send_notification', true ) ) {
			return;
		}

		$blogname = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$ua       = Utils::get_ua();
		$location = join( ', ', IP_API::describe( $location ) );
		$location = (string) apply_filters( 'psb_lw_location', $location, $this->ip );

		// translators: 1: site name; 2: user display name
		$subject = sprintf( __( '[%1$s] SECURITY: %2$s has logged in from a new location', 'secenh' ), $blogname, $user->display_name );
		$subject = (string) apply_filters( 'psb_lw_notification_subject', $subject );

		// translators: 1: user disiplay name, 2: site URL, 3: login, 4: IP, 5: location, 6: user-agent
		$message = __( 'Hello,

This is an automated email to inform you that %1$s has logged into %2$s from a new location.

It is likely that %1$s has logged in from a new web computer, but there is also a chance that their account has been compromised and someone else has logged into their account.

Username: %3$s
IP Address: %4$s
Guessed Location: %5$s
Browser User Agent: %6$s', 'secenh' );

		$message = sprintf(
			$message,
			$user->display_name,
			home_url(),
			$user->user_login,
			$this->ip,
			$location,
			$ua
		);

		$message = (string) apply_filters( 'psb_lw_notification_message', $message );
		/** @var string[] */
		$to = (array) apply_filters( 'psb_lw_notification_to', [ (string) get_option( 'admin_email' ) ] );

		do_action( 'psb_lw_before_notification', $user );
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
		wp_mail( $to, $subject, $message );
		do_action( 'psb_lw_after_notification', $user );
	}
}
