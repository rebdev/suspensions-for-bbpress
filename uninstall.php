<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) 
	exit;


/* TODO: Look into if this is defunct; was in plugin development book but causes white screen now at least! */
/* If uninstall not called from WordPress, exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) 
	exit(); 
*/




global 	$wpdb,
		$table_name; // Table name for plugin data

/**
 * Uninstall process
 *
 * Deletes the suspensions table when plugin is removed entirely
 * 
 * @param 	N/A
 * @return 	N/A
 */
function sfbbp_do_on_uninstall() {

	// Restore all user privileges
	sfbbp_deactivate_expired_suspensions();

	// Delete options from options table
	delete_option( 'default_suspend_time' );
	delete_option( 'suspension_message' );
	delete_option( 'rabbp_suspension_db_version' );

	// Drop table used to store suspension-related data
	global 	$wpdb,
			$table_name;
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$sql = "DROP TABLE $table_name";

	$wpdb->query($sql);
}
register_uninstall_hook( __FILE__, 'sfbbp_do_on_uninstall');
