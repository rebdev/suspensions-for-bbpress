<?php


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { 
    exit; 
} 


/* The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary.
 */
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}



/* Create a new list table package that extends the core WP_List_Table class.
 * WP_List_Table contains most of the framework for generating the table, but we
 * need to define and override some methods so that our data can be displayed
 * exactly the way we need it to be.
 * 
 * To display this example on a page, you will first need to instantiate the class,
 * then call $yourInstance->prepare_items() to handle any data manipulation, then
 * finally call $yourInstance->display() to render the table to the page.
 * 
 */
class Rabbp_Suspensions_List_Table extends WP_List_Table {


    /** ************************************************************************
     * REQUIRED. Set up a constructor that references the parent constructor. We 
     * use the parent reference to set some default configs.
     ***************************************************************************/
    function __construct(){
        global $status, $page;
                
        //Set parent defaults
        parent::__construct( array(
            'singular'  => 'suspension',     //singular name of the listed records
            'plural'    => 'suspensions',    //plural name of the listed records
            'ajax'      => false        //does this table support ajax?
        ) );
        
    }


    /** ************************************************************************
     * Recommended. This method is called when the parent class can't find a method
     * specifically build for a given column. For example, if the class needs to process a
     * column named 'title', it would first see if a method named $this->column_title() 
     * exists - if it does, that method will be used. If it doesn't, this one will
     * be used. Generally, you should try to use custom column methods as much as 
     * possible. 
     * 
     * For more detailed insight into how columns are handled, take a look at 
     * WP_List_Table::single_row_columns()
     * 
     * @param array $item A singular item (one full row's worth of data)
     * @param array $column_name The name/slug of the column to be processed
     * @return string Text or HTML to be placed inside the column <td>
     **************************************************************************/

    function column_default($item, $column_name) {

        switch($column_name){
            case 'user_id':            
            case 'url':
            case 'name':   
            case 'length_of_suspension_in_days':       
            case 'ordinary_bbp_roles':
            case 'suspended_until':        
            case 'status':                                   
            case 'reason':           
                return print_r($item->$column_name,true);
            default:
                return print_r($item,true); //Show the whole array for troubleshooting purposes
        }

    }



    /** ************************************************************************
     * Custom column methods are responsible for what is rendered in any column
     * with a name/slug of 'name', 'time' and 'suspended_until'. Every time the class
     * needs to render a column, it first looks for a method named 
     * column_{$column_title} - if it exists, that method is run. If it doesn't
     * exist, column_default() is called instead.
     * 
     * This example also illustrates how to implement rollover actions. Actions
     * should be an associative array formatted as 'slug'=>'link html' - and you
     * will need to generate the URLs yourself. You could even ensure the links
     * 
     * 
     * @see WP_List_Table::::single_row_columns()
     * @param array $item A singular item (one full row's worth of data)
     * @return string Text to be placed inside the column <td> (movie title only)
     **************************************************************************/

    function column_name($item){

        //Build row actions
        $actions = array(
            'edit'      => sprintf('<a href="?page=suspension&action=%s&suspension=%s">Edit</a>','edit',$item->id),
            'delete'    => sprintf('<a href="?page=suspensions&action=%s&suspension=%s">Delete</a>','delete',$item->id),
        );
        
        //Return the name contents
        return sprintf('%1$s <span style="color:silver">(user_id:%2$s)</span>%3$s',
            /*$1%s*/ $item->name,
            /*$2%s*/ $item->user_id,
            /*$3%s*/ $this->row_actions($actions)
        );

    }

    function column_ordinary_bbp_roles($item){

        //Return the user's forum roles (the ones they have when not suspended), formatted
        $roles_arr = explode(",", $item->ordinary_bbp_roles);
        if ( sizeof( $roles_arr ) > 0 ) {
            $return_str = "";
            foreach($roles_arr as $role) {
               $return_str .= $role;         
            }
        } else {
            $return_str = "No usual role saved";
        }
        return $return_str;

    }

    function column_suspended_until($item){
        //Return the time-suspension-ends contents, formatted
        return date('Y/m/d h:ia', strtotime($item->suspended_until));

    }     

    function column_status($item){
        $span_color = "";
        if ( strtolower($item->status) == 'active' ) {
            $span_color = "red";
        } 
        return sprintf('<span style="color:%1$s">%2$s</span>',
            $span_color,
            $item->status
        );

    }

    function column_time($item){
        // Return the time-of-suspension contents, formatted
		return date('Y/m/d h:ia', strtotime($item->time));

    }
  

    /** ************************************************************************
     * REQUIRED if displaying checkboxes or using bulk actions! The 'cb' column
     * is given special treatment when columns are processed. It ALWAYS needs to
     * have it's own method.
     * 
     * @see WP_List_Table::::single_row_columns()
     * @param array $item A singular item (one full row's worth of data)
     * @return string Text to be placed inside the column <td> (movie title only)
     **************************************************************************/
    function column_cb($item){

        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("suspension")
            /*$2%s*/ $item->id                //The value of the checkbox should be the record's id
        );
    	echo "box";

    }


    /** ************************************************************************
     * REQUIRED! This method dictates the table's columns and titles. This should
     * return an array where the key is the column slug (and class) and the value 
     * is the column's title text. If you need a checkbox for bulk actions, refer
     * to the $columns array below.
     * 
     * The 'cb' column is treated differently than the rest. If including a checkbox
     * column in your table you must create a column_cb() method. If you don't need
     * bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
     * 
     * @see WP_List_Table::::single_row_columns()
     * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
     **************************************************************************/
    function get_columns(){

        $columns = array(
            'cb'                    => '<input type="checkbox" />', //Render a checkbox instead of text
            'name'                  => 'Name',
            'ordinary_bbp_roles'    => 'Usual Forum Role',
            'length_of_suspension_in_days'  => 'Days Suspended',    
            'status'                => 'Suspension Status',      
            'time'                  => 'Start',
            'suspended_until'       => 'End'
            //'reason'    => 'Reason for Suspension'           
        );
        return $columns;

    }


    /** ************************************************************************
     * Optional. If you want one or more columns to be sortable (ASC/DESC toggle), 
     * you will need to register it here. This should return an array where the 
     * key is the column that needs to be sortable, and the value is db column to 
     * sort by. Often, the key and value will be the same, but this is not always
     * the case (as the value is a column name from the database, not the list table).
     * 
     * This method merely defines which columns should be sortable and makes them
     * clickable - it does not handle the actual sorting. You still need to detect
     * the ORDERBY and ORDER querystring variables within prepare_items() and sort
     * your data accordingly (usually by modifying your query).
     * 
     * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
     **************************************************************************/
    function get_sortable_columns() {

        $sortable_columns = array(
            'time'              => array('time', false),                //true means it's already sorted
            'suspended_until'    => array('suspended_until',false),
            'name'               => array('name', false),
            'status'            => array('status',false)
        );

        return $sortable_columns;

    }


    /** ************************************************************************
     * Optional. If you need to include bulk actions in your list table, this is
     * the place to define them. Bulk actions are an associative array in the format
     * 'slug'=>'Visible Title'
     * 
     * If this method returns an empty value, no bulk action will be rendered. If
     * you specify any bulk actions, the bulk actions box will be rendered with
     * the table automatically on display().
     * 
     * Also note that list tables are not automatically wrapped in <form> elements,
     * so you will need to create those manually in order for bulk actions to function.
     * 
     * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
     **************************************************************************/
    function get_bulk_actions() {
        $actions = array(
            'delete'    => 'Delete',
            'expire'    => 'Expire'
        );
        return $actions;
    }


    /** ************************************************************************
     * Optional. You can handle your bulk actions anywhere or anyhow you prefer.
     * For this example package, we will handle it in the class to keep things
     * clean and organized.
     * 
     * @see $this->prepare_items()
     **************************************************************************/
    function process_bulk_action() {

        $selection_string = ""; 

        if ( isset($_GET['suspension']) ) {
            // Get the ID's that were selected for use in the delete query
            $selected = $_GET['suspension'];

            if ( is_array($selected) ) {
                $selection_string = implode(",", $selected);
            } else {
                $selection_string = $selected;               
            }
        }     
        
        // Detect when a bulk action is being triggered and call appropriate function

        if( 'delete'===$this->current_action() ) {
            $myHelper = new RabbpSuspensionHelper();
            $myHelper->delete_suspensions( $selection_string );
        }

        if( 'expire'===$this->current_action() ) {
            $myHelper = new RabbpSuspensionHelper();
            $myHelper->expire_suspensions( $selection_string );
        }
        
    }


    /** ************************************************************************
     * REQUIRED! This is where you prepare your data for display. This method will
     * usually be used to query the database, sort and filter the data, and generally
     * get it ready to be displayed. At a minimum, we should set $this->items and
     * $this->set_pagination_args(), although the following properties and methods
     * are frequently interacted with here...
     * 
     * @global WPDB $wpdb
     * @uses $this->_column_headers
     * @uses $this->items
     * @uses $this->get_columns()
     * @uses $this->get_sortable_columns()
     * @uses $this->get_pagenum()
     * @uses $this->set_pagination_args()
     **************************************************************************/
    function prepare_items() {
        global $wpdb; //This is used only if making any database queries

        /**
         * First, lets decide how many records per page to show
         */
        $per_page = 20;
        

        /**
         * REQUIRED. Now we need to define our column headers. This includes a complete
         * array of columns to be displayed (slugs & titles), a list of columns
         * to keep hidden, and a list of columns that are sortable. Each of these
         * can be defined in another method (as we've done here) before being
         * used to build the value for our _column_headers property.
         */
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        
        /**
         * REQUIRED. Finally, we build an array to be used by the class for column 
         * headers. The $this->_column_headers property takes an array which contains
         * 3 other arrays. One for all columns, one for hidden columns, and one
         * for sortable columns.
         */
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        
        /**
         * Optional. You can handle your bulk actions however you see fit. In this
         * case, we'll handle them within our package just to keep things clean.
         */
        $this->process_bulk_action();
        

        /*
         * Role filter
         */
        // Get status param from the URL if any
        $status = isset( $_REQUEST['status'] ) ? $_REQUEST['status'] : '';
        // Do the query
		global $wpdb;
		$table_name = $wpdb->prefix . "suspensions";
		
        if ( isset( $_REQUEST['status'] ) ) {
            $data = $wpdb->get_results( $wpdb->prepare( "SELECT * 
                                                FROM $table_name
                                                WHERE status = '%s'", $status), OBJECT);

        } else {
            $data = $wpdb->get_results( sprintf("SELECT * FROM %s", mysql_real_escape_string( $table_name ), OBJECT ) );

            $data = $wpdb->get_results( $wpdb->prepare( "SELECT * 
                                                FROM $table_name"), OBJECT);

        }
        

        /**
         * REQUIRED for pagination. Let's figure out what page the user is currently 
         * looking at. We'll need this later, so you should always include it in 
         * your own package classes.
         */
        $current_page = $this->get_pagenum();
        
        /**
         * REQUIRED for pagination. Let's check how many items are in our data array. 
         * In real-world use, this would be the total number of items in your database, 
         * without filtering. We'll need this later, so you should always include it 
         * in your own package classes.
         */
        $total_items = count($data);
        
        
        /**
         * The WP_List_Table class does not handle pagination for us, so we need
         * to ensure that the data is trimmed to only the current page. We can use
         * array_slice() to 
         */
        $data = array_slice($data,(($current_page-1)*$per_page),$per_page);
        
        
        
        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where 
         * it can be used by the rest of the class.
         */
        $this->items = $data;
        
        
        /**
         * REQUIRED. We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
        ) );
    }


    /* Views sub menu to allow admin the ability to flick between status-oriented views */
    function views() {
        $subsubsubmenu = "<ul class='subsubsub'>";
        $subsubsubmenu .= "<li><a href='admin.php?page=suspensions'>All</a> |</li>";
        $subsubsubmenu .= "<li><a href='admin.php?page=suspensions&status=ACTIVE'>Active</a> |</li>";        
        $subsubsubmenu .= "<li><a href='admin.php?page=suspensions&status=COMPLETE'>Complete</a></li>";
        $subsubsubmenu .= "</ul>";
        echo $subsubsubmenu;
    }


}



/** *************************** RENDER PAGE ********************************
 *******************************************************************************
 * This function renders the admin page and the example list table. Although it's
 * possible to call prepare_items() and display() from the constructor, there
 * are often times where you may need to include logic here between those steps,
 * so we've instead called those methods explicitly. It keeps things flexible, and
 * it's the way the list tables are used in the WordPress core.
 */
function rabbp_suspension_render_list_page(){
    
    //Create an instance of our package class...
    $suspensionsListTable = new Rabbp_Suspensions_List_Table();
    
    //Fetch, prepare, sort, and filter our data...
    $suspensionsListTable->prepare_items();
    
    ?>
    <div class="wrap">
        
        <div id="icon-users" class="icon32">
            <br/>
        </div>

        <h2>
            Suspensions
            <a href="admin.php?page=suspension" class="add-new-h2">Add New</a>
        </h2>

        <?php if ( isset( $message ) ) {
            echo $message;
        } ?>

        <?php $suspensionsListTable->views(); ?>
        
        <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
        <form id="suspensions-filter" method="get">

            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />

            <!-- Now we can render the completed list table -->
            <?php $suspensionsListTable->display() ?>

        </form>
        
    </div>
    <?php
}


?>
