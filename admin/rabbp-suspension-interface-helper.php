<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


RabbpSuspensionInterfaceHelper::init(); 

class RabbpSuspensionInterfaceHelper {

	public static function init() {

		// Add styles to admin header
		add_action( 'admin_head', array( get_called_class(), 'dropdown_styles') );		

		// Add Javascript to admin footer
		add_action( 'admin_footer', array( get_called_class(), 'user_lookup_javascript') ); 

		// Callback for user-lookup dropdown selector
		add_action( 'wp_ajax_suspensions_user_lookup', array( get_called_class(), 'suspensions_user_lookup') );

    }
	


	/*
	 * Add styling to the head for user-search dropdown
	 */
	public function dropdown_styles() {

		echo '
			<style>
				ul#user_field_results {
					z-index: 10;
					position: relative;
					background: #FFF;
					min-width: 175px;
					position: absolute;
					padding: 0 !important;
					margin: 0 !important;
				}
				ul#user_field_results li a {
					padding: 4px;
					width: 100%;
					display: block;
				}
			</style>';
	}



	/*
	 * Callback for user search dropdown selector
	 * TODO: Need to make this so it DOESN'T ever send back bbp_suspended as the original role. If it gets back bbp_suspended in its role stuff, DON'T UPDATE THAT!
	 */
	function suspensions_user_lookup() {

		global $wpdb; // this is how you get access to the database

		$name_fragment = $_POST['name_fragment'];

		$userids = $wpdb->get_col( $wpdb->prepare(
			"
			SELECT $wpdb->users.ID
			FROM $wpdb->users
			WHERE user_nicename LIKE '%%%s%%'
			", $name_fragment
		) );

		// Build list of results.
		$return_markup = "";

		foreach ( $userids as $userid ) {

			$user = get_userdata( $userid );

			$myHelper = new RabbpSuspensionHelper();
			$roles = $myHelper->getCurrentRoles( $userid );
			
			$return_markup .= '<li><a data-roles="' . $roles . '" href="noclick" class="user_lookup_option" data-id="' . $user->ID . '">' . $user->user_nicename . '</a></li>';
		}


	    echo $return_markup;

		die(); // this is required to terminate immediately and return a proper response
	}



	/*
	 * Add javascript to bottom for ajax user searching
	 */

	public function user_lookup_javascript() { ?>

		<script type="text/javascript">
			jQuery(document).ready(function($) {

				// Global results list object readied for entry into page 
				$results_list = $('<ul id="user_field_results" class="medium-text"></ul>');

				// Add keyup and click actions to input field to do searching based on input text.
				var $user_input_field = $('input[name="name"]');
				$user_input_field.keypress(rabbp_suspension_search_usernames_for_string);
				$user_input_field.click(rabbp_suspension_search_usernames_for_string);

				function rabbp_suspension_search_usernames_for_string() {
					$user_input = $user_input_field.val();
					if ( $user_input.length >= 3) {

						// action to happen after entering 3 letters, and post data to be made available to it.
						var data = {
							'action': 'suspensions_user_lookup',
							'name_fragment': $user_input
						};
						$.post(ajaxurl, data, function(response) {
							// Remove old results list
							$("ul#user_field_results").remove();

							// Add current results to the ul ready for assessing/displaying
							$results_list.html(response);

							// If any of the results are a perfect match with no other alternatives, 
							// automatically select it; 
							// otherwise, display results in a dropdown.
							$results_list.find("li a").each(function() {
								if ( ($results_list.size==1) && ($(this).text() == $user_input) ) {
									// Get name from a value and ID from data-id
									// and ordinary_bbp_roles from data-roles
									$user_input_field.val( $(this).text() );
									$("#user_id").val( $(this).data("id") );	
									// Process the data-roles and update if it's not bbp_suspended
									updateRolesFieldIfAppropriate( $roles_data );						
								} else {
									// No match. Display dropdown and empty user_id field.
									$results_list.insertAfter($user_input_field);
									$("#user_id").val("");	
								}
							});
						});
					} else {
						// Dropdown disappears if user has fewer than 3 characters in the field.
						$("ul#user_field_results").remove();	
					}
				}


				// If user clicks on a (dynamically-created) result, use its data to set username field,
				// hidden ID field and ordinary_bbp_roles field.
				$("body").on("click", "a.user_lookup_option", function(e) {
					e.preventDefault();
					
					// Get the name from the a value and the ID from the data-id
					$user_input_field.val( $(this).text() );
					$("#user_id").val( $(this).data("id") );
					// Process the data-roles and update if it's not bbp_suspended
					updateRolesFieldIfAppropriate( $(this).data("roles") );

					// Finished with the results ul now
					$("ul#user_field_results").remove();

					return false;
				});


				// Make sure no roles in $roles_data are "bbp_suspended" (signifying the 
				// user was suspended already and their current role at the time already saved.
				// If no such role is currently applied to them, then we want to save their current role;
				// therefore add the contents to the ordinary_bbp_roles field.
				function updateRolesFieldIfAppropriate( $roles_data ) {
					$roles_data_as_array = $roles_data.split(",");
					if ( $.inArray("bbp_suspended", $roles_data_as_array) < 0 ) {
						$("#ordinary_bbp_roles").val( $roles_data );
					}
				}


			});
		</script> <?php
	}

}

?>
