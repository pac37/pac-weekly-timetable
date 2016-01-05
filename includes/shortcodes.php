<?php
/********************
 * utility functions
 ********************/

/** Rotate *associative* array to the left ( firs element enter from the right ) */
function array_assoc_rol( $a ) {

	$keys = array_keys($a);
	$val = $a[$keys[0]];
	unset($a[$keys[0]]);
	$a[$keys[0]] = $val;

	return $a;
}

/** Return the first Three letters of the specified day name*/
function shortcode_dnsize_three( $day ) {
	return substr($day,0,3);
}

/****************************
 * Shortcodes for Activities
 ****************************/

/*
 * Shortcode "pacwtt" has the following attributes:
 * 
 * id (mandatory): activity id
 * layout (optional, default from global options): one of the availables table layout, i.e. "text" or "time_boxes"
 * dnsize (optional, default 'full'): one of the available lenght of the name of the days, i.e  "three" or "full"   
 * 
 */

add_shortcode( 'pacwtt', 'pacwtt_shortcode_handler' );
function pacwtt_shortcode_handler( $atts, $content = null ) {

	// Database Models
	$pacwtt_model_activity = new PACWTT_Model_activity();
	$pacwtt_model_interval = new PACWTT_Model_Interval();
	
	
	$days_sequence = '';

	if(!isset($atts['id'])) {
		// Insufficient parameters
		return '<b>' . __("Error: no activity's id specified.", 'pacwtt-plugin' ) . '</b>';
	}
	
	//Attributes default
	$attributes_default = array(
			'id' => '',
			'layout' => get_option('pacwtt_option_layout'),
			'dnsize' => 'full'
	);	
	
	$options = shortcode_atts( $attributes_default, $atts );
	
	// "id" parameter validation and fetch
	$activity_data = $pacwtt_model_activity->read_item($options['id']);
	if ( NULL === $activity_data ) {
		//  Non existent 'id' activity
		return '<b>' . __("Error: requested id not found.", 'pacwtt-plugin' ) . '</b>';
	}	
	$interval_data = $pacwtt_model_interval->get_activity_items($options['id'], false);
	
	// "size" parameter validation
	$days_sequence = $pacwtt_model_interval->dow_map;

	switch ( $options['dnsize'] ) {
		case 'three':
			$days_sequence = array_map( 'shortcode_dnsize_three',$days_sequence );
			break;
		case 'full':
			// Do nothing
			break;
		default:
			// Invalid scortcode parameter
			return '<b>' . sprintf(
				/* translators: %s value of 'dnsize' shortcode setting */
				__("Error: dnsize=%s parameter value not allowed.", 'pacwtt-plugin'),
				$options['dnsize'] ) . '</b>';
	}
	$days_sequence = array_flip( $days_sequence ); // day_name => day_index
		
	// "week starts on monday" global option 
	if ( 'on' == get_option('pacwtt_option_monday')) {
		$options['monday'] = true;
	} else {
		$options['monday'] = false;
	}
	
	if ($options['monday'] == false){
		// sunday
		// nothing to do
	}
	elseif ($options['monday'] == true) {
		// Monday first (rotate left)
		$days_sequence = array_assoc_rol( $days_sequence );
	}
	else {
		//  Should not be executed
		return '<b>' . __("Error: global option 'monday' value not valid.", 'pacwtt-plugin' ) . '</b>';
	}
	
	// Layout parameter validation
	if ( 'text' != $options['layout'] && 'time_boxes' != $options['layout'] ) {
		// Invalid scortcode parametr
		return '<b>' . sprintf(
				/* translators: %s value of 'layout' plugin/shortcode setting */
				__("Error: layout=%s parameter value not allowed.", 'pacwtt-plugin'),
				$options['layout'] ) . '</b>';
	}
	
	switch ( $options['layout'] ) {
		case 'text':
			$html_output = pacwtt_shortcode_text( $options['id'], $interval_data, $days_sequence );
			break;
		case 'time_boxes':
			$html_output = pacwtt_shortcode_time_boxes( $options['id'], $interval_data, $days_sequence );
			break;
		default:
			wp_die( sprintf(__('Fatal Error: layout=%s parameter value not allowed.', 'pacwtt-plugin'), $options['layout'] ) );
	}
	
	//Output
	
	wp_enqueue_style( 'pacwtt-css-tables' );
	return $html_output;
}

/****************************
 *  Timetable implementation
 ****************************/

/** Option: "Time Boxes" layout */
function pacwtt_shortcode_time_boxes( $activity_id, $interval_data, $days_sequence ) {
	
	//Build 2d array from data
	
	$data_matrix = array();
	$weekday_pos = array_combine(range(0, 6), array_fill(0, 7, 0));
	
	foreach ( $interval_data as $interval ) {
		$day = $interval['weekday'];
		
		$data_matrix[$weekday_pos[$day]][$day] = '<span class="pacwtt-int-time">' . esc_html( $interval['time_start'] ) . '&nbsp;&ndash;&nbsp;' . esc_html( $interval['time_end'] ) . '</span><br />';
		$data_matrix[$weekday_pos[$day]][$day] .= '<span class="pacwtt-int-description">' . esc_html( $interval['description'] ) . '</span>';
		$weekday_pos[$day]++;
	}
	
	// Build html intervals output
	
	$html_table_intervals = '';
	foreach ($data_matrix as $row => $row_data) {
		$row_odd = $row % 2;
		$html_table_intervals .= '<tr class="pacwtt-int-row-' . $row_odd . ' ">';
		
		foreach ( $days_sequence as $day_name => $day_num ) {
			
			if (isset($data_matrix[$row][$day_num])) {
				$html_table_intervals .= '<td class="pacwtt-int-data">';
				$html_table_intervals .= $data_matrix[$row][$day_num];
			}
			else { 
				$html_table_intervals .= '<td class="pacwtt-int-empty">';
				$html_table_intervals .= '&nbsp;';							// separator between time_start and time_end
			}
			
			$html_table_intervals .= '</td>';
		}
		
		$html_table_intervals .= '<tr>';
	}
	
	// Build html days (header) output
	
	$html_table_days = '<tr class="pacwtt-day-row">';
	foreach ( $days_sequence as $day_name => $day_num ) {
		$html_table_days .= '<th class="pacwtt-day-data-' . $day_num . '"><span class="pacwtt-day-name">' . $day_name .'</span></th>';
	}
	$html_table_days .= "</tr>";
	
	$html_table = '<table id="pacwtt-time-boxes-' . $activity_id . '" class="pacwtt-time-boxes"><thead class="pacwtt-day">' . $html_table_days .'</thead><tbody class="pacwtt-int">' . $html_table_intervals . '</tbody></table>';
	
	return $html_table;
}

/** Options: "Text" Layout */
function pacwtt_shortcode_text( $activity_id, $interval_data, $days_sequence ) {
	
	//Build 2d table for data processing
	$data_matrix = array(array());
	foreach ($days_sequence as $day_name => $day_num ) $data_matrix[$day_num] = array(); 
	 
	$weekday_pos = array_combine(range(0, 6), array_fill(0, 7, 0));
	
	foreach ( $interval_data as $interval ) {
		$day = $interval['weekday'];
		
		$data_matrix[$day][$weekday_pos[$day]] = '<td class="pacwtt-int-time"><span>' . str_pad(esc_html( $interval['time_start'] ), 5," ",STR_PAD_LEFT). '&nbsp;&ndash;&nbsp;' . str_pad(esc_html( $interval['time_end'] ), 5," ",STR_PAD_LEFT). '</span></td>';
		$data_matrix[$day][$weekday_pos[$day]] .= '<td class="pacwtt-int-description"><span>' . esc_html( $interval['description'] ). '</span></td>';
		
		$weekday_pos[$day]++;
	}
	
	// Build html output
	$html_table_data = '';
	foreach ( $days_sequence as $day_name => $day_num ) {
		$html_table_data .= '<tbody class="pacwtt-day-' . $day_num . '">';
		$html_table_data .= '<tr class="pacwtt-day-name"><th colspan="2"><span>' . $day_name .'</span></th></tr>';
		$row_num = 0;
		foreach ( $data_matrix[$day_num] as $data ) {
			$row_odd = $row_num++ % 2;
			$html_table_data .= '<tr class="pacwtt-day-data-' . $row_odd . '">' . $data . '</tr>';
		}
		
		$html_table_data .= '</tbody>';
	}
	
	$html_table = '<table id="pacwtt-text-' . $activity_id . '" class="pacwtt-text">' . $html_table_data . '</table>';
	return $html_table;
}
