<?php

namespace Myrotvorets\WordPress\SecEnh;

use WP_CLI_Command;
use WP_Object_Cache;
use wpdb;

class CLI_Command_Base extends WP_CLI_Command {
	/**
	 * @global wpdb $wpdb
	 * @global WP_Object_Cache|null $wp_object_cache
	 */
	protected static function stop_the_insanity(): void {
		global $wp_object_cache;
		global $wpdb;

		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops      = [];
			$wp_object_cache->memcache_debug = [];
			$wp_object_cache->cache          = [];
		}

		$wpdb->queries = [];
	}

	protected function start_bulk_operation(): void {
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
	}

	protected function end_bulk_operation(): void {
		wp_defer_comment_counting( false );
		wp_defer_term_counting( false );
	}
}
