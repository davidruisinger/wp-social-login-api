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
				array( array( $this, 'get_user'), WP_JSON_Server::READABLE | WP_JSON_Server::HIDDEN_ENDPOINT ),
				array( array( $this, 'update_user'), WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON | WP_JSON_Server::HIDDEN_ENDPOINT )
			),
		);

		return array_merge( $routes, $user_route );
	}


	/**
	 * Retrieve a user.
	 *
	 * @param int $id user ID
	 * @param string $social_type the name of the social account
	 * @param string $access_token
	 * @param int $social_id the id of the user in that social network
	 * @return array user entity
	 */
	public function get_user( $id, $social_type, $access_token, $social_id ) {
		$social_id_meta_key = '_' . $social_type .'_id';

		// Identify which social account the user is using
		switch ( $social_type ) {
			case "weibo":
				// Check if the access token the user provided is still valid
				if ( $this->weibo_validate_token( $access_token == $social_id ) ) {
					// Get the user from the WP database
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

						// Return a formatted user
						$formatted_user = $this->get_format_user($user);
						return $formatted_user;
					} else {
						return new WP_Error( 'sl_api_social_account_not_found', __( 'There\'s no account matching the provided ID' ), array( 'status' => 400 ) );
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
	 * Update a user.
	 *
	 * @param int $id user ID
	 * @param string $social_type the name of the social account
	 * @param string $access_token
	 * @param int $social_id the id of the user in that social network
	 * @param array $data valid user data
	 * @return array user entity
	 */
	public function update_user( $id, $social_type, $access_token, $social_id , $data ) {
		$social_id_meta_key = '_' . $social_type .'_id';

		// Identify which social account the user is using
		switch ( $social_type ) {
			case "weibo":
				// Check if the access token the user provided is still valid
				if ( $this->weibo_validate_token( $access_token == $social_id ) ) {
					// Get the user from the WP database
					$user_objs = get_users(array(
						'meta_key' => $social_id_meta_key,
						'meta_value' => $social_id,
						'number' => 1,
					));
					if ($user_objs) {
						// We only need the first user because we are sure that there's only one matching
						$user = $user_objs[0]->data;

						// Separate the meta data from the provided data
						$user_meta = $data['meta'];
						// And make an object with only the basic user info
						$user_info = $data;
						unset($user_info['meta']);
						// Remove the password because we won't update it at all
						unset($user_info['user_pass']);

						// Update the basic user info
						$user_id = wp_update_user( $user_info );
						if ( is_wp_error( $user_id ) ) {
							// There was an error, probably that user doesn't exist.
							return new WP_Error( 'sl_api_social_account_not_found', __( 'There\'s no account matching the ID provied in the JSON' ), array( 'status' => 400 ) );
						}

						// Check if a new profile picture is passed String is NOT an URL and it is NOT empty
						if (!$this->validateURL( $user_meta[mima_profile_picture] ) && $user_meta[mima_profile_picture] != '') {
							
							// Get the upload directory
							$upload_dir = wp_upload_dir();

							// Check if the directory for the images is already prsent and create it if not
							if (wp_mkdir_p($upload_dir[basedir] . '/mima_images/profile_pics')) {
								// Remove the base64 declaration from the base64 image string (separated with a comma from the real data)
								$image_data = explode(',', $user_meta[mima_profile_picture])[1];

								// Generate a timestamp for the filename to prevent caching in the apps
								$timestamp = time();

								// Create the image from the base64 string and save it to the directory
								$image = base64_decode($image_data);
								file_put_contents($upload_dir[basedir] . '/mima_images/profile_pics/user_' . $user_id . '_' . $timestamp . '_' . '.jpeg', $image);

								// Add the new image URL to the meta data
								$user_meta[mima_profile_picture] = $upload_dir[baseurl] . '/mima_images/profile_pics/user_' . $user_id . '_' . $timestamp . '_' . '.jpeg';
							};
						}

						// Update the user's meta data
						foreach($user_meta as $key => $val){
							//array_push($test, $key);
							update_user_meta($user_id, $key, $val);
						}

						// Get the updated user info
						$updatedUserData = get_userdata($user_id)->data;

						// Get the meta data for the user
						$updatedUserData->meta = array_map( function( $a ){ return $a[0]; }, get_user_meta( $user_id ) );

						// Return a formatted user
						$formatted_user = $this->get_format_user($updatedUserData);
						return $updatedUserData;
					} else {
						return new WP_Error( 'sl_api_social_account_not_found', __( 'There\'s no account matching the provided ID' ), array( 'status' => 400 ) );
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


	/**
	 * Helper function to convert a user object to the needed format
	 *
	 * @param int $user the user object
	 * @return array with the formatted user object
	 */
	private function get_format_user( $user ) {
		$formatted_user = $user;

		// Convert strings to numbers where necessary
		if ($formatted_user->ID != '') $formatted_user->ID = (int)$formatted_user->ID;
		if ($formatted_user->meta['billing_postcode'] != '') $formatted_user->meta['billing_postcode'] = (int)$formatted_user->meta['billing_postcode'];
		if ($formatted_user->meta['shipping_postcode'] != '') $formatted_user->meta['shipping_postcode'] = (int)$formatted_user->meta['shipping_postcode'];

		return $formatted_user;
	}


	/**
	 * Helper function to check if a string is a valid URL
	 *
	 * @param string $url the string that should be an URL
	 * @return boolean true if the URL is valid
	 */
	private function validateURL( $url ) {
		$regex = "((https?|ftp)\:\/\/)?"; // SCHEME 
		$regex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?"; // User and Pass 
		$regex .= "([a-z0-9-.]*)\.([a-z]{2,3})"; // Host or IP 
		$regex .= "(\:[0-9]{2,5})?"; // Port 
		$regex .= "(\/([a-z0-9+\$_-]\.?)+)*\/?"; // Path 
		$regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?"; // GET Query 
		$regex .= "(#[a-z_.-][a-z0-9+\$_.-]*)?"; // Anchor 

		if(preg_match("/^$regex$/", $url)) { 
			return true; 
		} else {
			return false;
		}
	}

}