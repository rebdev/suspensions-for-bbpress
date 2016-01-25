<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) 
	exit; 


/**
 * Register admin settings to options page (to enable admin to store option values for plugin)
 */
function rabbp_suspension_register_plugin_options() {

	// Add options section to admin settings page.
	add_settings_section( 'rabbp_options_section', 
						'Suspension Settings', 
						'rabbp_suspension_setting_section_callback_function', 
						'suspension-options');

	// Add to whitelist the options that this form is able to accept and save.
	rabbp_suspension_register_my_settings();
}
add_action( 'admin_init', 'rabbp_suspension_register_plugin_options' );


/* 
 * Whitelist options
 */
function rabbp_suspension_register_my_settings() {
	register_setting( 'rabbp_options_section', 'default_suspend_time' );
	register_setting( 'rabbp_options_section', 'suspension_message' );
}


/**
 * Show options page
 */
function rabbp_suspension_options_page() {
?>
<div class="wrap">

	<?php echo "<h2>Suspension Options</h2>"; ?>

	<form method="post" action="options.php">

		<?php settings_fields( 'rabbp_options_section' ); ?>
		<?php do_settings_sections( 'rabbp_options_section' ); ?>

		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					Default Suspend Period
				</th>
				<td>
					<input name="default_suspend_time" type="text" id="default_suspend_time" class="small-text" value="<?php echo esc_attr( get_option('default_suspend_time') ); ?>"> days
				</td>
			</tr>
			<tr>
				<th scope="row">
					Suspended Message
				</th>
				<td>
					<p>
						When a user has been suspended they will see the following message at the top of each page.
					</p>
					<p>
						<input name="suspension_message" type="text" id="suspension_message" class="large-text" value="<?php echo esc_attr( get_option('suspension_message') ); ?>">
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button("Update Options"); ?>

	</form>

</div>

<?php
}
?>
