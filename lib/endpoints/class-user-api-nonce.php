<?php

class USER_API_Nonce {
	/**
	 * Register the Nonce route
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$nonce_route = array(
			// Nonce endpoint
			constant('USER_API_INTERNAL_PREFIX') . '/nonce' => array(
				array(array($this, 'get_nonce'), WP_JSON_Server::READABLE | WP_JSON_Server::HIDDEN_ENDPOINT )
			),
		);

		return array_merge( $routes, $nonce_route );
	}



	/**
	 * Route which returnes a nonce
	 *
	 * @param int $email The id of the user the nonce should be created for
	 * @param string $action The action the nonce should be created for
	 * @return String nonce
	 */
	public function get_nonce( $email, $action ) {
		$nonce = wp_create_nonce( $action . '_' . $email );

		return array(
			'email' => $email,
			'action' => $action,
			'nonce' => $nonce
		);
	}
}