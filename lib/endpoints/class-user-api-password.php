<?php

class USER_API_Password {
	/**
	 * Register the Password route
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$password_route = array(
			// Password endpoints
			constant('USER_API_INTERNAL_PREFIX') . '/password/reset' => array(
				array(array($this, 'password_reset'), WP_JSON_Server::CREATABLE | WP_JSON_Server::HIDDEN_ENDPOINT )
			),
			constant('USER_API_INTERNAL_PREFIX') . '/password/change' => array(
				array(array($this, 'password_change'), WP_JSON_Server::CREATABLE | WP_JSON_Server::HIDDEN_ENDPOINT )
			),
		);

		return array_merge( $routes, $password_route );
	}


	/**
	 * Route to reset a password
	 *
	 * @param int $email The user for which the password should be resetted
	 * @param string $action The action the nonce should be created for
	 * @return String nonce
	 */
	public function password_reset( $user_email ) {
		global $wpdb, $wp_hasher;
		
		// Get the user from the user_email
		$user = get_user_by( 'email', $user_email );
		if (!$user) {
			return new WP_Error( 'USER_api_user_not_exists', __( 'This user does not exist.' ), array( 'status' => 400 ) );
		}

		$response_mail = 'hello@mima.io';

		// Create a key for resetting the password
		do_action('retrieve_password', $user->user_login);
		$key = wp_generate_password( 20, false );
		do_action( 'retrieve_password_key', $user->user_login, $key );

		// Hash the key and store it with the user inthe DB
		if ( empty( $wp_hasher ) ) {
			require_once ABSPATH . 'wp-includes/class-phpass.php';
			$wp_hasher = new PasswordHash( 8, true );
		}
		$hashed = $wp_hasher->HashPassword( $key );


		$query = $wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user->user_login ) );
		if (!$query) {
			return new WP_Error( 'USER_api_pasword_reset_failed', __( 'Could not reset the password.' ), array( 'status' => 500 ) );
		}

		// Create the email message
		$message = __('Someone requested that the password be reset for your account on ' . $blogname ) . "\r\n\r\n";
		$message .= sprintf(__('Username: %s'), $user->user_login) . "\r\n\r\n";
		$message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
		$message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
		$message .= network_site_url('wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login), 'login') . "\r\n";


		// Send the email
		$headers = array('From: ' . $response_mail,
			'Reply-To: ' . $response_mail,
			'X-Mailer: PHP/' . PHP_VERSION
		);
		$headers = implode('\r\n', $headers);

		if ( is_multisite() ) {
			$blogname = $GLOBALS['current_site']->site_name;
		} else {
			$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		}

		$title = sprintf( __('[%s] Password Reset'), $blogname );
		$title = apply_filters('retrieve_password_title', $title);

		if ( !wp_mail( $user->user_email, $title, $message, $headers ) ) {
			return new WP_Error( 'USER_api_pasword_reset_failed', __( 'Error while trying to send the password reset mail.' ), array( 'status' => 500 ) );		
		} else {
			return array(
				'success' => true
			);	
		}
	}
}