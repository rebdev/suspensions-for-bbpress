jQuery(document).ready(function($) {

	// Display message reminding user they are suspended.
	$("<div></div>").attr("id", "suspension_notification")
					.addClass("alert alert-warning affix-top") // Bootstrap classes
					.insertBefore("#page")
					.html("<strong>" + rabbp_suspension_script_vars.message + "<br />Your suspension ends " + rabbp_suspension_script_vars.expirydate + ".</strong>")
					.slideDown();


});
