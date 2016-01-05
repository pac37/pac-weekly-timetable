<?php

/*********************************
 * Database model: Activity table
 *********************************/

require_once( plugin_dir_path(__FILE__).'class-pacwtt-model.php');

class PACWTT_Model_Activity extends PACWTT_Model {
	
	function __construct( $table = PACWTT_DB_TABLE_ACTIVITY ) {
		parent::__construct($table);
	}
	
	/** Insert item, in MySQL */
	public function insert_item ( $name, $description ){
		global $wpdb;
	
		$data = array(
				'name' => $name,
				'description' => $description
		);
		$format = array("%s", "%s");
		$result = $wpdb->insert( $this->table, $data , $format );
	
		return $result;
	}
}