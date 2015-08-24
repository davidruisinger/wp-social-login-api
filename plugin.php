<?php
/**
* Plugin Name: User REST API
* Description: JSON-based REST API for register/login users via Email or Social Accounts.
* Version: 1.0.0
* Author: David Ruisinger
* Author URI: flavordaaave.com
*/

define ( 'USER_API_NAME' , 'User REST API' );
define ( 'USER_API_DESCRIPTION' , 'JSON-based REST API for register/login users via Social Accounts.' );
define ( 'USER_API_VERSION' , '1.0.0' );
define ( 'USER_API_BASE' , 'user-api' );
define ( 'USER_API_INTERNAL_PREFIX' , '/user-api-plugin' );

/**
 * Include our files for the API.
 */

include_once( dirname( __FILE__ ) . '/lib/validation.php' );
include_once( dirname( __FILE__ ) . '/lib/endpoints/class-user-api-login.php' );
include_once( dirname( __FILE__ ) . '/lib/endpoints/class-user-api-user.php' );
include_once( dirname( __FILE__ ) . '/lib/endpoints/class-user-api-password.php' );
include_once( dirname( __FILE__ ) . '/lib/endpoints/class-user-api-nonce.php' );


/**
 * Register rewrite rules for the API
 */
function USER_api_init() {
	USER_api_register_rewrites();
	
	//Integrate with WooCommerce settings if dependencies are checked
	USER_api_check_dependencies();
}
add_action( 'init', 'USER_api_init' );


/**
 * Add rewrite rules.
 */
function USER_api_register_rewrites() {
	add_rewrite_rule( '^' . constant('USER_API_BASE') . '/?$','index.php?json_route=' . constant('USER_API_INTERNAL_PREFIX'),'top' );
	add_rewrite_rule( '^' . constant('USER_API_BASE') . '(.*)?','index.php?json_route=' . constant('USER_API_INTERNAL_PREFIX') . '$matches[1]','top' );
}


/**
 * Set the rewrites upon activation
 */
function USER_api_activate() {
	USER_api_register_rewrites();
	flush_rewrite_rules();
}


/**
 * Flush the rewrites upon deactivation
 */
function USER_api_deactivate() {
	flush_rewrite_rules();
}


/**
 * Register activation/deactivation functions
 */
register_activation_hook(__FILE__, 'USER_api_activate');
register_deactivation_hook(__FILE__, 'USER_api_deactivate');


/**
 * Register the endpoints for the WP API REST Plugin
 */
function USER_api_endpoints( $server ) {
	// Login(/Register)
	$USER_api_login = new USER_API_Login();
	add_filter( 'json_endpoints', array( $USER_api_login, 'register_routes' ), 0 );

	// User
	$USER_api_user = new USER_API_User();
	add_filter( 'json_endpoints', array( $USER_api_user, 'register_routes' ), 0 );

	// Password
	$USER_api_password = new USER_API_Password();
	add_filter( 'json_endpoints', array( $USER_api_password, 'register_routes' ), 0 );

	// Nonce
	$USER_api_nonce = new USER_API_Nonce();
	add_filter( 'json_endpoints', array( $USER_api_nonce, 'register_routes' ), 0 );

}
add_action( 'wp_json_server_before_serve', 'USER_api_endpoints' );


/**
 * Check if WP REST API Plugin is active
 */
function USER_api_check_dependencies() {
	if ( ! class_exists( 'WP_JSON_Posts' ) ) {
		function USER_api_wp_json_dependency_error_notice() {
			$class = "error";
			$message = "You need to have the WP REST API Plugin activated in order to use the " . constant('USER_API_NAME') . " Plugin.";
			
			echo"<div class=\"$class\"> <p>$message</p></div>"; 
		}
		add_action( 'admin_notices', 'USER_api_wp_json_dependency_error_notice' ); 
	}
}


// create an entry under the Settings Page
add_action( 'admin_menu', 'USER_api_create_menu' );

// Create the menu item
function USER_api_create_menu() {
	add_options_page( constant('USER_API_NAME'), constant('USER_API_NAME'), 'manage_options', __FILE__, 'USER_login_api_settings_page' );
	//call register settings function
	add_action( 'admin_init', 'register_USER_login_api_settings' );
}

function register_USER_login_api_settings() {
	//register the settings
	register_setting( 'user-api-login-settings-group', 'weibo_appkey' );
}

// The settings page
function USER_login_api_settings_page() {
?>
<div class="wrap">
	<h2><?php echo constant('USER_API_NAME'); ?></h2>

	<form method="post" action="options.php">
		<?php settings_fields( 'user-api-login-settings-group' ); ?>
		<?php do_settings_sections( 'user-api-login-settings-group' ); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">Weibo AppKey</th>
				<td><input type="text" name="weibo_appkey" value="<?php echo esc_attr( get_option('weibo_appkey') ); ?>" /></td>
			</tr>
		</table>

		<?php submit_button(); ?>

	</form>
</div>
<?php }


