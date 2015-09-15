<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Plugin Name: RA BBPress Suspension
 * Description: Adds a 'suspended' role that denies commenting ability within BBPress
 * for a specified amount of time.
 * Version: 1.5
 * Author: Rebecca Appleton
 * GitHub Plugin URI: https://github.com/rebdev/rabbp-suspension
 */


/*
 * Includes
 */
// General functionality
include "rabbp-suspension-helper.php";
include "activate/rabbp-suspension-role-setup.php";  			// Sets up a BBPress custom role, its only capability being to spectate.
include "activate/rabbp-suspension-activate.php"; 				// Setup and teardown
// Admin pages
include "admin/rabbp-suspension-interface-helper.php"; 			// Adds css and javascript to admin interface
include "admin/rabbp-suspension-list.php"; 						// Suspension listing for admin user
include "admin/rabbp-suspension-single.php"; 					// Suspension single view for admin user
include "admin/rabbp-suspension-options-page.php"; 				// Options page for admin user




/*
 * Runs daily when deactivate_expired_suspensions_hook event occurs.
 * Can't be part of a class just because of the way wp-crons are called.
 */
function rabbp_deactivate_expired_suspensions() {

	$myHelper = new rabbpSuspensionHelper();
	$suspensions_to_expire = $myHelper->get_expired_suspensions(); 

	foreach ($suspensions_to_expire as $suspension) {
		// Change their roles in Buddypress back to their usual one.
		$roles_data = array("user_id"=>intval( $suspension->user_id ), 
							"roles_as_string"=>$suspension->ordinary_bbp_roles);

		// Mark suspension status as 'complete' if reinstituteUsualRoles is successful
		if ( $myHelper->reinstituteUsualRoles($roles_data) == true ) {
			$myHelper->setSuspensionStatus($suspension->id, "COMPLETE");
		}
	}
}
add_action('rabbp_deactivate_expired_suspensions_hook', 'rabbp_deactivate_expired_suspensions', 10, 0);



/** 
 * Enqueue scripts
 */
function rabbp_suspension_scripts() {

	// Load jQuery for displaying message at top of screen to suspended users.

	if ( is_current_user_suspended() ) {

		// Get message for display to user
		$message = get_option('suspension_message');

		// Get suspension end date for display to user
		$current_user = wp_get_current_user();
		$myHelper = new rabbpSuspensionHelper();
		$expiry = $myHelper->get_expiry_date_for_suspended_user( $current_user->ID );

		wp_enqueue_script("rabbp_suspension", plugins_url('js/rabbp-suspension.js', __FILE__), array('jquery'), false );
		wp_enqueue_style("rabbp_suspension", plugins_url('css/rabbp-suspension.css', __FILE__), false, false, 'all');

		// Make the message and expiry date available to the jQuery script
	  	wp_localize_script( 'rabbp_suspension', 'rabbp_suspension_script_vars', array('message' => $message,
	  																				'expirydate' => $expiry) );
	}
}
add_action('wp_enqueue_scripts', 'rabbp_suspension_scripts');


/*
 * Returns true if current user has a suspended role on the forum
 */
function is_current_user_suspended() {

	if ( is_user_logged_in() ) {

		$myHelper = new rabbpSuspensionHelper();
		$current_user = wp_get_current_user();

		if ( $myHelper->rolesForUserIncludes( $current_user->ID, "bbp_suspended") ) {
			return true;
		}
	}
}




/*
 * Background actions that occur after Suspension is successfully created or edited.
 */
function rabbp_form_submitted_callback($suspension_id, $data) {
	$myHelper = new rabbpSuspensionHelper();

	// If status is complete but roles haven't been changed accordingly, do that now.
	$status = $data['status'];
	$roles_data = array('suspension_id'=>$suspension_id,
						'user_id'=>$data['user_id'],
						'roles_as_string'=>$data['ordinary_bbp_roles']);

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
		$myHelper->reinstituteUsualRoles( $roles_data );
	}
	if ( $status=="ACTIVE" && $user_is_bbp_suspended==false) {
		$myHelper->removeRolesAndSetAsSuspended($suspension_id);
	}
}
add_action('rabbp_suspension_form_submitted', 'rabbp_form_submitted_callback', 10, 2);

?>
