<?php
/**
 * Plugin Name:         Ultimate Member - Expire User Roles
 * Description:         Extension to Ultimate Member for User Roles Expiration based on an updated version of the <a href="https://github.com/MissVeronica/expire-users-um" target="_blank">Expire Users</a> plugin.
 * Version:             2.0.0 BETA
 * Requires PHP:        7.4
 * PHP version tested   8.5.7
 * Author:              Miss Veronica
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:          https://github.com/MissVeronica
 * Plugin URI:          https://github.com/MissVeronica/um-expire-user-roles
 * Update URI:          https://github.com/MissVeronica/um-expire-user-roles
 * Text Domain:         expire-users
 * Domain Path:         /languages
 * UM version:          2.12.1
 *
 */

/*  Original Plugin Code
Plugin Name: Expire Users
Plugin URI: http://wordpress.org/extend/plugins/expire-users/
Description: Set expiry dates for user logins.
Version: 1.2.2
Author: Ben Huson
Author URI: https://github.com/benhuson/expire-users
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.4
Requires PHP: 7.4
Tested up to: 6.8.2
Text Domain: expire-users
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;

// Version
//define( 'EXPIRE_USERS_VERSION', '1.2.2' );
define( 'EXPIRE_USERS_DB_VERSION', '1' );
define( 'Plugin_Path_EUR', plugin_dir_path( __FILE__ ) );
define( 'Basename_EUR', plugin_basename(__FILE__));

// Includes
require_once( dirname( __FILE__ ) . '/includes/expire-users.php' );
require_once( dirname( __FILE__ ) . '/includes/expire-user.php' );
require_once( dirname( __FILE__ ) . '/includes/query.php' );
require_once( dirname( __FILE__ ) . '/includes/settings.php' );
require_once( dirname( __FILE__ ) . '/includes/cron.php' );
require_once( dirname( __FILE__ ) . '/includes/shortcodes.php' );
require_once( dirname( __FILE__ ) . '/admin/plugin.php' );
require_once( dirname( __FILE__ ) . '/admin/settings.php' );
require_once( dirname( __FILE__ ) . '/admin/expire-user.php' );
require_once( dirname( __FILE__ ) . '/admin/notifications.php' );
require_once( dirname( __FILE__ ) . '/admin/help.php' );

// Ultimate Member integration with roles
require_once( dirname( __FILE__ ) . '/includes/um-expire-user-roles.php' );

// I18n
function expire_users_load_plugin_textdomain() {
	load_plugin_textdomain( 'expire-users', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'expire_users_load_plugin_textdomain' );

global $expire_users;
$expire_users = new Expire_Users();

// Clear cron on deactivate
function expire_users_deactivate() {
	if ( wp_next_scheduled( 'expire_user_cron' ) ) {
		wp_clear_scheduled_hook( 'expire_user_cron' );
	}
	UM()->options()->remove( 'expire_users_cron_um_version' );
}
register_deactivation_hook( __FILE__, 'expire_users_deactivate' );

function expire_users_activation() {
	if ( ! wp_next_scheduled( 'expire_user_cron' ) ) {
		$date = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ));
		wp_schedule_event( $date->getTimestamp() + HOUR_IN_SECONDS, 'hourly', 'expire_user_cron' );
	} 
}
register_activation_hook( __FILE__, 'expire_users_activation' );

