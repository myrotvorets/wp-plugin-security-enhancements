<?php

namespace Myrotvorets\WordPress\SecEnh;

use WildWolf\Utils\Singleton;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

final class REST {
	use Singleton;

	private function __construct() {
		$this->init();
	}

	public function init(): void {
		add_filter( 'rest_pre_dispatch', [ $this, 'rest_pre_dispatch' ], 10, 3 );
	}

	/**
	 * @param mixed $result
	 * @return WP_Error|mixed
	 */
	public function rest_pre_dispatch( $result, WP_REST_Server $_srv, WP_REST_Request $request ) {
		$method = $request->get_method();
		$path   = $request->get_route();

		if ( ( 'GET' === $method || 'HEAD' === $method ) && preg_match( '!^/wp/v2/users(?:$|/)!', $path ) && ! current_user_can( 'list_users' ) ) {
			$result = new WP_Error(
				'rest_user_cannot_view',
				'Sorry, you are not allowed to use this API.',
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return $result;
	}
}
