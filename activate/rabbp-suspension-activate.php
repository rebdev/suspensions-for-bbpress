<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly



/*
 * Some globals for use in the functions in this file.
 */

$path = plugin_dir_path( __FILE__ );

global $wpdb;

# Table name for plugin.
global $table_name;
$table_name = $wpdb->prefix . "suspensions"; 

# Database Version Number option. Directs whether the database is updated or left alone.
global $rabbp_suspension_db_version;
$rabbp_suspension_db_version = '1.6';



/**
 * Do teardown stuff on plugin removal.
 */
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
register_uninstall_hook( __FILE__, 'rabbp_suspension_on_uninstall');



/**
 * When plugin is deactivated, pause the wp-cron job.
 * 
 */
function rabbp_suspension_on_deactivation() {
	// Deschedule the suspension activator.
	if( false !== ( $time = wp_next_scheduled( 'rabbp_deactivate_expired_suspensions_hook' ) ) ) {
   		wp_unschedule_event( $time, 'rabbp_deactivate_expired_suspensions_hook' );
	}
}
register_deactivation_hook( __FILE__, 'rabbp_suspension_on_deactivation');



/**
 * Set up database.
 */
function rabbp_suspension_setup_database() {

	global 	$wpdb,
			$rabbp_suspension_db_version,
			$table_name;

   	# Get the character set and collation for the database so we can set
   	#  the table to the same. Setting the default character set and collation
   	#  for the table means we won't get characters being converted to just ?'s
   	#  when saved in our table.
   	$charset_collate = $wpdb->get_charset_collate();

	$sql = "
		CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			name tinytext NOT NULL,	
			user_id mediumint(9) NOT NULL UNIQUE,		
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			suspended_until datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,	
			ordinary_bbp_roles text NOT NULL,		
			reason text NOT NULL,
			status text DEFAULT 'active',			
			length_of_suspension_in_days mediumint(9),
			created_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			last_modified DATETIME DEFAULT 0,
			UNIQUE KEY id (id)
		);
	";

	# Run the query that creates the table
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );


	# Record the database version in the wp_options table
	add_option( 'rabbp_suspension_db_version', $rabbp_suspension_db_version );



	// Create a trigger associated with this table to update createDate upon row insertion and lastModified whenever updated.
	
	$trigger_name = $table_name . "_create_time_trigger";
	$trigger_sql = "CREATE TRIGGER $trigger_name 
		BEFORE UPDATE ON $table_name
		FOR EACH ROW BEGIN
			SET NEW.last_modified = now();
		END
	";
	$wpdb->query($trigger_sql);

	$installed_ver = get_option( "rabbp_suspension_db_version" );

	if ( $installed_ver != $rabbp_suspension_db_version ) {
		#...
		# Update the database version in the wp_options table
		update_option( "rabbp_suspension_db_version", $rabbp_suspension_db_version );
	}

}



/**
 * Update database as necessary depending on database version updates
 */
function rabbp_suspension_do_update_db_check() {
    global $rabbp_suspension_db_version;
    if ( get_site_option( 'rabbp_suspension_db_version' ) != $rabbp_suspension_db_version ) {
    	rabbp_suspension_setup_database();
    }
}
add_action( 'plugins_loaded', 'rabbp_suspension_do_update_db_check' );



/**
 * Checks database is up-to-date and schedules the wp-cron event on plugin re/activation.
 */
function rabbp_suspension_on_activation() {

	// Check database is up to date
	rabbp_suspension_do_update_db_check();

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

	// Add top level menu page. Usage: add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
	add_menu_page( 'Suspensions', 'Suspensions', 'manage_options', 'suspensions', 'rabbp_suspension_render_list_page', '', 6.3 );

	// Add submenu items. Usage: add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	add_submenu_page( 'suspensions', 'Suspension', 'Add New', 'manage_options', 'suspension', 'rabbp_suspension_render_single_page' );

	// Add submenu items. Usage: add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	add_submenu_page( 'suspensions', 'Suspension Options', 'Options', 'manage_options', 'suspension-options', 'rabbp_suspension_options_page' );
}
if ( is_admin() ){
	add_action('admin_menu','rabbp_suspension_add_menu_items');
}



?>
