<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/*
 * Some useful variables
 */
global $rabbp_suspension_db_version;
$rabbp_suspension_db_version = '1.4';
$path = plugin_dir_path( __FILE__ );

//add_action( 'plugins_loaded', 'rabbp_suspension_update_db_check' ); // Update database as necessary depending on database version updates (above)




/*
 * Do teardown stuff on plugin removal.
 */
function rabbp_suspension_on_uninstall() {
	
	// Restore all user privileges
	rabbp_deactivate_expired_suspensions();

	// Drop table entirely
	global $wpdb;
	$table_name = $wpdb->prefix . 'suspensions';
	$sql = "DROP TABLE $table_name";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$wpdb->query($sql);
}
register_uninstall_hook( __FILE__, 'rabbp_suspension_on_uninstall');



/*
 * When plugin is deactivated, pause the wp-cron job.
 */
function rabbp_suspension_on_deactivation() {
	// Deschedule the suspension activator.
	if( false !== ( $time = wp_next_scheduled( 'rabbp_deactivate_expired_suspensions_hook' ) ) ) {
   		wp_unschedule_event( $time, 'rabbp_deactivate_expired_suspensions_hook' );
	}
}
register_deactivation_hook( __FILE__, 'rabbp_suspension_on_deactivation');




/*
 * Set up database from nothing.
 */
function rabbp_suspension_setup_database() {
	global $wpdb;
	global $rabbp_suspension_db_version;
	$table_name = $wpdb->prefix . 'suspensions';

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

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	// Create a trigger associated with this table to update createDate upon row insertion and lastModified whenever updated.
	
	$trigger_name = $table_name . "_create_time_trigger";
	$trigger_sql = "CREATE TRIGGER $trigger_name 
		BEFORE UPDATE ON $table_name
		FOR EACH ROW BEGIN
			SET NEW.last_modified = now();
		END
	";
	$wpdb->query($trigger_sql);

	// Update the DB version in the options.
	update_option( "rabbp_suspension_db_version", $rabbp_suspension_db_version );
}


/*
 * Update database as necessary depending on database version updates
 */
function rabbp_suspension_do_update_db_check() {
    //global $rabbp_suspension_db_version;
    //if ( get_site_option( 'rabbp_suspension_db_version' ) != $rabbp_suspension_db_version ) {
    	rabbp_suspension_update_database();
    //}
}



/*
 * Do re/activate stuff on plugin activation.
 * Checks database is up-to-date.
 * Schedules the wp-cron event.
 */
function rabbp_suspension_on_activation() {

	// Check database is up to date
	rabbp_suspension_do_update_db_check();

	// Following this: http://codex.wordpress.org/Creating_Tables_with_Plugins	
	//rabbp_suspension_setup_database();	

	// If the suspension reactivator is not scheduled, schedule it.
	if( !wp_next_scheduled( 'rabbp_deactivate_expired_suspensions_hook' ) ) {
		wp_schedule_event( time(), 'daily', 'rabbp_deactivate_expired_suspensions_hook' );
	}

}
register_activation_hook( __FILE__ , 'rabbp_suspension_on_activation');









/*
 * Update the database if it's not up to date.
 */
function rabbp_suspension_update_database() {
	global $wpdb;
	global $rabbp_suspension_db_version;
	$table_name = $wpdb->prefix . 'suspensions';

	$sql = "
		CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			name tinytext NOT NULL,	
			user_id mediumint(9) NOT NULL UNIQUE,		
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			suspended_until datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,	
			ordinary_bbp_roles text NOT NULL,	
			reason text NOT NULL,
			status text NOT NULL,			
			length_of_suspension_in_days mediumint(9),
			created_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			last_modified DATETIME DEFAULT 0,
			UNIQUE KEY id (id)
		);
	";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	// Create a trigger associated with this table to update createDate upon row insertion and lastModified whenever updated.
	
	$trigger_name = $table_name . "_create_time_trigger";
	$trigger_sql = "CREATE TRIGGER $trigger_name 
		BEFORE UPDATE ON $table_name
		FOR EACH ROW BEGIN
			SET NEW.last_modified = now();
		END
	";
	$wpdb->query($trigger_sql);

	// Update the DB version in the options.
	update_option( "rabbp_suspension_db_version", $rabbp_suspension_db_version );

}












/**
 * Add nav items to admin menu.
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
