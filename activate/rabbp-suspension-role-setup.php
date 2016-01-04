<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/* 
 * Intercept the creation of bbPress' dynamic roles and insert our own new 'Suspended' one.
 * Method from here: http://gawainlynch.com/customising-dynamic-roles-in-bbpress-2-2/
 */
function rabbp_suspension_get_dynamic_roles( $bbp_roles ) {

  $bbp_roles['bbp_suspended'] = array( 
    'name' => 'Suspended',
    'capabilities' => rabbp_suspension_get_caps_for_role( 'bbp_suspended' ) // i just want them to have the same capabilities as participants
  );

  return $bbp_roles;
}   
add_filter('bbp_get_dynamic_roles', 'rabbp_suspension_get_dynamic_roles', 1);


/*
 * Filter capabilities for new roles and return capabilities.
 */
function rabbp_suspension_get_caps_for_role_filter($caps, $role) {

    /* Only filter for roles we are interested in! */
    if ($role == 'bbp_suspended')
        $caps = rabbp_suspension_get_caps_for_role($role);

    return $caps;
}
add_filter('bbp_get_caps_for_role', 'rabbp_suspension_get_caps_for_role_filter', 10, 2);


/* 
 * Specify capabilities for Suspended role 
 */
function rabbp_suspension_get_caps_for_role($role) {
    
    switch ($role) {
        /* Disable viewing of private forums by 'Participants' */
        case 'bbp_suspended':
            return array(
                
                // Primary caps
                'spectate' => true,
                'participate' => false,
                'moderate' => false,
                'throttle' => false,
                'view_trash' => false,
                
                // Forum caps
                'publish_forums' => false,
                'edit_forums' => false,
                'edit_others_forums' => false,
                'delete_forums' => false,
                'delete_others_forums' => false,
                'read_private_forums' => false,
                'read_hidden_forums' => false,
                
                // Topic caps
                'publish_topics' => false,
                'edit_topics' => false,
                'edit_others_topics' => false,
                'delete_topics' => false,
                'delete_others_topics' => false,
                'read_private_topics' => false,
                
                // Reply caps
                'publish_replies' => false,
                'edit_replies' => false,
                'edit_others_replies' => false,
                'delete_replies' => false,
                'delete_others_replies' => false,
                'read_private_replies' => false,
                
                // Topic tag caps
                'manage_topic_tags' => false,
                'edit_topic_tags' => false,
                'delete_topic_tags' => false,
                'assign_topic_tags' => false
            );
            break;
        default:
            return $role;
    }
}
?>
