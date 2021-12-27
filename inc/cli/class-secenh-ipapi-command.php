<?php

namespace Myrotvorets\WordPress\SecEnh;

use WP_CLI;
use WP_CLI\Formatter;

class SecEnh_IPAPI_Command {
	public static function register(): void {
		WP_CLI::add_command(
			'secenh ipapi',
			__CLASS__,
			[
				'shortdesc' => 'ip-api.com geolocation.',
				'synopsis'  => [
					[
						'type'        => 'positional',
						'name'        => 'ip',
						'description' => 'IP address to geolocate.',
						'repeating'   => true,
						'required'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'force',
						'description' => 'Ignore cached responses.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'format',
						'description' => 'Render output in a particular format.',
						'options'     => [ 'table', 'csv', 'yaml', 'json' ],
						'default'     => 'table',
					],
					[
						'type'        => 'assoc',
						'name'        => 'fields',
						'description' => 'Limit the output to specific object fields.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'field',
						'description' => 'Prints the value of a single field for each IP.',
						'optional'    => true,
					],
				],
			]
		);
	}

	/**
	 * @param string[] $args
	 * @param mixed[] $assoc
	 * @return void 
	 */
	public function __invoke( array $args, array $assoc ): void {
		$force = ! empty( $assoc['force'] );
		if ( $force && wp_using_ext_object_cache() ) {
			foreach ( $args as $ip ) {
				wp_cache_delete( IP_API::get_cache_key( $ip ), IP_API::CACHE_GROUP );
			}
		}

		$response  = IP_API::batch_geolocate( $args );
		$formatter = new Formatter( $assoc, [ 'ip', 'country', 'region', 'city', 'isp', 'org', 'mobile', 'proxy', 'hosting' ] );
		$formatter->display_items( $response );
		WP_CLI::line( '' );
		WP_CLI::success( 'Done' );
	}
}
