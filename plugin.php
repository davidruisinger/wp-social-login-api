<?php
/**
* Plugin Name: Social Login API
* Description: JSON-based REST API for register/login users via Social Accounts.
* Version: 1.0.0
* Author: David Ruisinger
* Author URI: flavordaaave.com
*/

define ( 'SL_API_NAME' , 'Social Login API' );
define ( 'SL_API_DESCRIPTION' , 'JSON-based REST API for register/login users via Social Accounts.' );
define ( 'SL_API_VERSION' , '1.0.0' );
define ( 'SL_API_BASE' , 'sl-api' );
define ( 'SL_API_INTERNAL_PREFIX' , '/sl-api-plugin' );

/**
 * Include our files for the API.
 */

include_once( dirname( __FILE__ ) . '/lib/endpoints/class-sl-api-login.php' );
include_once( dirname( __FILE__ ) . '/lib/endpoints/class-sl-api-user.php' );


/**
 * Register rewrite rules for the API
 */
function sl_api_init() {
	sl_api_register_rewrites();
	
	//Integrate with WooCommerce settings if dependencies are checked
	sl_api_check_dependencies();
}
add_action( 'init', 'sl_api_init' );


/**
 * Add rewrite rules.
 */
function sl_api_register_rewrites() {
	add_rewrite_rule( '^' . constant('SL_API_BASE') . '/?$','index.php?json_route=' . constant('SL_API_INTERNAL_PREFIX'),'top' );
	add_rewrite_rule( '^' . constant('SL_API_BASE') . '(.*)?','index.php?json_route=' . constant('SL_API_INTERNAL_PREFIX') . '$matches[1]','top' );
}


/**
 * Set the rewrites upon activation
 */
function sl_api_activate() {
	sl_api_register_rewrites();
	flush_rewrite_rules();
}


/**
 * Flush the rewrites upon deactivation
 */
function sl_api_deactivate() {
	flush_rewrite_rules();
}


/**
 * Register activation/deactivation functions
 */
register_activation_hook(__FILE__, 'sl_api_activate');
register_deactivation_hook(__FILE__, 'sl_api_deactivate');


/**
 * Register the endpoints for the WP API REST Plugin
 */
function sl_api_endpoints( $server ) {
	// Login(/Register)
	$sl_api_login = new SL_API_Login();
	add_filter( 'json_endpoints', array( $sl_api_login, 'register_routes' ), 0 );

	// User
	$sl_api_user = new SL_API_User();
	add_filter( 'json_endpoints', array( $sl_api_user, 'register_routes' ), 0 );

}
add_action( 'wp_json_server_before_serve', 'sl_api_endpoints' );


/**
 * Check if WP REST API Plugin is active
 */
function sl_api_check_dependencies() {
	if ( ! class_exists( 'WP_JSON_Posts' ) ) {
		function sl_api_wp_json_dependency_error_notice() {
			$class = "error";
			$message = "You need to have the WP REST API Plugin activated in order to use the " . constant('SL_API_NAME') . " Plugin.";
			
			echo"<div class=\"$class\"> <p>$message</p></div>"; 
		}
		add_action( 'admin_notices', 'sl_api_wp_json_dependency_error_notice' ); 
	}
}


// create an entry under the Settings Page
add_action( 'admin_menu', 'sl_api_create_menu' );

// Create the menu item
function sl_api_create_menu() {
	add_options_page( constant('SL_API_NAME'), constant('SL_API_NAME'), 'manage_options', __FILE__, 'sl_login_api_settings_page' );
	//call register settings function
	add_action( 'admin_init', 'register_sl_login_api_settings' );
}

function register_sl_login_api_settings() {
	//register the settings
	register_setting( 'sl-api-login-settings-group', 'weibo_appkey' );
}

// The settings page
function sl_login_api_settings_page() {
?>
<div class="wrap">
	<h2><?php echo constant('SL_API_NAME'); ?></h2>

	<form method="post" action="options.php">
		<?php settings_fields( 'sl-api-login-settings-group' ); ?>
		<?php do_settings_sections( 'sl-api-login-settings-group' ); ?>
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


