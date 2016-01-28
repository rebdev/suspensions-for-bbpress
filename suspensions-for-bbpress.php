<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) 
	exit ();

/**
 * Plugin Name: Suspensions for bbPress
 * Description: Adds a 'suspended' role that denies commenting ability within bbPress
 * for a specified amount of time.
 * Version: 1.7
 * Author: Rebecca Appleton
 * GitHub Plugin URI: https://github.com/rebdev/rabbp-suspension
 */


/*
 * Include files
 */
include "includes/helper.php";										// Functions used across multiple files, together in a class to use as a helper
include "uninstall.php";											// Uninstall actions
include "includes/infrastructure/roles.php";  						// Sets up a BBPress custom role, its only capability being to spectate.
include "includes/infrastructure/setup-and-teardown.php"; 			// Activation and deactivation actions
include "includes/infrastructure/admin-interface-helper.php"; 		// Adds css and javascript to admin interface
include "includes/admin/list-suspensions.php"; 						// Suspensions listing view for admin user
include "includes/admin/single-suspension.php"; 					// Suspension single view for admin user
include "includes/admin/options-and-options-page.php"; 				// Options page for admin user



/*
 * Runs daily when deactivate_expired_suspensions_hook event occurs.
 * Can't be part of a class just because of the way wp-crons are called.
 */
function sfbbp_deactivate_expired_suspensions() {

	$myHelper = new SfbbpHelper();
	$suspensions_to_expire = $myHelper->get_expired_suspensions(); 

	foreach ($suspensions_to_expire as $suspension) {
		// Change their roles in Buddypress back to their usual one.
		$roles_data = array( 	"user_id" 			=> intval( $suspension->user_id ), 
								"roles_as_string" 	=> $suspension->ordinary_bbp_roles
							);

		// Mark suspension status as 'complete' if reinstitute_usual_roles is successful
		if ( $myHelper->reinstitute_usual_roles( $roles_data ) == true ) {
			$myHelper->set_suspension_status( $suspension->id, "COMPLETE" );
		}
	}
}
add_action('sfbbp_deactivate_expired_suspensions_hook', 'sfbbp_deactivate_expired_suspensions', 10, 0);



/** 
 * Enqueue scripts
 */
function sfbbp_frontend_message() {

	if ( is_current_user_suspended() ) {

		// Get message for display to user
		$message = get_option( 'suspension_message' );

		// Get suspension end date for display to user
		$current_user = wp_get_current_user();
		$myHelper = new SfbbpHelper();
		$expiry = $myHelper->get_expiry_date_for_suspended_user( $current_user->ID );

		// Load jQuery for displaying message at top of screen
		wp_enqueue_script("sfbbp_suspension", plugins_url('js/sfbbp-suspension.js', __FILE__), array('jquery'), false );
		
		// Make the message and expiry date available to the jQuery script
	  	wp_localize_script( 'sfbbp_suspension', 'sfbbp_suspension_script_vars', array('message' => $message,
	  																				'expirydate' => $expiry) );
	}
}
add_action( 'wp_enqueue_scripts', 'sfbbp_frontend_message' );


/*
 * Returns true if current user has a suspended role on the forum
 */
function is_current_user_suspended() {

	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();

		$myHelper = new SfbbpHelper();
		if ( $myHelper->rolesForUserIncludes( $current_user->ID, "bbp_suspended") ) {
			return true;
		}
	}

}



/*
 * Background actions that occur after Suspension is successfully created or edited.
 */
function rabbp_suspension_success_background_actions($suspension_id, $data) {

	$myHelper = new SfbbpHelper();

	// If status is complete but roles haven't been changed accordingly, do that now.
	$status = $data['status'];
	$roles_data = array('suspension_id'		=> $suspension_id,
						'user_id'			=> $data['user_id'],
						'roles_as_string'	=> $data['ordinary_bbp_roles']);

	// Do the user's roles include 'suspended'?
	$user = get_user_by('id', $data['user_id']);
	$users_current_roles = $user->roles;
	if ( in_array("bbp_suspended", $users_current_roles ) ) {
		$user_is_bbp_suspended = true;
	} else {
		$user_is_bbp_suspended = false;
	}

	// Perform role removals and reinstatements if suspension status requires it
	if ( $status=="COMPLETE" && $user_is_bbp_suspended==true) {
		$myHelper->reinstitute_usual_roles( $roles_data );
	}
	if ( $status=="ACTIVE" && $user_is_bbp_suspended==false) {
		$myHelper->removeRolesAndSetAsSuspended($suspension_id);
	}
}
add_action('rabbp_suspension_form_submitted', 'rabbp_suspension_success_background_actions', 10, 2);

?>
