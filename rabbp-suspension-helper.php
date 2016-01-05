<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly



class RabbpSuspensionHelper {

	/**
	 * Contents:
	 * Fn calculate_end_date
	 * Fn delete_suspensions
	 * Fn get_current_role
	 * Fn get_expired_suspensions
	 * Fn prepare_suspension
	 * Fn reinstituteUsualRoles // TODO: Change camelcase function names across all files
	 * Fn removeRolesAndSetAsSuspended
	 * Fn setSuspensionStatus
	 */




	/**
	 * Calculates and returns an end date given a start date and a length (in days)
	 */
	function calculate_end_date( $start, $length ) {
		global $format_string;
		$format_string = 'Y/m/d h:i';
		$calculated_end_date = new DateTime( $start );
		date_add( $calculated_end_date, date_interval_create_from_date_string($length . " days") );
		$suspended_until = $calculated_end_date->format($format_string);

		return $suspended_until;
	}



	/*
	 * Deletes provided suspensions from database by suspension IDs (supplied as a comma-separated string).
	 * @params String $selected_suspensions
	 */
	function delete_suspensions( $selected_suspensions ) {

		error_log("Selected_suspensions var is: " . $selected_suspensions);

		$myHelper = new RabbpSuspensionHelper();
		$result = $myHelper->prepare_suspension( $selected_suspensions );

		if ( $result ) {

			// If prepare_suspensions only returned a single result, it will be an object rather than an array.
			// This is what the original function (not written by me) did, and what some stuff in the list page code may 
			// rely on so I'm leaving it working that way if a single result only is returned.
			// For this function, it's easier to have it in an array for consistency of processing. Ergo:
			$suspensions = array();
			if ( is_array($result) ) {
				$suspensions = $result;
			} else {
				$suspensions[0] = $result;
			}

			foreach ( $suspensions as $suspension ) {

				// Reapply usual BP roles
				$role_data = array('suspension_id'		=>$suspension->id, 
									'user_id'			=>$suspension->user_id, 
									'roles_as_string'	=>$suspension->ordinary_bbp_roles);
				$myHelper->reinstituteUsualRoles( $role_data );

		   	}

			// Delete the Suspensions' records as per user's request.
			global $wpdb;
			$table_name = $wpdb->prefix . "suspensions";
			$where = array(
				'id' => $selected_suspensions
			);

		    if ( $wpdb->delete($table_name, $where) ) {

		    	// TODO: Get this displaying under the h2 on the list page.
			    $message = "<div id=\"message\" style=\"margin-left: 0; margin-right: 22px;\" class=\"updated below-h2\"><p>Deleted.</p></div>";
		    }

		}

	}


	/*
	 * @param String $selected_suspensions
	 */
	function expire_suspensions( $selected_suspensions ) {

		$myHelper = new RabbpSuspensionHelper();
		$result = $myHelper->prepare_suspension( $selected_suspensions );

		if ( $result ) {

			// If prepare_suspensions only returned a single result, it will be an object rather than an array.
			// This is what the original function (not written by me) did, and what some stuff in the list page code may 
			// rely on so I'm leaving it working that way if a single result only is returned.
			// For this function, it's easier to have it in an array for consistency of processing. Ergo:
			$suspensions = array();
			if ( is_array($result) ) {
				$suspensions = $result;
			} else {
				$suspensions[0] = $result;
			}

			foreach ( $suspensions as $suspension ) {
				error_log ( gettype($suspension) );

				// Reapply usual BP roles and cron jobs
				$role_data = array('suspension_id'		=>$suspension->id, 
									'user_id'			=>$suspension->user_id, 
									'roles_as_string'	=>$suspension->ordinary_bbp_roles);
				$myHelper->reinstituteUsualRoles( $role_data );

				$myHelper->setSuspensionStatus( $suspension->id, "COMPLETE" );	
		   	}

		}

	}




	/*
	 * @returns an array of errors encountered when the Suspension form is submitted.
	 * @returns an empty array if no errors. Form processing is dependent on this returning an empty array.
	 */
	function checkFormDataForErrors( $data ) {

		global $wpdb;
		$errors = Array();

		if ( !$data['name'] ) {
			$errors['name'] = "<p>You haven't entered a username.</p>";
		}

		if ( !$data['user_id'] || get_user_by('id', $data['user_id'])==false ) {
			$errors['user_id'] = "<p>No valid user_id could be determined from the username you entered. 
								Please make sure the user_id field auto-fills when you type in a username. 
								You may need to turn JavaScript on in your browser if it isn't already.</p>";
		}

		if ( !$data['time'] || strtotime($data['time'])==false ) {
			$errors['name'] = "<p>Invalid time.</p>";
		}

		$myHelper = new RabbpSuspensionHelper();
		if ( $myHelper->rolesForUserIncludes( $data['user_id'], "bbp_suspended" ) ) {
			$errors['user_id'] = "<p>That user is already suspended. Try editing their existing suspension.</p>";
		}

		return $errors;
	}



	/*
	 * Checks whether a role with the name supplied is in the current roles of the user_id supplied
	 * @params int user_id, string role_name
	 * @returns boolean true or false according to whether or not the user has the role
	 */
	function rolesForUserIncludes( $user_id, $role_name ) {
		$user = get_user_by('id', $user_id);
		$users_current_roles = $user->roles;

		foreach($users_current_roles as $role) {
			if ($role == $role_name) {
				return true;
			}
		}
		// Return false as no match happened in the for loop
		return false;
	}




	/* 
	 * Generates the string that will dynamically populate the ordinary_bbp_roles field
	 */
	function getCurrentRoles($user_id) {
		
		$user = get_user_by('id', $user_id);

		$users_current_roles = $user->roles;

		// Separate out the bbp roles
		$current_bbp_roles = array();
		foreach($users_current_roles as $role) {
			$first_three_letters_of_role = substr($role, 0, 3);
			if ( $first_three_letters_of_role == "bbp") {
				array_push( $current_bbp_roles, $role );
			}
		}

		$current_bbp_roles_as_string = implode(",", $current_bbp_roles );	
		return $current_bbp_roles_as_string;
	}





	/*
	 * @ return 
	 */
	function get_expired_suspensions() {

		// Query suspensions database for still-active suspensions whose and date has now passed

		global $wpdb;
		$table_name = $wpdb->prefix . "suspensions";
		$status = "ACTIVE";

		$sql = sprintf("SELECT * FROM %s
							WHERE status='%s'
							AND suspended_until < now()", 
							mysql_real_escape_string($table_name), mysql_real_escape_string($status) );

		$suspension_ids = $wpdb->get_results( $sql );

		return $suspension_ids;
	}


	/*
	 * Returns a formatted expiry date for a suspended user whose ID is supplied.
	 * @params Integer, representing a user ID
	 * @returns String with a user-friendly suspension expiry date for display to user
	 */
	function get_expiry_date_for_suspended_user( $userid ) {

		// Query the database for the relevant suspension(s)	
		global $wpdb;
		$table_name = $wpdb->prefix . "suspensions";
		$sql = sprintf("SELECT * FROM %s
							WHERE user_id='%u' LIMIT 1", 
							mysql_real_escape_string($table_name), $userid );

		$suspension_ids = $wpdb->get_results( $sql );
		$expiry_date = $suspension_ids[0]->suspended_until;

		// Reformat the saved date string to something more user-friendly
		$date = DateTime::createFromFormat('Y-m-d H:i:s', $expiry_date);
		$output = $date->format('d/m/Y h:ia');

		return $output;
	}


	/*
	 * Retrieves selection from database in preparation for display on page.
	 * @param String $suspension_ids (comma-separated)
	 * @returns Array of objects
	 */
	function prepare_suspension( $suspension_ids ) {

		// Query the database for the relevant suspension(s)
		global $wpdb;
		$table_name = $wpdb->prefix . "suspensions";
		$suspension_ids_as_array = explode(",", $suspension_ids);

		// Count the number of IDs
		$ids_count = count( $suspension_ids_as_array );
		// Prepare the right number of placeholders
		$unsignedDecimalPlaceholders = array_fill(0, $ids_count, '%u'); // %u = unsigned decimal number
		// Put all the placeholders in one string '%u, %u, %u, %u, %u,…'
		$placeholders_for_ids = implode(',', $unsignedDecimalPlaceholders);
		//error_log("The placeholders_for_ids var is: " . $placeholders_for_ids);

		// Our query	
		$query = "SELECT * FROM $table_name WHERE id IN ($placeholders_for_ids)";
		$data = $wpdb->get_results( $wpdb->prepare($query, $suspension_ids_as_array), OBJECT );

		// Just return the object, not wrapped in an array, unless there are multiple rows
		if (count($data) == 1) {
			$data = $data[0];
		}
		return $data;


		/*
		error_log( "Suspension ID is of type: " . gettype( $suspension_id ) );
		global $wpdb;
		$table_name = $wpdb->prefix . "suspensions";
		$sql = sprintf("SELECT * FROM %s WHERE id=%s", mysql_real_escape_string($table_name), mysql_real_escape_string($suspension_id) );
		$data = $wpdb->get_row($sql, OBJECT );
		*/


	}




	/*
	 * Removes suspended role and adds back a user role. 
	 * @param Array containing 3 things:
	 * 1. suspension_id: an int 
	 * 2. user_id: an int
	 * 3. roles_as_string: an array or a serialized set of roles to remove
	 */
	function reinstituteUsualRoles($roles_data) {

		$suspension_id = $roles_data['suspension_id'];
		$user_id = $roles_data['user_id'];
		$roles_that_were_removed_as_string = $roles_data['roles_as_string'];

		// Get WP_User object, remove the suspended role and give them back their old roles.

		$user = get_user_by('id', $user_id );

		$roles_that_were_removed = explode(",", $roles_that_were_removed_as_string);

		if ( $user ) {

			// Re-add any Buddypress roles we took off the user when suspending them.
			if ($roles_that_were_removed) {
				foreach($roles_that_were_removed as $role) {
					$user->add_role($role);
				}
			} else {
				error_log("No roles to add back.");
			}

			// Remove the 'Suspended' role.
			$user->remove_role("bbp_suspended");

			return true;

		} else {
			error_log("Un oh, no user was found by that user ID.");
			return false;

		}

	}


	/*
	 * Removes roles based on the user_id and expiry date a suspension ID passed in.
	 * Happens when a suspension is added or updated successfully.
	 */
	function removeRolesAndSetAsSuspended( $suspension_id ) {

		//error_log("suspension_id is of type: " . typeof($suspension_id) );
		// Query the database via for the relevant suspension.
		$myHelper = new RabbpSuspensionHelper();
		$suspension = $myHelper->prepare_suspension($suspension_id);

		//error_log("suspension is of type: " . typeof($suspension));
		// We're going to want to return them to this level of role once their suspension has expired.

		$user_id = $suspension->user_id;
		error_log("User id is " . $user_id);

		$user = get_user_by('id', $user_id );

		if ($user) {
			// Remove any Buddypress roles so user can't post comments or forum posts while the suspension lasts.
			// We're only interested in saving the Buddypress roles for retrieval later. Any other membership status should remain the same

			$users_current_roles = $user->roles;

			$roles_to_remove = array();

			foreach($users_current_roles as $role) {
				error_log("We are working now on the " . $role . " role.");
				$first_three_letters_of_role = substr($role, 0, 3);
				if ( $first_three_letters_of_role == "bbp") {
					array_push( $roles_to_remove, $role );
				}
			}

			// Save any other useful stuff to args to be passed into the action when it runs
			$roles_to_remove_serialized = serialize( $roles_to_remove );	
			$args = array( $suspension_id, $user_id, $roles_to_remove_serialized );


			// Switch the user's role from their old one(s) to the 'Suspended' role and save their old role to the cron job 
			// so it can be reinstituted later.
			foreach($roles_to_remove as $role) {
				$user->remove_role($role);
			}
			// Apply the 'Suspended' role.
			$user->add_role("bbp_suspended");

			return $roles_to_remove;

		} else {

			// Failed to save the cron.
			return false;

		}

	}


	/*
	* Sets the status of a given Suspension to the string supplied. 
	* Requires a suspension_id (int), and a status (string).
	*/
	function setSuspensionStatus($suspension_id, $new_status) {

		// Save the data to the db before showing the page and its feedback
		global $wpdb;
		$table_name = $wpdb->prefix . "suspensions";

		$data = array(
						'status' => $new_status
				);
		$format = array(
						'%s'
				);
		$where = array('id'	=> $suspension_id);

		//update. Failure returns false.
		if ( $wpdb->update( $table_name, $data, $where, $format) === TRUE )  {
			return true;
		} else {
			return false;
		}
	}

	

}


?>
