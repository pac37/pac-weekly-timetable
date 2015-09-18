<?php
// WP_List_Table isn't loaded automatically
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

// Create custom table object
class PACWTT_Activity_List_Table extends WP_List_Table	{
	
    var $model_activity = '';
    var $model_interval = '';
    
    // One time html message (cfr. getter method )
    var $html_message = '';

	/** Child class constructor */
	public function __construct() {
		global $wpdb;
		
		// Database models init
		$this->model_activity = new PACWTT_model_activity( $wpdb->prefix . 'pacwtt_activity' );
		$this->model_interval = new PACWTT_model_interval( $wpdb->prefix . 'pacwtt_interval' );

		//Set parent defaults (Mandatory!)
		parent::__construct( array(
			'singular'  => __( 'activity', 'pacwtt-plugin' ),		// Singular name of the listed records
			'plural' => __( 'activities', 'pacwtt-plugin' ),		// Plural name the listed records
			'ajax' => false					// ajax support (not enabled)
		));
	}

	/************************************
	 * Columns' headers: display options
	 ************************************/
	
	/** Displayed attributes' labels, in order */
	public function get_columns(){
		$columns = array(
			'cb' => '<input type="checkbox" />',
			'id' => __( 'ID', 'pacwtt-plugin' ),
			'name' => __('Name' , 'pacwtt-plugin' ),
			'description' => __('Description', 'pacwtt-plugin' )
		);
		return $columns;
	}

	/** Hidden columns: none */
	public function get_hidden_columns(){
		return array();
	}

	/** sortable columms */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'id' => array('id', true),
			'name' => array('name', true),
			'description' => array('description', false)
		);
		return $sortable_columns;
	}

	/** table behaviour */
	public function prepare_items() {
		// Pagination
		$per_page = $this->get_items_per_page('activities_per_page',5);
		$current_page = $this->get_pagenum();
		$total_items = $this->model_activity->count_items();
		
		// Table column headers	
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns(); 
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Process bulk actions (and single actions too!)
		$this->process_bulk_action();

		// Pagination widgets (WP_List_Table method)
		$this->set_pagination_args( 
			array (
				'total_items' => $total_items,
				'per_page' => $per_page,
				'total_pages' => ceil( $total_items / $per_page )
			)
		);

		// Retrieve data based on pagination
		$limit_offset = ( $current_page-1 ) * $per_page;
		$limit_count = $per_page ; 
		$this->items = $this->model_activity->get_items( $limit_offset, $limit_count );
	}

	/** Text displayed when no data is avaliable */
	public function no_items() {
		  _e( 'No activities avaliable.', 'pacwtt-plugin' );
	}
	
	/****************************************
	 *  Columms' data: add links and actions
	 ****************************************/

	/** default column's data visualization method (use when no column_{key_name} method exists)  */
	public function column_default( $item, $column_name ) {
		switch( $column_name ) {
		case 'id':
		case 'name':
		case 'description':
			return $item[ $column_name ];
		default:
			return print_r( $item, true ); //shows the whole array for troubleshooting purposes
		}
	}
	
	/** Customization: 'id' data */
	public function column_id($item) {
		
		// Action 'delete' XSS protection
		$delete_nonce = wp_create_nonce( 'pacwtt_delete_activity' );
		
		// Item name with link
		$item_edit = sprintf ('<a href="?page=%s&action=%s&activity=%s">' .$item['id'] . '</a>',
				$_REQUEST['page'],
				'edit_intervals',
				$item['id']
		);
		
		// Item actions with link: Edit (activity), Delete (activity), Edit Intervals
		$actions = array(
				'edit' => sprintf('<a href="?page=%s&action=%s&activity=%s">Edit</a>',
						$_REQUEST['page'],
						'edit',
						$item['id']
				),
				'delete' => sprintf('<a href="?page=%s&action=%s&activity=%s&_wpnonce=%s">Delete</a>',
						$_REQUEST['page'],
						'delete',
						$item['id'],
						$delete_nonce
				),
				'edit_intervals' => sprintf('<a href="?page=%s&action=%s&activity=%s">Edit Intervals</a>',
						$_REQUEST['page'],
						'edit_intervals',
						$item['id']
				)
		);
		
		// Output: item name with link, no actions
		return sprintf('%1$s %2$s', 
				$item_edit,
				$this->row_actions($actions)
		);
	}

	/** Customization: 'name' data */
	public function column_name($item) {

		// Item name with link
		$item_edit = sprintf ('<a href="?page=%s&action=%s&activity=%s">' . esc_html($item['name']) . '</a>',
						$_REQUEST['page'],
						'edit_intervals',
						$item['id']
				);

		// Output: item name with link, actions (edit, delete, list intervals)
		return sprintf('%1$s', 
				$item_edit
			);
	}
	
	/** Customization: 'description' data */
	public function column_description($item) {
		// Item name with link
		$item_edit = sprintf ('<a href="?page=%s&action=%s&activity=%s">' . esc_html($item['description']) . '</a>',
				$_REQUEST['page'],
				'edit_intervals',
				$item['id']
		);
	
		// Output: item name with link, no actions
		return sprintf('%1$s',
				$item_edit
		);
	}

	/**************************************************
	 *  Bulk Actions: add checkboxes and dropdown menu 
	 **************************************************/
	
	/** Adds bulk actions drop down menu */
	public function get_bulk_actions() {
		$actions = array(
			'bulk-delete' => 'Delete'
		);
		return $actions;
	}

	/** Adds bulk actions checkboxes */
	public function column_cb($item) {
		return sprintf(
			'<input type="checkbox" name ="activity[]" value="%s" />',
				$item['id']
			);
	}

	/** Processes bulk actions and single actions */
	public function process_bulk_action() {
		/* 
		 * Example parameters
		 * 
		 * delete action (id 1)
		 * (
		 *     [page] => pacwtt-settings-all-activities
		 *     [action] => delete
		 *     [activity] => 1
		 * )
		 * Delete bulk action Invoked id (1,2)
		 * (
		 *     [page] => pacwtt-settings-all-activities
		 *     [_wpnonce] => 3253651ea2
		 *     [_wp_http_referer] => /wp-dev/wp/wp-admin/admin.php?page=pacwtt-settings-all-activities
		 *     [action] => bulk-delete
		 *     [activity] => Array
		 *     (
		 *        [0] => 1
		 *        [1] => 2
		 *     )
		 *
		 *     [action2] => -1
		 * )
		 *
		 */ 
		
		// Single item delete action
		if ( 'delete' === $this->current_action()){
			
			// XSS protection: Nonce
			$nonce = $_REQUEST['_wpnonce'];
			if ( ! wp_verify_nonce( $nonce, 'pacwtt_delete_activity' ) ) {
				wp_die( __( 'Insufficient privileges. Operation aborted.', 'pacwtt-plugin' ) );
			}
			else {
				$activity_id = absint( $_GET['activity'] );
				$this->model_activity->delete_item( $activity_id  );
				$this->model_interval->delete_activity_items( $activity_id );
				
				return; // return to the list of activities (empty message)
			}
		}
		
		// Bulk item delete action
		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )               	// Top drop down menu
			|| ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )) {    		// Bottom drop down menu
			
			$delete_ids = esc_sql( $_POST['activity'] );

			// loop over the array of record IDs and delete them
			foreach ( $delete_ids as $activity_id ) {
				$this->model_activity->delete_item( $activity_id );
				$this->model_interval->delete_activity_items( $activity_id );
			}
			
			return;
		}
		
		// Single item edit action
		if ( 'edit' === $this->current_action()){
			
			// Manage edit activity 
			$this->edit_activity_form($_GET['activity']);
			return;  // return to the list of activities
			
		}
		
		// Edit intervals belonging to an activity
		if ( 'edit_intervals' === $this->current_action()){
				
			// Show Intervals edit form and ajax list
			$this->interval_form($_GET['activity']);
			exit;	// do not return to the list of activities
		}
	}
	
	/******************************************
	 * Action: Edit Intervals
	 *****************************************/
	
	/** AJAX form: Intervals (selected activity) */
	public function interval_form( $activity_id )
	{
		// Activity info.
		$activity = $this->model_activity->read_item( $activity_id );
		
	
		?>
			<div class="wrap">
				<h2><?php echo '<h2>' . __('Activity','pacwtt-plugin') .': &laquo; ' . esc_html($activity['name']) . ' &raquo;</h2>';?></h2>
				<hr />
				<h3><?php _e( 'New interval data', 'pacwtt-plugin' ); ?>:</h3>
				<div id="pacwtt-form-messages"></div>
				<form id="pacwtt-new-interval-form" method="POST">
					<table class="form-table">
				  		<tbody>
							<tr valign="top">
								<th><?php _e( 'Day of the week', 'pacwtt-plugin' ); ?>:</th>
								<td>
									<select id='pacwtt-weekday' name='pacwtt-weekday' >
									    <option title='Select a day...' value='-1'><?php _e('Select a day...', 'pacwtt-plugin' ); ?></option>
									    <?php 
									    	// Get the mapping from the model
									    	foreach ($this->model_interval->dow_map as $index => $name)
									    		echo "<option title='$name' value='$index'>$name</option>";
									    ?>
									</select>
								</td>
							</tr>
							<tr valign="top">
								<th><?php _e( 'Start time (HH:MM):', 'pacwtt-plugin' ); ?></th>
								<td> 
									<input type="text" id="pacwtt-time-start-h" name="pacwtt-time-start-h" maxlength="2" size="2">:
									<input type="text" id="pacwtt-time-start-m" name="pacwtt-time-start-m" maxlength="2" size="2">
								</td>
							</tr>
							<tr valign="top">
								<th><?php _e('Finish time  (HH:MM):', 'pacwtt-plugin' ); ?></th>
								<td>
									<input type="text" id="pacwtt-time-end-h" name="pacwtt-time-end-h" maxlength="2" size="2">:
									<input type="text" id="pacwtt-time-end-m" name="pacwtt-time-end-m" maxlength="2" size="2">
								</td>
							</tr>
							<tr valign="top">
								<th><?php _e( 'Description :', 'pacwtt-plugin' ); ?></th>
								<td>
									<input type="text" id="pacwtt-description" name="pacwtt-description" maxlength="255" size="64">
								</td>
							</tr>	
						</tbody>
					</table>
					<input type="hidden" id ="pacwtt-activity-id" name="pacwtt-activity-id" value="<?php echo $activity['id'] ?>">
					<input type="submit" id ="pacwtt-activity-submit" class="button-primary" name="pacwtt-submit-interval" value="<?php _e('Add New Interval','pacwtt-plugin'); ?>">
							
				</form>
				<br />
				<hr />
				<h3><?php _e('Related intervals', 'pacwtt-plugin') ?>:</h3>
				<img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" id="pacwtt-loading" class="waiting" style="display:none" >
				<div id="pacwtt-list-messages"><!--  Ajax Messages Area --></div>
				<div id="pacwtt-intervals-list"><!--  Ajax Activities List Area --></div>
			</div><!-- /.wrap -->
	<?php
		return;
	}
	
	/************************
	 * Action: Edit Activity
	 ************************/	

	function edit_activity_form( $activity_id ) {
	
		if ( isset( $_POST['submit'] ) ) {
			// Form submitted
			// Chech submission origin
			
			if (check_admin_referer( 'pacwtt-activity-form' )) {
				// Validation of sanitized input
				// Validation rule: no empty activity name.
				
				$name = sanitize_text_field( $_POST['pacwtt-activity-name'] );
				$description = sanitize_text_field( $_POST['pacwtt-activity-description'] );
				
				if ('' == $name) {
					$this->html_message = pacwtt_error_message( [__("Activity name can't be empty.", 'pacwtt-plugin')] );
				}
				else {
					if ( FALSE === $this->update_items() ) {
						$this->html_message = pacwtt_error_message( [__("Database insertion failed.",'pacwtt-plugin')] );
					}
					else {
						// Success
						
						$this->html_message = pacwtt_updated_message( __('Activity Updated.', 'pacwtt-plugin') );
						return;
					}
				}
			} else {
				wp_die( __( 'Insufficient privileges. Operation aborted.', 'pacwtt-plugin' ) );
			}
		}
		else {
			// First access
			$this->html_message = '';
			$data =  $this->model_activity->read_item( $activity_id );
			$name = $data['name'];
			$description = $data['description'];
		}
		echo '<h1>'.__('Edit Interval', 'pacwtt-plugin').'</h1>';
		output_activity_form($name, $description, __( 'Update', 'pacwtt-plugin') , $this->get_html_message());
		
		// Do not return to activity list
		exit();
	}
	
	/** Update selected activity tuple */
	public function update_items()
	{
		$id = $_GET['activity'];
		$data_values = array('name' => $_POST['pacwtt-activity-name'], 'description' => $_POST['pacwtt-activity-description']);
		$data_formats = array( '%s', '%s' );
	
		$result = $this->model_activity->update_item($_GET['activity'], $data_values, $data_formats);
		return $result;
	}
	
	/** getter method */
	public function get_html_message() {
		$message = $this->html_message;
		$this->html_message = '';
		return $message;
	}
	
}

/*  Activity: edit data form 
 * 
 *  Note: data are validated and sanitized by the calling function
 * 
 * */

function output_activity_form($name, $description, $button_text, $message) {
	?>
	<div class="wrap">
		<!--  Message Area -->
		<?php echo $message; ?>
		<!--  Activity Form -->
		<form id="pacwtt-activity-form" method="POST" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
			<p><?php _e( 'Name', 'pacwtt-plugin' ); ?>: <input type='text' id='pacwtt-activity-name' name='pacwtt-activity-name' value='<?php echo $name; ?>' maxlength='32' size='32'></p>
			<p><?php _e( 'Description', 'pacwtt-plugin' ); ?>: <input type='text' id='pacwtt-activity-description' name='pacwtt-activity-description' value='<?php echo $description; ?>' maxlength='255' size='64'></p>			
			<?php 
			wp_nonce_field( 'pacwtt-activity-form' );
			submit_button( $button_text, 'primary' ); ?>
		</form>
	</div><!-- /.wrap -->		
<?php 
}
