<?php

	/**
	 * Check if an authentication cookie is valid
	 *
	 * @param string $cookie the authentication cookie
	 * @param int $user_id The user ID the cookie should be for
	 * @return boolean wether the cookie is valid
	 */
	function validate_auth_cookie( $user_id, $cookie ) {
		if ( wp_validate_auth_cookie( $cookie, 'logged_in' ) ==  $user_id ) {
			return true;
		} else {
			return false;
		}
	}
 ?>