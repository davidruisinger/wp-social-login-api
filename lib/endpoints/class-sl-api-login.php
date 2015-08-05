<?php

class SL_API_Login {
	/**
	 * Register the Login route
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$login_route = array(
			// Login endpoint
			constant('SL_API_INTERNAL_PREFIX') . '/login' => array(
				array(array($this, 'login'), WP_JSON_Server::CREATABLE | WP_JSON_Server::HIDDEN_ENDPOINT )
			),
		);

		return array_merge( $routes, $login_route );
	}



	/**
	 * Router handling to log user in using Social Account
	 *
	 * @param string $social_type The name of the social network
	 * @param string $access_token The access token for the user's social account
	 * @param int $social_id User ID of the user's social account
	 * @return WP User ID if successfull otherwise error
	 */
	public function login( $social_type, $access_token, $social_id ) {
		switch ( $social_type ) {
			case "weibo":
				// Get the user info from Weibo
				$social_user = $this->weibo_get_user( $access_token, $social_id );
				// Parse the user info from Weibo into a wordpress user
				if ($social_user['id']) {
					apply_filters( 'sl_api/user_data', $user = array(
						'user_login' => $social_user['id'], // Shoudl be changed to email
						// 'user_email' => $social_user['screen_name'], // Add email here
						'display_name' => $social_user['screen_name'],
						'user_nicename' => $social_user['screen_name'],
						'nickname' => $social_user['screen_name'],						
					));
					
					// Login or register the user
					return $this->login_or_register( $user, $social_type, $social_id );

				
				// No Weibo user was found, return error
				} else {
					return new WP_Error( 'sl_api_social_no_user', __( 'Couldn\'t get a user with provided access_token and UID' ), array( 'status' => 400 ) );
				}
				break;
			
			// No social account matched, return error
			default:
				return new WP_Error( 'sl_api_social_type_not_supported', __( 'This type of social account is currently not supported.' ), array( 'status' => 400 ) );
		}
	}


	/**
	 * Get the user info from the Weibo API
	 *
	 * @param string $access_token The access token for the user's weibo account
	 * @param string $social_id User ID of the user's weibo account
	 * @return stdClass User information from weibo or error object
	 */
	private function weibo_get_user( $access_token, $social_id ) {
		$url = 'https://api.weibo.com/2/users/show.json?access_token=' . $access_token . '&uid=' . $social_id;

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

		return json_decode($result, true);
	}


	/**
	 * Handle Login/Registration for the social account
	 *
	 * @param stdClass $user The user object that needs to be registered or logged in
	 * @param string $social_type The name of the social network
	 * @param string $social_id User ID of the user's social account
	 * @return WP User ID if successfull otherwise error
	 */
	private function login_or_register( $user, $social_type, $social_id ) {
		$social_id_meta_key = '_' . $social_type .'_id';

		// Check if this user already exists in the database based on the user_login (should be email)
		$user_obj = get_user_by( 'login', $user['user_login'] );
		if ( $user_obj ){
			// Add/Update the social ID for this social account
			if ( !get_user_meta( $user_obj->ID, $social_id_meta_key, true ) == $social_id ) {
				update_user_meta( $user_obj->ID, $social_id_meta_key, $social_id );
			};

			return $user_obj->ID;
		
		// The user is not here, so let's create one
		} else {
			$user_id = $this->register_user( $user );
			if( !is_wp_error($user_id) ) {
				// Add the Social ID to the user_meta
				update_user_meta( $user_id, $social_id_meta_key, $social_id );
				return $user_id;
			} else {
				return null;
			}
		}
	}


	/**
	 * Register new user
	 * @param $user Array of user values captured via the social account
	 *
	 * @return int user id
	 */
	private function register_user( $user ) {
		return wp_insert_user( $user );
	}
}