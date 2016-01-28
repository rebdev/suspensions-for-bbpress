<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) 
	exit;


/*
 * Some globals for use in the functions in this file
 */

$path = plugin_dir_path( __FILE__ );


global 	$wpdb,
		$table_name, 					// Table name for plugin data.
		$installed_ver;					// Database version already installed by previous versions of this plugin.
		$rabbp_suspension_db_version; 	// New (if any) db version # option. Lets us work out if the db 
										//  is to be altered or left alone on plugin updates.
$table_name = $wpdb->prefix . "suspensions"; 
$installed_ver = get_option( 'rabbp_suspension_db_version' );
$rabbp_suspension_db_version = '1.7';



/**
 * Checks database is up-to-date and schedules the wp-cron event on plugin re/activation
 */
function sfbbp_do_on_activation() {

	// Check/do database updating
	sfbbp_set_up_database_table();

	// If the suspension reactivation hook is not scheduled to run daily already (unsuspending any
	//  users whose suspensions have expired), schedule it.
	if( !wp_next_scheduled( 'sfbbp_deactivate_expired_suspensions_hook' ) ) {
		wp_schedule_event( time(), 'daily', 'sfbbp_deactivate_expired_suspensions_hook' );
	}

}
register_activation_hook( __FILE__ , 'sfbbp_do_on_activation');



/**
 * Pauses the wp-cron job when plugin is deactivated
 */
function sfbbp_do_on_deactivation() {

	// Deschedule the suspension activator
	if( false !== ( $time = wp_next_scheduled( 'sfbbp_deactivate_expired_suspensions_hook' ) ) ) {
   		wp_unschedule_event( $time, 'sfbbp_deactivate_expired_suspensions_hook' );
	}

}
register_deactivation_hook( __FILE__, 'sfbbp_do_on_deactivation');





/**
 * Sets up database. Used by sfbbp_do_on_activation when plugin is activated, and also runs whenever
 *  plugin is loaded, the latter to check table is up-to-date in case of changes in plugin updates.
 */
function sfbbp_set_up_database_table() {

	global 	$wpdb,
			$rabbp_suspension_db_version,
			$table_name,
			$installed_ver;

	if( ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) 
	|| ( $installed_ver !== $rabbp_suspension_db_version ) ) {

	   	// Get the character set and collation for the database so we can set the table to the same. 
	   	//  Setting the default character set and collation for the table means we won't get 
	   	//  characters being converted to just ?'s when saved in our table.
	   	$charset_collate = $wpdb->get_charset_collate();

	   	// Build and run the query that creates the table
		$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				name tinytext NOT NULL,	
				user_id mediumint(9) NOT NULL UNIQUE,		
				time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				suspended_until datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,	
				ordinary_bbp_roles text NOT NULL,		
				reason text NOT NULL,
				status varchar(30) DEFAULT 'active' NOT NULL,			
				length_of_suspension_in_days mediumint(9),
				created_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				last_modified DATETIME DEFAULT 0,
				UNIQUE KEY id (id)
		) $charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		// Record this plugin's database version in the wp_options table.
		update_option( 'rabbp_suspension_db_version', $rabbp_suspension_db_version );

		// TODO: Test this trigger bit which I'm not sure is doing anything.
		// Create a trigger associated with this table to update createDate upon row insertion and lastModified whenever updated.
		$trigger_name = $table_name . "_create_time_trigger";
		$trigger_sql = "CREATE TRIGGER $trigger_name 
			BEFORE UPDATE ON $table_name
			FOR EACH ROW BEGIN
				SET NEW.last_modified = now();
			END
		";
		$wpdb->query( $trigger_sql );
	}
}
add_action( 'plugins_loaded', 'sfbbp_set_up_database_table' );




?>
