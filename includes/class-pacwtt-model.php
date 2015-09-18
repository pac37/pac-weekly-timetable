<?php

/***************************************************
 * Database model: generic table, common operations
 ***************************************************/

class PACWTT_Model {
	
	var $table = '';
	
	function __construct( $table ){
		$this->table = $table;
	}
	
	/** Counts items listed into the table */
	public function count_items() {
		global $wpdb;
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table" );
		return $total;
	}
	
	/** Get selected items from MySQL*/
	public function get_items($limit_offset = '', $limit_count = '' )
	{
		global $wpdb;
	
		$sql_order = '';
		if (isset($_GET['orderby'])) {
			$sql_order = 'ORDER BY '.$_GET['orderby'].' '.$_GET['order'];
		}
	
		$sql_limit = '';
		if ( is_int( $limit_offset ) && is_int( $limit_count ) ) {
			$sql_limit = "LIMIT $limit_offset, $limit_count";
		}
		else {
			wp_die('Error: Pagination malfunction');
		}
		
		$sql_query = "SELECT * FROM $this->table $sql_order $sql_limit";
		$results = $wpdb->get_results( $sql_query , ARRAY_A );
		return $results;
	}
	
	/** Read selected item from MySQL */
	public function read_item ($id) {
		global $wpdb;
		$activity = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table WHERE ID = %d", $id ), ARRAY_A );
		return $activity;
	}
	
	/** Remove item tuple by unique 'id' from MySQL */
	public function delete_item ( $id ) {
		global $wpdb;
	
		$wpdb->delete(
				$this->table,
				[ 'id' => $id ],
				[ '%d' ]
		);
	}
	
	/** Update an tuple by id in MySQL */
	public function update_item ($id, $data_values, $data_formats ) {
		global $wpdb;
		$result = $wpdb->update( $this->table,
				$data_values,
				array("id" => $id),
				$data_formats,
				array("%d")
		);
		return $result;
	}
}