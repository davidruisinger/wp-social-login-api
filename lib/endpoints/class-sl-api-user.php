<?php

class SL_API_User {
	/**
	 * Register the User route
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$user_route = array(
			// User endpoint
			constant('SL_API_INTERNAL_PREFIX') . '/user/(?P<id>\d+)' => array(
				array( array( $this, 'get_user'), WP_JSON_Server::READABLE ),
			),
		);

		return array_merge( $routes, $user_route );
	}



	/**
	 * Retrieve a user.
	 *
	 * @param int $id user ID
	 * @return array user entity
	 */
	public function get_user( $id, $social_type, $access_token, $social_id ) {
		$social_id_meta_key = '_' . $social_type .'_id';

		// Identify which social account the user is using
		switch ( $social_type ) {
			case "weibo":
				// Check if the access token the user provided is still valid
				if ( $this->weibo_validate_token( $access_token == $social_id ) ) {
					$user_objs = get_users(array(
						'meta_key' => $social_id_meta_key,
						'meta_value' => $social_id,
						'number' => 1,
					));
					if ($user_objs) {
						// We only need the first user because we are sure that there's only one matching
						$user = $user_objs[0]->data;

						// Get the meta data for the user
						$user->meta = array_map( function( $a ){ return $a[0]; }, get_user_meta( $user->ID ) );

						// Convert strings to numbers where necessary
						if ($user->ID != '') $user->ID = (int)$user->ID;
						if ($user->meta['billing_postcode'] != '') $user->meta['billing_postcode'] = (int)$user->meta['billing_postcode'];
						if ($user->meta['shipping_postcode'] != '') $user->meta['shipping_postcode'] = (int)$user->meta['shipping_postcode'];

						return $user;
					} else {
						return new WP_Error( 'sl_api_social_account_not_found', __( 'There\'s no aaccount matching the provided ID' ), array( 'status' => 400 ) );
					}


				} else {
					return new WP_Error( 'sl_api_social_access_token_invalid', __( 'The access_token is invalid or doesn\'t match the provided ID' ), array( 'status' => 400 ) );
				}


				break;
			
			// No social account matched, return error
			default:
				return new WP_Error( 'sl_api_social_type_not_supported', __( 'This type of social account is currently not supported.' ), array( 'status' => 400 ) );
		}

	}


	/**
	 * Check if the provided weibo access_token is valid for the weibo user_id
	 *
	 * @param string $access_token The access token for the user's social account
	 * @param int $social_id User ID of the user's social account
	 * @return boolean indication validation
	 */
	private function weibo_validate_token( $access_token ) {
		$url = 'https://api.weibo.com/2/account/get_uid.json?access_token=' . $access_token;

		//  Initiate curl
		$ch = curl_init();
		// Will return the response, if false it print the response
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// Set the url
		curl_setopt($ch, CURLOPT_URL, $url);
		// Execute
		$result=curl_exec($ch);
		// Closing
		curl_close($ch);

		$result = json_decode($result, true);

		return $result['uid'] == $social_id;
	}



}