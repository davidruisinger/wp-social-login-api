<?php

class USER_API_User {
	/**
	 * Register the User route
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$user_route = array(
			// User endpoint
			constant('USER_API_INTERNAL_PREFIX') . '/user/(?P<id>\d+)' => array(
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
	 * @param string $auth_cookie a valid auth_cookie for that user
	 * @return array user entity
	 */
	public function get_user( $id, $auth_cookie ) {
		if ( !validate_auth_cookie( $id, $auth_cookie ) ) {
			return new WP_Error( 'USER_api_invalid_auth_cookie', __( 'The provided auth_cokie is invalid for that user.' ), array( 'status' => 400 ) );
		}

		// Get the user from the WP database
		$user_obj = get_user_by( 'id', $id );
		if ($user_obj) {
			// Get the user data
			$user = $user_obj->data;

			// Get the meta data for the user
			$user->meta = array_map( function( $a ){ return $a[0]; }, get_user_meta( $user->ID ) );

			// Return a formatted user
			$formatted_user = $this->get_formatted_user( $user );
			return $formatted_user;
		} else {
			return new WP_Error( 'USER_api_internal_error', __( 'Could not get the user data.' ), array( 'status' => 500 ) );	
		}
	}


	/**
	 * Update a user.
	 *
	 * @param int $id user ID
	 * @param string $auth_cookie a valid auth_cookie for that user
	 * @param array $data valid user data
	 * @return array user entity
	 */
	public function update_user( $id, $auth_cookie, $data ) {
		if ( !validate_auth_cookie( $id, $auth_cookie ) ) {
			return new WP_Error( 'USER_api_invalid_auth_cookie', __( 'The provided auth_cokie is invalid for that user.' ), array( 'status' => 400 ) );
		}

		// Get the user from the WP database
		$user_obj = get_user_by( 'id', $id );
		if ($user_obj) {
			// Get the user data
			$user = $user_obj->data;

			// Separate the meta data from the provided data
			$user_meta = $data['meta'];
			// Remove session_tokens from the meta
			unset($user_meta['session_tokens']);
			// And make an object with only the basic user info
			$user_info = $data;
			unset($user_info['meta']);
			// Remove the password because we won't update it at all
			unset($user_info['user_pass']);

			// Check if the email already exists and if yes, if it belongs to another user
			$user_with_same_email = get_user_by( 'email', $user_info['user_email'] );
			if ( $user_with_same_email && $user_with_same_email->ID != intval($id) ) {
				return new WP_Error( 'USER_api_user_email_exists', __( 'This email is already taken by another user.' ), array( 'status' => 400 ) );
			}

			// Update the basic user info
			$user_id = wp_update_user( $user_info );
			if ( is_wp_error( $user_id ) ) {
				// There was an error, probably that user doesn't exist.
				return new WP_Error( 'USER_api_internal_error', __( 'Could not update the user data.' ), array( 'status' => 500 ) );	
			}

			// Check if a new profile picture is passed String is NOT an URL and it is NOT empty
			if (!$this->validateURL( $user_meta[mima_profile_picture] ) && $user_meta[mima_profile_picture] != '') {
				
				// Get the upload directory
				$upload_dir = wp_upload_dir();

				// Check if the directory for the images is already present and create it if not
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
				update_user_meta($user_id, $key, $val);
			}

			// Get the updated user info
			$updatedUserData = get_userdata( $user_id )->data;

			// Get the meta data for the user
			$updatedUserData->meta = array_map( function( $a ){ return $a[0]; }, get_user_meta( $user_id ) );

			// Return a formatted user
			$formatted_user = $this->get_formatted_user($updatedUserData);
			return $updatedUserData;
		} else {
			return new WP_Error( 'USER_api_internal_error', __( 'Could not get the user data.' ), array( 'status' => 500 ) );	
		}
	}


	/**
	 * Helper function to convert a user object to the needed format
	 *
	 * @param int $user the user object
	 * @return array with the formatted user object
	 */
	private function get_formatted_user( $user ) {
		$formatted_user = $user;

		// Convert strings to numbers where necessary
		if ($formatted_user->ID != '') $formatted_user->ID = (int)$formatted_user->ID;
		if ($formatted_user->meta['billing_postcode'] != '') $formatted_user->meta['billing_postcode'] = (int)$formatted_user->meta['billing_postcode'];
		if ($formatted_user->meta['shipping_postcode'] != '') $formatted_user->meta['shipping_postcode'] = (int)$formatted_user->meta['shipping_postcode'];

		// Remove the 
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