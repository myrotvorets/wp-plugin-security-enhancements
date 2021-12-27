<?php

namespace Myrotvorets\WordPress\SecEnh;

use WildWolf\Utils\Singleton;

final class Content {
	use Singleton;

	private function __construct() {
		$this->init();
	}

	public function init(): void {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );

		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );

		add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
	}
}
