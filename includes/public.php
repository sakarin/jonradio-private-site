<?php
/*
	Initiated when on the "public" web site,
	i.e. - not an Admin panel.
*/

//	Exit if .php file accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

add_action( 'login_init', 'jr_ps_login' );
add_action( 'wp', 'jr_ps_force_login' );

/**
 * Login Detection
 * 
 * Set a global variable, $jr_ps_is_login, whenever a login occurs 
 *
 * @return   NULL                Nothing is returned
 */
function jr_ps_login() {
	global $jr_ps_is_login;
	$jr_ps_is_login = TRUE;
}

/**
 * Present a login screen to anyone not logged in
 * 
 * Check for already logged in or just logged in.
 * Only called when is_admin() is FALSE
 *
 * @return   NULL                Nothing is returned
 */
function jr_ps_force_login() {
	global $jr_ps_is_login;
	if ( is_user_logged_in() || isset( $jr_ps_is_login ) ) {
		return;
	}
	
	$settings = get_option( 'jr_ps_settings' );
	/*	URL of current page without http://, i.e. - starting with domain
	*/
	$current_url = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
	if ( $settings['excl_home'] && jr_v1_same_url( get_home_url(), $current_url ) ) {
		return;
	}
	if ( $settings['custom_login'] && !empty( $settings['login_url'] ) && jr_v1_same_url( $settings['login_url'], $current_url ) ) {
		return;
	}
	if ( isset( $settings['excl_url'] ) ) {
		foreach ( $settings['excl_url'] as $arr ) {
			/*	Test the pre-parsed URL in the URL Exclusion list
			*/
			if ( jr_v1_same_url( $arr[1], $current_url ) ) {
				return;
			}
		}
	}
	
	if ( $settings['reveal_registration'] ) {
		$buddypress_path = 'buddypress/bp-loader.php';
		$buddypress_active = is_plugin_active( $buddypress_path );
		/*	URL of Registration Page varies between Multisite (Network)
			and Single Site WordPress.
			Plus, wp_registration_url function was introduced in
			WordPress Version 3.6.
		*/
		if ( is_multisite() ) {
			$reg_url = get_site_url( 0, 'wp-signup.php' );
			$buddypress_active = $buddypress_active || is_plugin_active_for_network( $buddypress_path );
		} else {
			if ( function_exists( 'wp_registration_url' ) ) {
				$reg_url = wp_registration_url();
			} else {
				$reg_url = get_site_url( 0, 'wp-login.php?action=register' );
			}
		}
		if ( jr_v1_same_url( $reg_url, $current_url )
			|| ( $buddypress_active 
				&& ( jr_v1_same_url( get_site_url( 0, 'register' ), $current_url )
					|| jr_v1_same_url( get_site_url( 0, 'activate' ),
						parse_url( $current_url, PHP_URL_HOST )
						. parse_url( $current_url, PHP_URL_PATH ) ) ) ) ) {
			/*	BuddyPress plugin redirects Registration URL to
				either {current site}/register/ or {main site}/register/
				and has its own Activation at /activate/?key=...
			*/
			return;
		}
	}
	
	/*	Must exclude all of the pages generated by the Theme My Login plugin
	*/
	$theme_my_login_path = 'theme-my-login/theme-my-login.php';
	$theme_my_login_active = is_plugin_active( $theme_my_login_path );
	if ( is_multisite() ) {
		$theme_my_login_active = $theme_my_login_active || is_plugin_active_for_network( $theme_my_login_path );
	}
	if ( $theme_my_login_active ) {
		if ( NULL !== ( $page = get_post( $null = NULL ) ) ) {
			/*	Some Versions of WordPress required that get_post() have a parameter
			*/
			if ( ( 'page' === $page->post_type )
				&& in_array( $page->post_name, array( 'login', 'logout', 'lostpassword', 'register', 'resetpass' ) )
				&& stripos( $page->post_content, 'theme-my-login' ) ) {
				return;
			}
		}
	}
	
	switch ( $settings['landing'] ) {
		case 'return':
			//	$_SERVER['HTTPS'] can be off in IIS
			if ( empty( $_SERVER['HTTPS'] ) || ( $_SERVER['HTTPS'] == 'off' ) ) {
				$http = 'http://';
			} else {
				$http = 'https://';
			}
			$after_login_url = $http . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
			break;
		case 'home':
			$after_login_url = get_home_url();
			break;
		case 'admin':
			$after_login_url = get_admin_url();
			break;
		case 'url':
			$after_login_url = trim( $settings['specific_url'] );
			break;
		case 'omit':
			$after_login_url = '';
			break;
	}
	
	if ( $settings['custom_login'] && !empty( $settings['login_url'] ) ) {
		if ( empty( $after_login_url ) ) {
			$url = $settings['login_url'];
		} else {
			$url = add_query_arg( 'redirect_to', $after_login_url, $settings['login_url'] );
		}
	} else {
		/*	Avoid situations where specific URL is requested, 
			but URL is blank.
		*/
		if ( empty( $after_login_url ) ) {
			$url = wp_login_url();
		} else {
			$url = wp_login_url( $after_login_url );
		}
	}

	/*	Next line:
		wp_redirect( $url ) goes to $url right after exit on the line that follows;
		wp_login_url() returns the standard WordPress login URL;
		$after_login_url is the URL passed to the standard WordPress login URL,
		via the ?redirect_to= URL query parameter, to go to after login is complete.
	*/
	wp_redirect( $url );
	exit;
}

?>