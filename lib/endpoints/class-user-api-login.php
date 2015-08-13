<?php

class USER_API_Login {
	/**
	 * Register the Login route
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$login_route = array(
			// Login endpoints
			constant('USER_API_INTERNAL_PREFIX') . '/social-login' => array(
				array(array($this, 'social_login'), WP_JSON_Server::CREATABLE | WP_JSON_Server::HIDDEN_ENDPOINT )
			),
			constant('USER_API_INTERNAL_PREFIX') . '/email-login' => array(
				array(array($this, 'email_login'), WP_JSON_Server::CREATABLE | WP_JSON_Server::HIDDEN_ENDPOINT )
			),
			constant('USER_API_INTERNAL_PREFIX') . '/email-register' => array(
				array(array($this, 'email_register'), WP_JSON_Server::CREATABLE | WP_JSON_Server::HIDDEN_ENDPOINT )
			),
		);

		return array_merge( $routes, $login_route );
	}


	/**
	 * Router handling registration using Email
	 *
	 * @param string $nonce Nonce for registration
	 * @param string $email The Email for the user
	 * @param string $password The user's password
	 * @return User object with a valid authentication cookie
	 */
	public function email_register( $nonce, $email, $password ) {
		// Validate the nonce
		if ( !wp_verify_nonce( $nonce, 'email-register' . '_' .  $email) ) {
			return new WP_Error( 'USER_api_nonce_invalid', __( 'This nonce is not valid.' ), array( 'status' => 400 ) );
		}

		// Check if this user already exists in the database based on the email
		$user_obj = get_user_by( 'email', $email );
		if ( $user_obj ) {
			return new WP_Error( 'USER_api_user_already_exists', __( 'This user already exists.' ), array( 'status' => 400 ) );
		}

		// Create the user object
		$user = array(
			'user_login' => sanitize_user($email),
			'user_email' => sanitize_user($email),
			'user_pass' => $password					
		);

		// Create the meta data for the user (add the email)
		$user_meta = array();

		// Register the user
		return $this->register( $user, $user_meta );
	}


	/**
	 * Router handling login using Email
	 *
	 * @param string $nonce Nonce for login
	 * @param string $email The Email for the user
	 * @param string $password The user's password
	 * @return User object with a valid authentication cookie
	 */
	public function email_login( $nonce, $email, $password ) {
		// Validate the nonce
		if ( !wp_verify_nonce( $nonce, 'email-login' . '_' .  $email) ) {
			return new WP_Error( 'USER_api_nonce_invalid', __( 'This nonce is not valid.' ), array( 'status' => 400 ) );
		}

		// Check if this user exists in the database based on the email
		$user_obj = get_user_by( 'email', $email );
		if ( !$user_obj ) {
			// The user is not here
			return new WP_Error( 'USER_api_user_not_exists', __( 'This user does not exist.' ), array( 'status' => 400 ) );
		}

		// Verify the password
		if ( wp_check_password( $password, $user_obj->data->user_pass, $user_obj->ID ) ) {
			// Log user in
			return $this->login( $user_obj->ID, array() );	
		} else {
			return new WP_Error( 'USER_api_wrong_password', __( 'Wrong password.' ), array( 'status' => 400 ) );
		}
	}


	/**
	 * Router handling registration/login using a supported Social Account
	 *
	 * @param string $provider The name of the social network
	 * @param string $access_token The access token for the user's social account
	 * @param int $social_id User ID of the user's social account
	 * @return WP User ID if successfull otherwise error
	 */
	public function social_login( $provider, $access_token, $social_id ) {
		// Define how the social ID meta key looks like in the WP database
		$social_id_meta_key = '_' . $provider .'_id';

		switch ( $provider ) {
			case "weibo":
				// Get the user info from Weibo
				$social_user = $this->weibo_get_user( $access_token, $social_id );
				// Parse the user info from Weibo into a wordpress user
				if ($social_user['id']) {
					// Create a user object out of the info from weibo
					$user = array(
						'user_login' => $social_user['id'] . '@weibo.com', // Should be changed to "real" email in order to avoid duplicates
						'display_name' => $social_user['screen_name'],
						'user_nicename' => $social_user['screen_name'],
						'nickname' => $social_user['screen_name']			
					);

					// Create the user's meta data with the social info
					$user_meta = array (
						$social_id_meta_key => $social_id
					);

					// Login or register the user
					return $this->login_or_register( $provider, $user, $user_meta );

				// No Weibo user was found, return error
				} else {
					return new WP_Error( 'USER_api_social_no_user', __( 'Couldn\'t get a user with provided access_token and UID' ), array( 'status' => 400 ) );
				}
				break;

			// No social account matched, return error
			default:
				return new WP_Error( 'USER_api_provider_not_supported', __( 'This type of social account is currently not supported.' ), array( 'status' => 400 ) );
		}
	}


	/**
	 * Handle Login for user accounts
	 *
	 * @param stdClass $user The user object that needs to be registered or logged in
	 * @param stdClass $user_meta The meta data for the user (should contain potentially available social data)
	 * @return String Valid WP authentication cookie
	 */
	private function login( $user_id, $user_meta ) {
		// Update the meta data to the user
		foreach($user_meta as $key => $val){
			update_user_meta($user_id, $key, $val);
		}

		// Return the authentication info
		return $this->generate_authentication( $user_id );
	}


	/**
	 * Handle Registration for user accounts
	 *
	 * @param stdClass $user The user object that needs to be registered or logged in
	 * @param stdClass $user_meta The meta data for the user (should contain potentially available social data)
	 * @return String Valid WP authentication cookie
	 */
	private function register( $user, $user_meta ) {
		// Create a new user
		$user_id = wp_insert_user( $user );
		if( is_wp_error($user_id) ) {
			return new WP_Error( 'USER_api_failed_create_user', __( 'Failed creating a new user.' ), array( 'status' => 500 ) );
		}

		// Add the meta data to the user
		foreach($user_meta as $key => $val){
			update_user_meta($user_id, $key, $val);
		}

		// Return the authentication info
		return $this->generate_authentication( $user_id );
	}


	/**
	 * Handle Login/Registration for user accounts (used for social account)
	 *
	 * @param stdClass $user The user object that needs to be registered or logged in
	 * @param stdClass $user_meta The meta data for the user (should contain potentially available social data)
	 * @return String Valid WP authentication cookie
	 */
	private function login_or_register( $provider, $user, $user_meta ) {
		// Define how the social ID meta key looks like in the WP database
		$social_id_meta_key = '_' . $provider .'_id';

		// Check if this user already exists in the database based on the social_id (meta field)
		$existing_user = get_users( array( 'meta_key' => $social_id_meta_key, 'meta_value' => $user_meta[$social_id_meta_key] ) );

		if ( $existing_user ) {
			// User already exists, log him in
			return $this->login( $user_obj->ID, $user_meta );
		} else {
			// Create a new user
			return $this->register( $user, $user_meta );
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
	 * Get an authentication cookie for a user
	 * @param int $user_id ID of the user
	 * @param array $user Array of the user info
	 *
	 * @return Array of info to authenticate the user
	 */
	private function generate_authentication( $user_id ) {
		$seconds = 1209600; // =14 days
		$expiration = time() + apply_filters('auth_cookie_expiration', $seconds, $user_id, true);

		// Generate an authentication cookie for the user
		$cookie = wp_generate_auth_cookie($user_id, $expiration, 'logged_in');

		// Return the authentication data
		$authenticaten_info = array(
			'ID' => $user_id,
			'auth_cookie' => $cookie
		);

		return $authenticaten_info;
	}
}