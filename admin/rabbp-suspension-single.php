<?php 

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { 
	exit; 
} 


/*
 * Render New and Edit pages: suspension details in a form.
 */

function rabbp_suspension_render_single_page() {

	$myHelper = new RabbpSuspensionHelper();

	global $format_date;
	$format_date = 'Y/m/d h:i';

	// Check if admin has set a default suspend time, else use our default of 7 days.
	if ( get_option('default_suspend_time') ) {
		$default_suspend_time = get_option('default_suspend_time');
	} else {
		$default_suspend_time = 7;
	}



	// Do form submission

	if ( isset($_POST['submit']) ) {

		// Read input data for saving

		if ( isset($_POST['id']) ) {
			$suspension_id = $_POST['id'];
		} else {
			$suspension_id = null;
		}


		$name = ( isset($_POST['name']) ? stripslashes($_POST['name']) : "" );
		$user_id = ( isset($_POST['user_id']) ? stripslashes($_POST['user_id']) : null );
		$length_of_suspension_in_days = ( isset($_POST['length_of_suspension_in_days']) ? stripslashes($_POST['length_of_suspension_in_days']) : $default_suspend_time );
		if ( isset($_POST['time']) ) {
			// parse input into a unix timestamp using strtotime to ensure it's a valid date
			if ( strtotime( stripslashes($_POST['time']) ) != false) {
				$time_as_timestamp = strtotime( stripslashes($_POST['time']) );
				// convert to a valid datetime string ready for database entry.
				$time = date($format_date, $time_as_timestamp); 
			} else {
				die( "That wasn't a valid date.");
			}
		} else {
			$time = current_time('mysql');
		}
		$status = ( isset($_POST['status']) ? stripslashes($_POST['status']) : "ACTIVE" ); // Status is 'ACTIVE' by default
		$reason = ( isset($_POST['reason']) ? stripslashes($_POST['reason']) : "" );
		$suspended_until = $myHelper->calculate_end_date( $time, $length_of_suspension_in_days);
		// ordinary_bbp_roles is a hidden field, populated when the admin has identified
		//  a user to suspend from the dropdown.
		$ordinary_bbp_roles = ( isset($_POST['ordinary_bbp_roles']) ? stripslashes($_POST['ordinary_bbp_roles']) : "" );
		

		// Prepare data for saving.
		global $wpdb;
		$table_name = $wpdb->prefix . "suspensions";
		$data = array(
						'user_id'	=>	$user_id,			
						'name'		=>	$name,
						'time'		=> $time, 
						'length_of_suspension_in_days' => $length_of_suspension_in_days,
						'suspended_until' 				=> $suspended_until,
						'ordinary_bbp_roles' 			=> $ordinary_bbp_roles,
						'reason' 	=> $reason,
						'status' 	=> $status
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

		// Is there an ID already? If yes, we need to remember it (saved to $where), as that's the entry we're updating.

		if( !isset($suspension_id) ) {
			$where = array('id'	=> 0);
		} else {
			$where = array('id'	=> $suspension_id);
		}
		
		// Build a message explaining what's wrong.
		$formErrors = $myHelper->checkFormDataForErrors($data);
		$formErrorsString = "";
		foreach ( $formErrors as $formError ) {
			$formErrorsString .= $formError;
		}


		// Check for validity of what's entered in the form and process if appropriate, otherwise show errors.
		
		if ( count($formErrors) == 0 ) { 

			// Are we working on a new or existing suspension?
			if( !isset($suspension_id) || ( isset($suspension_id) && ($suspension_id == null) && ($suspension_id == 0) ) ) {
				
				// It's a new one.
	
				if ( $wpdb->insert($table_name, $data, $format) === FALSE ) {  	// Failure returns false. (Success returns 0.)
					// If failed to add suspension to database, give an error message.
					$message = "<div id=\"message\" class=\"error\"><h3>Error creating suspension..</h3>" . $formErrorsString . "</div>";
				} else {
					// Success!
					// Trigger the action that handles behind-the-scenes changes
					$new_suspension_id = intval( $wpdb->insert_id );
					do_action('rabbp_suspension_form_submitted', $new_suspension_id, $data );	
					
					// User message
					$message = "<div id=\"message\" class=\"updated below-h2\"><p>Suspension created.</p></div>";
				}


			} else {

				// Try to edit an existing suspension.
				
				if ( $wpdb->update( $table_name, $data, $where, $format) === FALSE ) {   // Failure returns false.
					$message = "<div id=\"message\" class=\"error\"><h3>Error creating suspension.</h3>" . $formErrorsString . "</div>";
				} else {
					// Success!
					// Trigger the action that handles behind-the-scenes changes
					do_action('rabbp_suspension_form_submitted', $suspension_id, $data );	
					// User message
					$message = "<div id=\"message\" class=\"updated below-h2\"><p>Suspension updated.</p></div>";
				}
			}

		} else {

			$message = "<div class=\"error\">";
			foreach( $formErrors as $formError ) {
				$message .= $formError;
			}
			$message .= "</div>";

		}

	}


	/* Display the form */

	if ( isset( $_GET['action'] ) && !empty( $_GET['action'] ) ) {
		$action = $_GET['action']; 
	} else {
		$action = "0";
	}

	if ( isset( $_GET['suspension'] ) && !empty( $_GET['suspension'] ) ) {
		$suspension_id = $_GET['suspension'];
	} else if ( isset( $new_suspension_id ) ) {
		$suspension_id = $new_suspension_id;
	} else {
		$suspension_id = "0";
	}

	/* Get suspension from database (returns no result if it's a new suspension being created) */
	$suspension = $myHelper->prepare_suspension($suspension_id);

	// Prepare field values to show in form
	$id = ( isset($suspension->id) ? $suspension->id : "" );
	$name = ( isset($suspension->name) ? $suspension->name : "" );
	$user_id = ( isset($suspension->user_id) ? $suspension->user_id : "" );
	$length_of_suspension_in_days = ( isset($suspension->length_of_suspension_in_days) ? $suspension->length_of_suspension_in_days : $default_suspend_time );
	$time = ( isset($suspension->time) ? $suspension->time : current_time($format_date) );
	$reason = ( isset($suspension->reason) ? $suspension->reason : "" );
	$ordinary_bbp_roles = ( isset($suspension->ordinary_bbp_roles) ? $suspension->ordinary_bbp_roles : "" );
	$status = ( isset($suspension->status) ? $suspension->status : "ACTIVE" );

?>

<div class="wrap">

	<h2>
		<?php 
			// Title is either 'Add' or 'Edit' depending on action
			if ($action == "edit") {
				$title = "Edit Suspension";	
			} else {
				$title = "Add New Suspension";
			}
			print $title; 
		?>
	</h2>

	<?php if ( isset( $message ) ) {
		echo $message;
	} ?>

	<p>Suspend a user and schedule when wp-cron should reinstitute their usual role.</p>

    <form name="suspension_single" method="post" action="<?php get_the_permalink(); ?>">

        <input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
        <input type="hidden" name="suspension_hidden" value="Y">
   		<input type="hidden" class="large-text" id="ordinary_bbp_roles" name="ordinary_bbp_roles" value="<?php echo esc_attr( $ordinary_bbp_roles ); ?>" size="20">

		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					Username suspended
				</th>
				<td>
					<input type="text" id="name" name="name" autocomplete="off" class="medium-text" value="<?php echo esc_attr( $name ); ?>" size="20">
					user_id <input readonly type="text" id="user_id" name="user_id" autocomplete="off" class="small-text" value="<?php echo esc_attr( $user_id ); ?>" size="20">
				</td>
			</tr>			

			<tr valign="top">
				<th scope="row">
					Suspension starts
				</th>
				<td>
					<input type="text" class="medium-text" name="time" value="<?php echo esc_attr( date($format_date, strtotime($time)) ); ?>" size="20"> eg. 2014/12/10 16:00
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">
					Length of suspension
				</th>
				<td>
					<input type="text" class="small-text" name="length_of_suspension_in_days" value="<?php echo esc_attr( $length_of_suspension_in_days ); ?>" size="20"> days
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">
					Reason for suspension
				</th>
				<td>
					<textarea name="reason" rows="10" cols="50" id="reason" class="large-text code"><?php echo esc_textarea( $reason ); ?></textarea>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">
					Suspension Status
				</th>
				<td>
					<select id="status" name="status">
						<option value="ACTIVE" <?php if ($status == "ACTIVE" ) echo 'selected'; ?> >ACTIVE</option>
						<option value="COMPLETE" <?php if ($status == "COMPLETE" ) echo 'selected'; ?> >COMPLETE</option>
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
