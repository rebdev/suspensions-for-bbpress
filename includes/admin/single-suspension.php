<?php 

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { 
	exit; 
} 

/*
 * Render single (New and Edit) pages: suspension details in a form.
 */
function sfbbp_single_suspension() {

	$myHelper = new SfbbpHelper();

	global 	$wpdb,
			$format_date,
			$default_suspend_time;
	$format_date = 'Y/m/d h:i';

	// Set default suspend time from options if one is entered, else use 7 days.
	if ( get_option('default_suspend_time') ) {
		if ( absint(get_option('default_suspend_time') ) > 0 ) {
			$default_suspend_time = absint( get_option('default_suspend_time') );
		} else {
			$default_suspend_time = 7;
		}
	} else {
		$default_suspend_time = 7;
	}


	$clean = array(); 	// An array to hold incoming sanitized data
	$errors = array(); 	// An array to hold error messages about fields that don't pass muster


	/*
	 * This first bit takes place when the form is submitted.
	 * In it we validate, sanitize and otherwise process the input data.
	 * If any problems are detected we don't save, and instead provide error messages to the user.
	 * If no errors, we save the data - either updating an existing suspension or creating a new one depending
	 * on whether or not a suspension_id is present.
	 */
	if ( isset( $_POST['submit'] ) ) {

		// Sanitize suspension_id so that we only accept an int
		if ( !empty( $_POST['id'] ) ) {
			$clean['suspension_id'] = absint( $_POST['id'] );
		} else {
			$clean['suspension_id'] = null;
		}


		// Sanitize username and user_id
		if ( !empty( $_POST['name'] ) ) {
			
			// Sanitize name field contents
			$clean['name'] = sanitize_text_field( $_POST['name'] );

			// Sanitize and validate user_id field contents
			if ( absint( $_POST['user_id'] ) > 0 ) {
				$clean['user_id'] = intval( $_POST['user_id'] );
			
				// Check user with the sanitized ID actually exists
				if ( $myHelper->validate_user_exists( $clean['user_id'] == false ) ) {		
					$errors['user_id'] = "<p>No valid user could be determined from the username you entered. 
						Please make sure the user_id field auto-fills when you type in a username. 
						You may need to turn JavaScript on in your browser if it isn't already.</p>";
				}

			} else {

				// Something was wrong with ID, even though some attempt was made at entering a username
				$clean['user_id'] = null;
				$errors['user_id'] = "<p>No valid user could be determined from the username you entered. 
					Please make sure the user_id field auto-fills when you type in a username. 
					You may need to turn JavaScript on in your browser if it isn't already.</p>";	
			}

		} else {
			$clean['name'] = "";
			$clean['user_id'] = "";
			$errors['name'] = "<p>You haven't specified the user you wish to suspend.</p>";
		}


		// Sanitize length_of_suspension_in_days
		if ( !empty( $_POST['length_of_suspension_in_days'] ) ) {
			$clean['length_of_suspension_in_days'] = absint( $_POST['length_of_suspension_in_days'] );
		} else {
			// Use default suspend time as set in options, or if none is set, use 7 days.
			if ( $default_suspend_time ) {
				$default_no_of_suspension_days = $default_suspend_time;
			} else {
				$default_no_of_suspension_days = 7;
			}
			$clean['length_of_suspension_in_days'] = absint( $default_no_of_suspension_days );
		}
		

		// Sanitize and validate time when suspension starts: the current time by default.
		if ( isset( $_POST['time'] ) ) {
			// Check it passes our validations
			$date_validation_result = $myHelper->validate_date( $_POST['time'] );
			if ( $date_validation_result==1 ) {
				// then convert it to a valid datetime string ready for database entry
				$time_as_timestamp = strtotime( $_POST['time'] );
				$clean['time'] = date($format_date, $time_as_timestamp); 
			} else {
				$clean['time'] = "";
				$errors['time'] = "<p>" . $date_validation_result . "</p>";
			}
		} else {
			$time = current_time('mysql');
		}


		// Sanitize and validate status (a required field; active by default)
		switch( $_POST['status'] ) {
			case 'COMPLETE':
				$clean['status'] = 'COMPLETE';
				break;
			case 'ACTIVE':
				default:
				$clean['status'] = 'ACTIVE';
				break;
		}


		// Sanitize reason
		if ( isset( $_POST['reason'] ) ) {
			$clean['reason'] = sanitize_text_field( $_POST['reason'] );
		} else {
			$clean['reason'] = "";
		}


		// Calculate when suspension continues until
		$suspended_until = $myHelper->calculate_end_date( $clean['time'], $clean['length_of_suspension_in_days']);



		// Use the user_id to grab the user's current bbp role/s so we can save them before suspending them. 
		// These will be used later to reinstate their role when the suspension period has ended.
		if ( empty( $errors['user_id'] ) ) {

			$myHelper = new SfbbpHelper();

			if ( !$clean['suspension_id'] || $clean['suspension_id'] == null ) {
				// There's no suspension ID, which means we're creating a new suspension and want to check if the user is
				//  currently suspended in order to work out what to save in the ordinary_bbp_roles cell
				if ( $myHelper->is_suspended( $clean['user_id'] ) ) {
					$clean['ordinary_bbp_roles'] = ''; // we won't be using this, errors are being generated
					$errors['ordinary_bbp_roles'] = "<p>User is already suspended. Please either edit their existing suspension or change its status to 'complete' before suspending the user again.</p>";
				} else {
					// Prep a string containing their current roles in order for saving.
					$roles = $myHelper->get_current_roles( $clean['user_id'] );
					$clean['ordinary_bbp_roles'] = esc_attr( $roles );
				}

			} else {

				// We have a suspension ID, so we don't want to change what's in the ordinary_bbp_roles field at all.
				// Just include in it what was in there already. (The $data array below is expecting it and will print a notice otherwise.)
				$suspension = $myHelper->prepare_suspension( $clean['suspension_id'] );
				$roles = $suspension->ordinary_bbp_roles;
				$clean['ordinary_bbp_roles'] = esc_attr( $roles );
			}
		} 
		

		// Prepare data for saving
		$table_name = $wpdb->prefix . "suspensions";
		$data = array( 
			'user_id'						=> $clean['user_id'],			
			'name'							=> $clean['name'],
			'time'							=> $clean['time'], 
			'length_of_suspension_in_days' 	=> $clean['length_of_suspension_in_days'],
			'suspended_until' 				=> $suspended_until,
			'ordinary_bbp_roles' 			=> $clean['ordinary_bbp_roles'],
			'reason' 						=> $clean['reason'],
			'status' 						=> $clean['status']
		);
		$format = array(
			'%s',			
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s'
		);

		// Build a where clause for SQL query if working on an existing suspension
		if ( $clean['suspension_id'] ) {
			$where = array('id'	=> $clean['suspension_id']);
		}


		// Build $formErrorsString, a compilation of all error messages in the $errors array
		$formErrorsString = "";
		if ( count($errors) > 0 ) {
			foreach( $errors as $key => $value) {
				$formErrorsString .= $value;
			}
		}

		// Check for validity of what's entered in the form and process if appropriate, otherwise show errors.
		if ( count( $errors ) == 0 ) {
			// If we're working on a new suspension
			if ( empty( $clean['suspension_id'] )  
				|| ( !empty( $clean['suspension_id'] ) && ( $clean['suspension_id'] == null ) )
				|| ( !empty( $clean['suspension_id'] ) && ( $clean['suspension_id'] == 0 ) ) 
				) {
					
				// First of all, check if user is already suspended and add an error message if so, because you shouldn't be able to suspend someone twice.
				
				$myHelper = new SfbbpHelper();

				if ( $clean['user_id']) { // Since if no user ID is entered there's no way we can check their roles
					if ( $myHelper->roles_for_user_includes( $data['user_id'], "bbp_suspended" ) ) {
						$formErrorsString .= "<p>That user is already suspended. Please edit their existing suspension, or change its status to complete before creating a new one.</p>";
					}
				} else {
					$formErrorsString .= "<p>You need to specify a user to suspend.</p>";
				}

				// Trigger the action that handles behind-the-scenes changes if query is successful; 
				//   else build an error message explaining this.
				
				if ( $wpdb->insert($table_name, $data, $format) === FALSE ) {  	// Failure returns false. (Success returns 0.)
				
					$message = "<div id=\"message\" class=\"error\">" 
								. $formErrorsString 
								. "</div>";
				
				} else {				

					$new_suspension_id = intval( $wpdb->insert_id );

					do_action('sfbbp_suspension_form_submitted', $new_suspension_id, $data );	

					$message = "<div id=\"message\" class=\"updated below-h2\">
									<p>Suspension created.</p>
								</div>";
				}

			} else {

				// (Editing an existing suspension)

				if ( $wpdb->update( $table_name, $data, $where, $format) === FALSE ) {   // Failure returns false
					$message = "<div id=\"message\" class=\"error\">" 
								. $formErrorsString
								. "</div>";
				} else {

					do_action('sfbbp_suspension_form_submitted', $clean['suspension_id'], $data );	
				
					$message = "<div id=\"message\" class=\"updated below-h2\">
									<p>Suspension updated.</p>
								</div>";
				}
			}
		} else {
			// Errors are > 0. Build error message from contents of $errors array, to be shown atop the form.
			$message = "<div class=\"error\">"
						. $formErrorsString
						. "</div>";
		}
	}


	/*
	 * This is where we display the form. 
	 * Fields display data if user is editing or viewing an existing suspension.
	 */

	// An array where we store suspension data after extracted from database and sanitized.
	$cleaned = array();

	// Get the suspension ID (if any), & save the current action to a var for use in the page title.
	if ( isset( $_GET['suspension'] ) && !empty( $_GET['suspension'] ) ) {
		// User is editing a pre-existing suspension
		$suspension_id = $_GET['suspension'];
		$action = "edit";
	} else if ( isset( $new_suspension_id ) ) {
		// A suspension has just been added and the page has refreshed. 
		// To be consistent with what happens when you add a new Post, we want to shot the 'edit' title/headers here too.
		$suspension_id = $new_suspension_id;
		$action = "edit";
	} else {
		// User is beginning work on a new suspension
		$suspension_id = "0";
		$action = "create";
	}

	// Get suspension from database using the suspension ID, returning no result if it's a new suspension being created.
	$suspension = $myHelper->prepare_suspension($suspension_id);

	// Prepare field values to show in form

	// Sanitize suspension ID data
	if( isset( $suspension->id ) ) {
		$cleaned['id'] = absint( $suspension->id );
	} else {
		$cleaned['id'] = "";
	}

	// Sanitize Name & User ID data
	$cleaned['name'] 	= ( !empty($suspension->name) ? esc_attr( $suspension->name ) : "" );
	$cleaned['user_id'] = ( !empty($suspension->user_id) ? absint( $suspension->user_id ) : "" );
	
	// Sanitize Length of suspension data
	if ( !empty($suspension->length_of_suspension_in_days) ) {
		$cleaned['length_of_suspension_in_days'] = absint( $suspension->length_of_suspension_in_days);
	} else {
		$cleaned['length_of_suspension_in_days'] = absint( $default_suspend_time );
	}

	// Sanitize and humanize start-time data
	if( !empty($suspension->time) ) {
		$cleaned['time'] = date( $format_date, strtotime($suspension->time) ); 
	} else {
		// We use the current time as start time by default when creating new suspensions
		$cleaned['time'] = current_time( $format_date );
	}

	// Sanitize reason for suspension
	$cleaned['reason'] = ( !empty($suspension->reason) ? esc_attr( $suspension->reason ) : "" );

	// Sanitize suspended user's ordinary roles (saved so we can restore usual roles when suspension ends)
	$cleaned['ordinary_bbp_roles'] = ( !empty($suspension->ordinary_bbp_roles) ? esc_attr( $suspension->ordinary_bbp_roles ) : "" );

	// Sanitize status
	$cleaned['status'] = ( !empty($suspension->status) ? esc_attr( $suspension->status ) : "ACTIVE" );

?>

<div class="wrap">
	
	<?php 
		// Title and header area contents differ depending on whether action if 'edit' or 'add'. 
		//  If on the edit page, a button also links to the 'Add' page, in the same way as for native Posts.
		if ($action != "edit") {
			echo "<h2>Add New Suspension</h2>";	
		} else {
			$add_link = admin_url() . "/admin.php?page=suspension";
			echo "<h2>Edit Suspension ";
			echo '<a class="add-new-h2" href="' . esc_url( $add_link ) . '">Add New</a>';
			echo "</h2>";
		}
	?>

	<?php if ( !empty( $message ) ) {
		// Error message may only contain divs with id and class attributes and p tags
		$allowed = array(
			'div' => array(
				'id' => array(),
				'class' => array()
			),
			'p'	=> array()
		);
		echo wp_kses( $message, $allowed );
	} ?>

	<!--<p>Suspend a user and schedule when wp-cron should reinstitute their usual role.</p>-->

    <form name="suspension_single" method="post" action="<?php get_the_permalink(); ?>">

        <input type="hidden" name="id" value="<?php echo esc_attr( $cleaned['id'] ); ?>">
        <input type="hidden" name="suspension_hidden" value="<?php echo esc_attr( "Y" ); ?>">

   		<input type="hidden" class="large-text" id="ordinary_bbp_roles" name="ordinary_bbp_roles" value="<?php echo esc_attr( $cleaned['ordinary_bbp_roles'] ); ?>" size="20">

		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					Username to suspend
				</th>
				<td>
					<input type="text" id="name" name="name" autocomplete="off" class="medium-text" value="<?php echo esc_attr( $cleaned['name'] ); ?>" size="20">
					User ID <input readonly type="text" id="user_id" name="user_id" autocomplete="off" class="small-text" value="<?php echo esc_attr( $cleaned['user_id'] ); ?>" size="20">
				</td>
			</tr>			

			<tr valign="top">
				<th scope="row">
					Suspension starts
				</th>
				<td>
					<input type="text" class="medium-text" name="time" value="<?php echo esc_attr( $cleaned['time']); ?>" size="20"> eg. 2014/12/10 16:00
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">
					Suspension ends after
				</th>
				<td>
					<input type="text" class="small-text" name="length_of_suspension_in_days" value="<?php echo esc_attr( $cleaned['length_of_suspension_in_days'] ); ?>" size="20"> days
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">
					Reason for suspension
				</th>
				<td>
					<textarea name="reason" rows="10" cols="50" id="reason" class="large-text code"><?php echo esc_textarea( $cleaned['reason'] ); ?></textarea>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">
					Suspension Status
				</th>
				<td>
					<select id="status" name="status">
						<option value="ACTIVE" <?php if ( $cleaned['status'] == "ACTIVE" ) echo esc_attr('selected'); ?> >ACTIVE</option>
						<option value="COMPLETE" <?php if ( $cleaned['status'] == "COMPLETE" ) echo esc_attr('selected'); ?> >COMPLETE</option>
					</select>
				</td>
			</tr>

        </table>

        <p>
        	<?php 
        		if ($action === "edit") {
        			submit_button("Update Suspension");
        		} else {
        			submit_button("Save Suspension");
        		}
			?>
        </p>

    </form>


</div>

<?php
}
?>
