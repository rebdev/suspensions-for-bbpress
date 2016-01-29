<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) 
	exit; 


/**
 * Adds nav items to admin menu.
 */
function sfbbp_add_menu_items() {
	// Add top level menu page
	// Usage: add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
	add_menu_page( 'Suspensions', 'Suspensions', 'manage_options', 'suspensions', 'rabbp_suspension_render_list_page', 'dashicons-welcome-comments', 26 );

	// Add submenu items
	// Usage: add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	add_submenu_page( 'suspensions', 'Suspension', 'Add New', 'manage_options', 'suspension', 'sfbbp_single_suspension' );

	// Add submenu items
	// Usage: add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);
	add_options_page( 'Suspensions', 'Suspensions', 'manage_options', 'suspension-options', 'sfbbp_options_page' );
}
if ( is_admin() ){
	add_action( 'admin_menu','sfbbp_add_menu_items' );
}


/*
 * Add styling to the head for user-search dropdown
 */
function sfbbp_dropdown_styles() {
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
add_action( 'admin_head', 'sfbbp_dropdown_styles' );		


/*
 * Add javascript to bottom for ajax user searching
 */
function sfbbp_user_lookup_javascript() { ?>
	<script type="text/javascript">
		jQuery(document).ready(function($) {

			// Global results list object readied for entry into page 
			$results_list = $('<ul id="user_field_results" class="medium-text"></ul>');

			// Add keyup and click actions to input field to do searching based on input text.
			var $user_input_field = $('input[name="name"]');
			$user_input_field.keypress( sfbbp_search_usernames_for_string );
			$user_input_field.click( sfbbp_search_usernames_for_string );

			function sfbbp_search_usernames_for_string() {
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
								sfbbp_update_roles_field_if_appropriate( $roles_data );						
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
				//sfbbp_update_roles_field_if_appropriate( $(this).data("roles") );

				// Finished with the results ul now
				$("ul#user_field_results").remove();

				return false;
			});


			// Make sure no roles in $roles_data are "bbp_suspended" (signifying the user was 
			// suspended already and their current role at the time already saved. If no such role is 
			// currently applied to them, then we want to try and save their current role/s; therefore add the 
			// contents to the ordinary_bbp_roles field. Content will be validated when form is submitted to ensure
			// content entered here are actual roles in the system.
			//function sfbbp_update_roles_field_if_appropriate( $roles_data ) {
			//	$roles_data_as_array = $roles_data.split(",");
			//	if ( $.inArray("bbp_suspended", $roles_data_as_array) < 0 ) {
			//		$("#ordinary_bbp_roles").val( $roles_data );
			//	}
			//}
		});
	</script> <?php
}
add_action( 'admin_footer', 'sfbbp_user_lookup_javascript' ); 



/*
 * Callback for user search dropdown selector
 * TODO: Need to make this so it DOESN'T ever send back bbp_suspended as the original role. If it gets back bbp_suspended in its role stuff, DON'T UPDATE THAT!
 */
function sfbbp_user_lookup() {

	global $wpdb; // this is how we get access to the database

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

		//$myHelper = new SfbbpHelper();
		//$roles = $myHelper->get_current_roles( $userid );
		$roles = "";
		
		$return_markup .= '<li><a data-roles="' . $roles . '" href="noclick" class="user_lookup_option" data-id="' . $user->ID . '">' . $user->user_nicename . '</a></li>';
	}


    echo $return_markup;

	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_suspensions_user_lookup', 'sfbbp_user_lookup' );



?>
