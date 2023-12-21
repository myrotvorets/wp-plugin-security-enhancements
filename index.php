<?php
/*
 * Plugin Name: Security Enhancements
 * Plugin URI: https://myrotvorets.center/
 * Description: Collection of various security enhancements
 * Version: 1.0.0
 * Author: Myrotvorets
 * Author URI: https://myrotvorets.center/
 * License: MIT
 */

use Myrotvorets\WordPress\SecEnh\Banhammer;
use Myrotvorets\WordPress\SecEnh\Plugin;
use Myrotvorets\WordPress\SecEnh\SecEnh_Command;

if ( defined( 'ABSPATH' ) ) {
	if ( defined( 'VENDOR_PATH' ) ) {
		/** @psalm-suppress UnresolvableInclude, MixedOperand */
		require_once constant( 'VENDOR_PATH' ) . '/vendor/autoload.php'; // NOSONAR
	} elseif ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';
	} elseif ( file_exists( ABSPATH . 'vendor/autoload.php' ) ) {
		require_once ABSPATH . 'vendor/autoload.php';
	}

	Banhammer::instance();
	Plugin::instance();
}

if ( defined( 'WP_CLI' ) && true === constant( 'WP_CLI' ) ) {
	SecEnh_Command::register();
}
