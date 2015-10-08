<?php

/**************************
 * CSS and AJAX management  
 **************************/

// Note to self: css.php cannot include WordPress functions (is loaded by itself) so to "render" a dynamic containing the plugin's registered options we define
// a "trigger" on the query parameters, i.e. a function will be started on a certain query. All the code is inside the plugin and the ".css" file does not exists into the file system.
// An alternate solution should be the use of WP Filesystem (use the WP funcitons instead of the php functions so the the file belong to the user and not to the server)
// to generate the new css on the fly when saving the options

/** Trigger for 'pacwtt' query variable */
function pacwtt_add_trigger($vars) {
	$vars[] = 'pacwtt-style';
	return $vars;
}
add_filter('query_vars','pacwtt_add_trigger');

/** CSS generation on /?pacwtt-style=table query */
function pacwtt_trigger_check() {
	
	if(get_query_var('pacwtt-style') == 'tables') {
		// CSS on the fly generation
		require_once plugin_dir_path(__FILE__).'../css/tables.php';
		// Halt default WP front end output 
		exit();
	}
}
add_action('template_redirect', 'pacwtt_trigger_check');


/** Insert CSS uri into html header of the frontend pages */
function register_css() {
	global $pacwtt_trigger;
	
	wp_register_style( 'pacwtt-css-tables',  home_url() ."/?pacwtt-style=tables");	
}
add_action( 'wp_enqueue_scripts', 'register_css' );

// AJAX setup //

/** Load script for appropriate admin page and action */
function load_js( $hook ) {
	
	global $hook_all_activities;
	global $pacwtt_plugin_url;
	
	// Check page
	if ($hook != $hook_all_activities) {
		return;
	}

	// Enqueue script in the page
	wp_enqueue_script(
			'pacwtt-ajax', 										// slug
			$pacwtt_plugin_url . 'js/pacwtt-ajax.js',			// file
			array('jquery')										// requirements
	);
	

	// Inset variables into HTML to be read by JavaScript
	wp_localize_script(
			'pacwtt-ajax',
			'pacwtt_vars',	// Name of the JavaScript object: pacwtt_vars.*
			array( 
					//Translated stings
					'error' => __('Error',  'pacwtt-plugin' ),
					'success' => __('Success', 'pacwtt-plugin' ),
					//workaround to pass nonce value to JavaScript as a variable
					'pacwtt_nonce' => wp_create_nonce('pacwtt-nonce'))
	);
}
add_action('admin_enqueue_scripts', 'load_js');

// AJAX Callbacks //

/** Ajax callaback: new interval */
function pacwtt_new_interval() {
	// Database Models
	$pacwtt_model_interval = new PACWTT_Model_Interval();

	// Verify the nonce
	if ( !isset( $_POST[ 'pacwtt_nonce' ] ) || !wp_verify_nonce(  $_POST['pacwtt_nonce'] , 'pacwtt-nonce' ) )
	{
		// force exit
		echo json_encode(array( 'nonce_errors' => __( 'Insufficient privileges. Operation aborted.', 'pacwtt-plugin' ) ));
		wp_die();
	}

	$response = array();

	$html_error_list = '<ul>';
	
	
	// Dates: sanitize not necessary
	// Description: sanitize (no html allowed)
	$_POST['pacwtt-description'] = sanitize_text_field( $_POST['pacwtt-description'] );
		
	// Dates: validation and error message generation
	// Description: validation not necessary
	if (-1 == $_POST['pacwtt-weekday']) {
		$html_error_list .= "<li>You must select a day of the week.</li>";
	}
	if ( '' == $_POST['pacwtt-time-start-h'] || !is_numeric( $_POST['pacwtt-time-start-h'] ) || $_POST['pacwtt-time-start-h'] < 0 || $_POST['pacwtt-time-start-h'] > 24 ){
		$html_error_list .= "<li>".__("Start time: hour must be a number from 0 to 23.", 'pacwtt-plugin' )."<li>";
	}
	if ( '' == $_POST['pacwtt-time-start-m'] || !is_numeric( $_POST['pacwtt-time-start-m'] ) ||$_POST['pacwtt-time-start-m'] < 0 || $_POST['pacwtt-time-start-m'] > 59 ) {
		$html_error_list .= "<li>".__("Start time: minute must be a number from 0 to 59.", 'pacwtt-plugin' )."<li>";
	}
	if ( '' == $_POST['pacwtt-time-end-h'] || !is_numeric( $_POST['pacwtt-time-end-h'] ) ||$_POST['pacwtt-time-end-h'] < 0 || $_POST['pacwtt-time-end-h'] > 24 ) {
		$html_error_list .= "<li>".__("Finish time: hour must be a number from 0 to 23.", 'pacwtt-plugin' )."<li>";
	}
	if ( '' == $_POST['pacwtt-time-end-m'] || !is_numeric( $_POST['pacwtt-time-end-m'] ) ||$_POST['pacwtt-time-end-m'] < 0 || $_POST['pacwtt-time-end-m'] > 59 ) {
		$html_error_list .= "<li>".__("Finish time: minute must be a number from 0 to 59.", 'pacwtt-plugin' )."<li>";
	}
	
	$html_error_list .= '</ul>';

	// Results
	if ('<ul></ul>' != $html_error_list) {
		// Consistency error
		$response = array( 'consistency_errors' => $html_error_list );
	}
	else
	{
		// Consistent data (to be inserted into the database)
		$result = $pacwtt_model_interval->insert_item (
			$_POST['pacwtt-activity-id'],
			$_POST['pacwtt-weekday'],
			$_POST['pacwtt-time-start-h'] , $_POST['pacwtt-time-start-m'],
			$_POST['pacwtt-time-end-h'] , $_POST['pacwtt-time-end-m'],
			$_POST['pacwtt-description']);

		if ($result === FALSE) {
			// Database error
			$response = array( 'database_errors' => __('Database Error: Unable to insert the specified interval.', 'pacwtt-plugin' ) );
		}
		else
		{
			// Database success
			$response = array( 'success' => __('New record successfully added.', 'pacwtt-plugin' ) );
		}
	}
	
	// Response and exit
	echo json_encode($response);
	wp_die(); // this is required to terminate immediately and return a proper response (exiting from admin-ajax.php )
}
add_action( 'wp_ajax_pacwtt_new_interval', 'pacwtt_new_interval' );

/** Ajax callaback: delete intervals */
function pacwtt_delete_interval() {
	// Database Models
	$pacwtt_model_interval = new PACWTT_Model_Interval();

	// Verify the nonce
	if ( !isset( $_POST[ 'pacwtt_nonce' ] ) || !wp_verify_nonce(  $_POST['pacwtt_nonce'] , 'pacwtt-nonce' ) )
	{
		// force exit
		echo json_encode( array( 'nonce_errors' =>  __( 'Insufficient privileges. Operation aborted.', 'pacwtt-plugin' ) ) );
		wp_die();
	}

	// Parameters
	$interval_id = intval( $_POST['pacwtt-interval-id'] );
	// Remove data from Database
	$result = $pacwtt_model_interval->delete_item( $interval_id );
	
	
	if ($result === FALSE) {
		// Database error
		$response = array( 'database_errors' => __('Database Error: Unable to delete the specified interval.', 'pacwtt-plugin' ) );
	}
	else
	{
		// Database success
		$response = array( 'success' => __('Record successfully deleted.', 'pacwtt-plugin' ) );
	}
	
	// Response and exit
	echo json_encode($response);
	wp_die();
}
add_action( 'wp_ajax_pacwtt_delete_interval', 'pacwtt_delete_interval' );



/** Ajax callaback: get intervals' table */
function pacwtt_interval_table_update() {
	
	// Parameters
	$activity_id = intval( $_POST['pacwtt-activity-id'] );
	
	// HTML Table
	echo pacwtt_interval_table( $activity_id );
	wp_die(); // this is required to terminate immediately and return a proper response (exiting from admin-ajax.php )
}
add_action( 'wp_ajax_pacwtt_interval_table_update', 'pacwtt_interval_table_update' );

/** Intervals' list HTML table by activity */
function pacwtt_interval_table ( $activity_id )
{
	// Database Models
	$pacwtt_model_interval = new PACWTT_Model_Interval();

	$results = $pacwtt_model_interval->get_activity_items ($activity_id);
	
	// Table Headers
	$html_table = '<table id="pacwtt-intervals-table" class="widefat fixed"><thead><tr>';
	$html_table .= '<th>'.__('Day of the week', 'pacwtt-plugin' ).'</th>';
	$html_table .= '<th>'.__('Start', 'pacwtt-plugin' ).'</th>';
	$html_table .= '<th>'.__('Finish', 'pacwtt-plugin' ).'</th>';
	$html_table .= '<th>'.__('Description', 'pacwtt-plugin' ). '</th>';
	$html_table .= '<th>'.__('Operations', 'pacwtt-plugin' ). '</th>';
	$html_table .= '</tr></thead><tbody>';

	// Table Data
	if ( count( $results ) > 0) {
		foreach ($results as $interval) {
			$html_table .= '<tr>';
			$html_table .= '<td>' . $pacwtt_model_interval->dow_map[$interval['weekday']] .'</td>';
			$html_table .= '<td>' . esc_html( $interval['time_start'] ) .'</td>';
			$html_table .= '<td>' . esc_html( $interval['time_end'] ).'</td>';
			$html_table .= '<td>' . esc_html( $interval['description'] ).'</td>';
			$html_table .= '<td><a id="'.$interval['id'].'" class="pacwtt-interval-delete-link" href="javascript:void(0)">'.__('Delete', 'pacwtt-plugin' ).'</a></td>';
			$html_table .= '</tr>';
		}
	}
	else
	{
		$html_table .= "<tr><td align='center' colspan ='5'>" . __('No results found.', 'pacwtt-plugin' ) . "</td></tr>";
	}
	$html_table .= '</tbody></table>';
	
	return $html_table;
}