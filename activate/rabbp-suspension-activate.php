<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) 
	exit;

/*
 * Suspensions For bbPress
 * sfbbp_
 *
 */

/*
 * Some globals for use in the functions in this file
 */

$path = plugin_dir_path( __FILE__ );


global $wpdb,
		$table_name, 					// Table name for plugin data.
		$installed_ver;					// Database version already installed by previous versions of this plugin.
		$rabbp_suspension_db_version; 	// New (if any) db version # option. Lets us work out if the db 
										//  is to be altered or left alone on plugin updates.
$table_name = $wpdb->prefix . "suspensions"; 
$installed_ver = get_option( 'rabbp_suspension_db_version' );
$rabbp_suspension_db_version = '1.7';



/**
 * Do teardown stuff on plugin removal
 */
register_uninstall_hook( __FILE__, 'rabbp_suspension_on_uninstall');
function rabbp_suspension_on_uninstall() {
	// Restore all user privileges
	rabbp_deactivate_expired_suspensions();

	// Drop table entirely
	global 	$wpdb,
			$table_name;

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$sql = "DROP TABLE $table_name";

	$wpdb->query($sql);
}



/**
 * Pauses the wp-cron job when plugin is deactivated
 */
function rabbp_suspension_on_deactivation() {
	// Deschedule the suspension activator.
	if( false !== ( $time = wp_next_scheduled( 'rabbp_deactivate_expired_suspensions_hook' ) ) ) {
   		wp_unschedule_event( $time, 'rabbp_deactivate_expired_suspensions_hook' );
	}
}
register_deactivation_hook( __FILE__, 'rabbp_suspension_on_deactivation');


/**
 * Sets up database
 */
function rabbp_suspension_setup_database_table() {

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
		$wpdb->query($trigger_sql);
	}
}
add_action( 'plugins_loaded', 'rabbp_suspension_setup_database_table' );


/**
 * Checks database is up-to-date and schedules the wp-cron event on plugin re/activation.
 */
function rabbp_suspension_on_activation() {

	// Check/do database updating
	rabbp_suspension_setup_database_table();

	// If the suspension reactivator is not scheduled, schedule it.
	if( !wp_next_scheduled( 'rabbp_deactivate_expired_suspensions_hook' ) ) {
		wp_schedule_event( time(), 'daily', 'rabbp_deactivate_expired_suspensions_hook' );
	}
}
register_activation_hook( __FILE__ , 'rabbp_suspension_on_activation');



/**
 * Adds nav items to admin menu.
 */
function rabbp_suspension_add_menu_items() {

	// Add top level menu page. 
	// Usage: add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
	add_menu_page( 'Suspensions', 'Suspensions', 'manage_options', 'suspensions', 'rabbp_suspension_render_list_page', '', 6.3 );

	// Add submenu items. 
	// Usage: add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	add_submenu_page( 'suspensions', 'Suspension', 'Add New', 'manage_options', 'suspension', 'rabbp_suspension_render_single_page' );

	// Add submenu items. 
	// Usage: add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	add_submenu_page( 'suspensions', 'Suspension Options', 'Options', 'manage_options', 'suspension-options', 'rabbp_suspension_options_page' );
}
if ( is_admin() ){
	add_action('admin_menu','rabbp_suspension_add_menu_items');
}



?>
