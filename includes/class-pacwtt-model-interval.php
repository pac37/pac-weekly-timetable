<?php

/*********************************
 * Database model: Interval Table
 *********************************/

require_once( plugin_dir_path(__FILE__).'class-pacwtt-model.php');

class PACWTT_Model_Interval extends PACWTT_Model {
	
	var $dow_map = '';
	
	function __construct( $table = PACWTT_DB_TABLE_INTERVAL ) {
		parent::__construct( $table );
		// Days' name mapping
		$this->dow_map = array(
				__('Sunday', 'pacwtt-plugin' ), 
				__('Monday', 'pacwtt-plugin' ),  
				__('Tuesday', 'pacwtt-plugin' ), 
				__('Wednesday', 'pacwtt-plugin' ), 
				__('Thursday', 'pacwtt-plugin' ), 
				__('Friday', 'pacwtt-plugin' ), 
				__('Saturday', 'pacwtt-plugin' )
		);
	}
	
	/** Insert item, in MySQL */
	public function insert_item ( $activity_id, $weekday, $time_start_h, $time_start_m, $time_end_h, $time_end_m, $description ){
		global $wpdb;
		
		$data = array(
				'id_activity' => intval( $activity_id ),
				'weekday' => intval( $weekday ),
				'time_start' => intval( $time_start_h ) . ':' . intval( $time_start_m ),
				'time_end' => intval( $time_end_h ) . ':' .  intval( $time_end_m ),
				'description' => $description
		);
		$format = array("%d", "%d", "%s", "%s", "%s", "%s");
		$result = $wpdb->insert( $this->table, $data , $format );
		
		return $result;
	}

	/** Retrieve all items, sorted and with name of the days */
	public function get_activity_items ( $activity_id, $day_name = false, $remove_seconds = true ) {
		global $wpdb;
		
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $this->table WHERE id_activity = %d ORDER BY weekday,time_start ASC", $activity_id ), ARRAY_A );
		
		
		
		if (true == $day_name)
			// Map day number to day name
			foreach ($results as $id => $value )
				$results[$id]['weekday'] = $this->dow_map[$value['weekday']]; 
		
		if (true == $remove_seconds)
			// time format: 24h, no leading zeroes, no seconds
			foreach ($results as $id => $value ) {
				$results[$id]['time_start'] = date( 'G:i', strtotime($results[$id]['time_start']) );
				$results[$id]['time_end'] = date( 'G:i', strtotime($results[$id]['time_end']) );
			}
		
		
		return $results;
	}
	
	/** Delete all the items ralted to a specific activity */
	public function delete_activity_items ( $activity_id ) {
		global $wpdb;
		
		$result = $wpdb->delete(
				$this->table,
				[ 'id_activity' => $activity_id ],
				[ '%d' ]
		);
		
		return $result;
	}
}