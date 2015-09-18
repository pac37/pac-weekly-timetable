<?php
	// Called on plugin deletion (NOT on deactivation) 
	// If uninstall/delete not called from WordPress then exit
	if (!defined('ABSPATH') && ! defined('WP_UNINSTALL_PLUGIN' ))
		exit();
	
	// Delete option from options table
	delete_option('pacwtt_option_layout');
	delete_option('pacwtt_option_monday');

	// Remove any additional options and custom tables
	delete_option('pacwtt_db_version');

	global $wpdb;
	$table_activity = $wpdb->prefix . 'pacwtt_activity';
	$table_interval = $wpdb->prefix . 'pacwtt_intervall';

	$sql_remove_tables = "DROP TABLE $table_activity ;";
	$sql_remove_tables .= "DROP TABLE $table_interval ;";

	$wpdb->query($sql_remove_tables);

	// TODO: verify seams to be not necessary)
	require_once(ABSPATH . 'wp-admin/includes/upgrade-php');
	dbDelta($sql_remove_tables);
?>
