<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) 
	exit; 


class SfbbpHelper {

	/**
	 * Calculates and returns an end date given a start date and a length (in days)
	 */
	function calculate_end_date( $start, $length ) {
		global $format_string;
		$format_string = 'Y/m/d h:i';

		// Calculate
		$calculated_end_date = new DateTime( $start );
		date_add( $calculated_end_date, date_interval_create_from_date_string($length . " days") );
		
		// Format
		$suspended_until = $calculated_end_date->format( $format_string );

		return $suspended_until;
	}



	/*
	 * Deletes provided suspensions from database by suspension IDs (supplied as a comma-separated string).
	 * @params String $selected_suspensions
	 */
	function delete_suspensions( $selected_suspensions ) {

		$myHelper = new SfbbpHelper();
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
				// Reinstitute usual BP roles
				$myHelper->reinstitute_usual_roles( $suspension->id );
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

		$myHelper = new SfbbpHelper();
		$result = $myHelper->prepare_suspension( $selected_suspensions );

		if ( $result ) {

			// If prepare_suspensions only returned a single result, it will be an object rather than an array.
			// This is what the original function (not written by me) did, and what some stuff in the list page code may 
			// there rely on, so I'm leaving it working that way if a single result only is returned.
			// For this function, however, it's easier to have the results in an array whether it's single or multiple
			// for consistency of processing. Ergo we put any single result into an array before we proceed:
			$suspensions = array();
			if ( is_array( $result ) ) {
				$suspensions = $result;
			} else {
				$suspensions[0] = $result;
			}

			foreach ( $suspensions as $suspension ) {
				// Mark suspensions as complete and reapply usual bbPress roles
				$myHelper->reinstitute_usual_roles( $suspension->id );
				$myHelper->set_suspension_status( $suspension->id, "COMPLETE" );	
		   	}

		}

	}


	/*
	 * @returns an array of errors encountered when the Suspension form is submitted.
	 * @returns an empty array if no errors. Form processing is dependent on this returning an empty array.
	 */
	function check_form_data_for_errors( $data ) {

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

		return $errors;
	}


	/*
	 * Gets the display name for a user_id
	 */
    function get_displayname_from_userid( $user_id ) {
        $user = get_userdata( $user_id );
        return $user->display_name; 
    }


	/*
	 * In the lack of an easily-discernable alternative, produces a humanized version of the rolename from a supplied
	 * bbPress rolecode by performing the amazing feat of... just lopping off the "bbp_" at the beginning.
	 * @param string role
	 * @returns string role
	 */
    function make_humanized_rolename_from_bbp_rolecode( $role ) {
		$first_four_letters_of_role = substr( $role, 0, 4 );
		if ( $first_four_letters_of_role == "bbp_") {
			// Chop it off!
			$chopped = substr( $role, 4 );
			$chopped_capitalized = ucfirst( $chopped );
			return $chopped_capitalized;
		} else {
			return $role;
		}
    }


	/*
	 * Checks if a user is suspended (ie has the bbp_suspended forum role)
	 * @params int user_id
	 * @returns a boolean true or false
	 */
	function is_suspended( $user_id ) {
		$myHelper = new SfbbpHelper();
		if ( $myHelper->roles_for_user_includes( $user_id, "bbp_suspended" ) ) {
			return true;
		}
		return false;
	}


	/*
	 * Checks a date is real and in the format required
	 * @params string date
	 * @returns a boolean true if all expectations are met
	 */
	function validate_date( $date ) {
		// check the format is as requested
		if( !preg_match( '!\d{4}/\d{2}/\d{2} \d{2}:\d{2}!', $date ) ) {
			return "wrong pattern";
		}
		// check date is real and valid
		$time_as_timestamp = strtotime( $date );	// parse input into a unix timestamp using strtotime to ensure it's a valid date
		if ( !$time_as_timestamp ) {
			return "date invalid";
		}
		return true;
	}


	/*
	 * Checks if a user exists with a specified ID
	 * @params int user_id
	 * @returns a boolean true or false
	 */
	function validate_user_exists( $user_id ) {
		if ( (!$user_id) || ($user_id == 0) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( $user === false) {
			return false;
		} else {
			return true;
		}
	}


	/*
	 * Checks whether a role with the name supplied is in the current roles of the user_id supplied
	 * @params int user_id, string role_name
	 * @returns boolean true or false according to whether or not the user has the role
	 */
	function roles_for_user_includes( $user_id, $role_name ) {
		if ( $user_id ) {
			$user = get_user_by('id', $user_id);
			$users_current_roles = $user->roles;

			foreach($users_current_roles as $role) {
				if ($role == $role_name) {
					return true;
				}
			}	
		}
		return false;
	}


	/* 
	 * Generates the string that will dynamically populate the ordinary_bbp_roles field
	 */
	function get_current_roles( $user_id ) {
		
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

		// Our query	
		$query = "SELECT * FROM $table_name WHERE id IN ($placeholders_for_ids)";
		$data = $wpdb->get_results( $wpdb->prepare($query, $suspension_ids_as_array), OBJECT );

		// Just return the object, not wrapped in an array, unless there are multiple rows
		if (count($data) == 1) {
			$data = $data[0];
		}
		return $data;
	}




	/* 
	 * Removes suspended role and adds back a user role
	 */
	function reinstitute_usual_roles( $suspension_id ) {

		$myHelper = new SfbbpHelper();
		$suspension = $myHelper->prepare_suspension( $suspension_id );

		$user_id = $suspension->user_id;
		$roles_that_were_removed_as_string = $suspension->ordinary_bbp_roles;

		// Get WP_User object, remove the suspended role and give them back their old roles.
		$user = get_user_by('id', $user_id );

		$roles_that_were_removed = explode( ",", $roles_that_were_removed_as_string );

		if ( $user ) {

			// Re-add any bbPress roles we took off the user when suspending them.
			if ( $roles_that_were_removed ) {
				foreach( $roles_that_were_removed as $role ) {
					$user->add_role( $role );
				}
			} else {
				//error_log("No roles to add back.");
			}

			// Remove the 'Suspended' role.
			$user->remove_role("bbp_suspended");

			return true;

		} else {
			return false;
		}
	}


	/*
	 * Removes roles based on the user_id and expiry date a suspension ID passed in.
	 * Happens when a suspension is added or updated successfully.
	 */
	function remove_roles_and_set_as_suspended( $suspension_id ) {

		// Query the database via for the relevant suspension.
		$myHelper = new SfbbpHelper();
		$suspension = $myHelper->prepare_suspension( $suspension_id );

		// We're going to want to return them to this level of role once their suspension has expired.

		$user_id = $suspension->user_id;

		$user = get_user_by('id', $user_id );

		if ( $user ) {
			// Remove any bbPress roles so user can't post comments or forum posts while the suspension lasts.
			// We're only interested in saving the bbPress roles for retrieval later. Any other membership status should remain the same

			$users_current_roles = $user->roles;

			$roles_to_remove = array();

			foreach( $users_current_roles as $role ) {
				$first_three_letters_of_role = substr($role, 0, 3);
				if ( $first_three_letters_of_role == "bbp" ) {
					array_push( $roles_to_remove, $role );
				}
			}

			// Save any other useful stuff to args to be passed into the action when it runs
			$roles_to_remove_serialized = serialize( $roles_to_remove );	
			$args = array( $suspension_id, $user_id, $roles_to_remove_serialized );


			// Switch the user's role from their old one(s) to the 'Suspended' role and save their old role 
			// so it can be reinstituted later.
			foreach( $roles_to_remove as $role ) {
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
	 *  Requires a suspension_id (int), and a status (string).
	 */
	function set_suspension_status( $suspension_id, $new_status ) {

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
