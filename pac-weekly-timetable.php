<?php
/*
Plugin Name: PAC Weekly Timetable
Plugin URI: https://github.com/pac37/pac-weekly-timetable
Description: Timetables generation with admin interface and shortcodes
Version: 0.7.1
Auothor: Pietro Amedeo Cigoli
Author URI: http://www.metapac.it
Text Domain: pacwtt-plugin
Domain Path: /languages
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/******************
 * 
 * Global Constant
 * 
 ******************/
global $wpdb;

$pacwtt_plugin_dir_path = plugin_dir_path( __FILE__ );
$pacwtt_plugin_url = plugin_dir_url( __FILE__ );

/****************
 * 
 * External code
 * 
 ****************/

// Database Model classes
require_once( $pacwtt_plugin_dir_path . 'includes/class-pacwtt-model-activity.php');
require_once( $pacwtt_plugin_dir_path . 'includes/class-pacwtt-model-interval.php');

// Activities table class
require_once( $pacwtt_plugin_dir_path . 'includes/class-pacwtt-activity-list-table.php');


/********************
 * 
 *  Global Variables
 *  
 ********************/

// Database Model Default Tables
define('PACWTT_DB_TABLE_ACTIVITY', $wpdb->prefix . 'pacwtt_activity' );
define('PACWTT_DB_TABLE_INTERVAL', $wpdb->prefix . 'pacwtt_interval' );

/*******************
 * 
 *  Plugin includes
 *  
 *******************/

require_once( plugin_dir_path(__FILE__) . 'includes/shortcodes.php'); 	// Shortcodes implementation
require_once( plugin_dir_path(__FILE__) . 'includes/scripts.php');		// Ajax (Intervals' Table), CSS (Shortcodes) 


$pacwtt_activities_list_table = '';						 // Activities Table: must be instatiated later, after the option panel's definition
$pacwtt_option_layout = array ('text', 'time_boxes'); 	 // Availables timetable's formats

/************************************************************************************
 * 
 * Action hooks: Activation, Deactivation, initialization, Internationalization, CSS
 * 
 ************************************************************************************/

/** Plugin installation */
function pacwtt_install() {
	global $wpdb;
	
	// Version Compare
	global $wp_version;
	$wp_min_version = "4.0";
	
	if (version_compare($wp_version, $wp_min_version, "<")) {
		deactivate_plugins(basename(__FILE__));	// Unsupported WP Version: Plugin Deactivion
		wp_die( sprintf(__('This plugin requires WordPress version %s or higher.','pacwtt-plugin'), $wp_min_version) );
	}
	
	// Custom Tables
	$pacwtt_db_version = '1.0';
	$charset_collate = $wpdb->get_charset_collate();

	$sql_create_table_activity = "CREATE TABLE " . PACWTT_DB_TABLE_ACTIVITY . " (
	  			      id int(10) NOT NULL AUTO_INCREMENT,
	                              name varchar(32) NOT NULL,
	                              description varchar(255) NOT NULL,
	                              PRIMARY KEY  (id),
				      UNIQUE (name)
	                              ) $charset_collate;";

	$sql_create_table_interval = "CREATE TABLE " . PACWTT_DB_TABLE_INTERVAL . " (
	 			      id int(10) unsigned NOT NULL AUTO_INCREMENT,
	 			      id_activity int(10) unsigned NOT NULL COMMENT 'Foreign key',
	 			      weekday tinyint(4) NOT NULL COMMENT 'a number from 0 to 6, starting sunday (php format)',
	 	                      time_start time NOT NULL,
	 			      time_end time NOT NULL COMMENT 'must be greater them time_start, and less then 24:00',
	 			      description varchar(255) NOT NULL COMMENT 'A descriptive note.',
	 			      PRIMARY KEY  (id)
				      ) $charset_collate;";

	// MySQL table creation/upgrade
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
	dbDelta($sql_create_table_activity);
	dbDelta($sql_create_table_interval);

	// Save the table structure version
	add_option("pacwtt_db_version", $pacwtt_db_version);
}
register_activation_hook( __FILE__ , 'pacwtt_install' );

/** Plugin uninstallation */
function pacwtt_uninstall() {
	global $wpdb;
	
	// Called on plugin deletion (NOT on deactivation) 
	// If uninstall/delete not called from WordPress then exit
	if (!defined('ABSPATH') && ! defined('WP_UNINSTALL_PLUGIN' ))
		exit();
	
	// Delete user options
	delete_option('pacwtt_option_layout');
	delete_option('pacwtt_option_monday');
	delete_option('pacwtt_option_css');

	// Delete plugin info option
	delete_option('pacwtt_db_version');

	// Remove all plugin's options
	$sql_remove_table = "DROP TABLE " . PACWTT_DB_TABLE_ACTIVITY . ";";
	$wpdb->query($sql_remove_table);
	
	$sql_remove_table = "DROP TABLE " . PACWTT_DB_TABLE_INTERVAL . ";";
	$wpdb->query($sql_remove_table);
	
	// TODO: verify if it is necessary
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
}
register_uninstall_hook( __FILE__ , 'pacwtt_uninstall' );

function pacwtt_deactivate() {
	// Nothing to do
}
register_deactivation_hook( __FILE__ , 'pacwtt_deactivate' );

/** Plugin initialization */
function pacwtt_init() {
	// placeholder
}
add_action( 'init', 'pacwtt_init');

/** Plugin l10n */
function pacwtt_load_plugin_textdomain() {
	global $pacwtt_plugin_dir_path;
	// $plugin_rel_path relative path from WP_PLUGIN_DIR to .mo files directory
	// i.e. 'pac-weekly-timetable/languages'
	// every .mo file name must follow the format: <namespace>-<locale>.mo
	// i.e. pacwtt-plugin-it_IT.mo for the italian translation
	
	$plugin_rel_path = dirname(plugin_basename(__FILE__)) . '/languages';
	$loaded = load_plugin_textdomain( 'pacwtt-plugin', false, $plugin_rel_path );
}
add_action( 'plugins_loaded', 'pacwtt_load_plugin_textdomain' );

/*********************************************************
 * 
 * Actions Hooks: menu pages, subpages and options saving
 * 
 *********************************************************/

// Plugin menu
add_action('admin_menu','pacwtt_create_menu');
// Plugin options settings
add_action('admin_init','pacwtt_register_settings');

/** Plugin menu definitions */
function pacwtt_create_menu() {
	global $hook_all_activities;
	
	// Top Level Menu
	add_menu_page(
		'PACWTT',								// page_title
		'PACWTT',								// menu_title
		'administrator',						// capability required
		'pacwtt-main',							// handle/file
		'pacwtt_main',							// function
		'dashicons-schedule'					// icon url
	);

	// Submenu:
	// Activities table page
	$hook_all_activities = add_submenu_page(
		'pacwtt-main', 											// Parent
		'PACWTT - '. __('All Activities', 'pacwtt-plugin' ),	// page_title
		__('All Activities', 'pacwtt-plugin' ),					// menu_title
		
		'administrator',										// capability required

		'pacwtt-settings-all-activities',						// handle/file slug
		'pacwtt_settings_all_activities'						// function
	);
	add_action("load-$hook_all_activities", 'add_options' );	// screen option for WP_List_Table
	add_action("admin_enqueue_scripts", 'load_js'); //scripts needed for ajax table

	// Activities creation page
	add_submenu_page(
		'pacwtt-main', 												// Parent
		'PACWTT - ' . __('Add New Activity', 'pacwtt-plugin' ),		// page_title
		__('Add New', 'pacwtt-plugin' ),							// menu_title

		'administrator',											// capability required

		'pacwtt-settings-new-activities',							// handle/file
		'pacwtt_settings_new_activities'							// function
	);

	// Plugin default settings page
	add_submenu_page(
		'pacwtt-main', 												// Parent
		'PACWTT - ' . __('Options', 'pacwtt-plugin' ),				// page_title
		__('Options', 'pacwtt-plugin' ),							// menu_title

		'administrator',											// capability required

		'pacwtt-settings-options',	 								// handle/file
		'pacwtt_settings_options'									// function
	);

}

/** Adds top options slider to All activities page */
function add_options() { 
	global $pacwtt_activities_list_table;

	$option = 'per_page';
	$args = array(
		'label' => 'Activities',
		'default' => 5,
		'option' => 'activities_per_page'
	);
	add_screen_option( $option, $args);

	// the table must be defined at this point to enable the column hiding checkboxes
	$pacwtt_activities_list_table = new PACWTT_Activity_List_Table();
}

/** Adds the callback for the sliders options and put them in the usermeta table */ 
function activities_table_set_option($status, $option, $value) {
	return $value;
}
add_filter('set-screen-option', 'activities_table_set_option',10,3);

/*********************************
 * 
 * Menu pages rendering functions
 *
 *********************************/

/** "PACWTT" MENU PAGE */
function pacwtt_main(){
	echo '<h1>PACWTT - PAC Weekly Timetable Plugin</h1>';
	echo "<div class='wrap'><p>";
	_e( 'This plugin provides the ability to create simple weekly timetables. A timetable corresponds to an "Activity", which contains "Intervals" defined by a starting time, a finishing time and an optional description.' , 'pacwtt-plugin' );
	echo "</p></p>";
	_e( 'Every timetable can be displayed using the [pacwtt id=xx] shortcode, where xx is the activity\'s id value as shown in the "All Activities" page. The "Option" page allow to choose the timetables\' look, setting some default value for the shortcode and a user defined css.' , 'pacwtt-plugin'); 
	echo "</p></p>";
	_e( 'Note: this plugin is aimed to semplicity so it dosen\'t enforce too many controls over the data and doesn\'t ask for confirmations for delete operations and similar actions.' , 'pacwtt-plugin');
	echo "</p><p><h2>";
	_e('Details:', 'pacwtt-plugin');
	echo "</h2><ul>";
	echo "<li>".__( 'All Activities - list and manage available Activities, click one Activity to access its intervals.', 'pacwtt-plugin').'</li>';
	echo "<li>".__( 'Add New - create a new Activity.', 'pacwtt-plugin').'</li>';
	echo "<li>".__( 'Options - set shortcode\'s default options and an optional custom defined CSS.','pacwtt-plugin').'</li>';
	echo "</ul></p></div>";
	return;
}

/** "All Activities" MENU PAGE */
function pacwtt_settings_all_activities(){
	global $pacwtt_activities_list_table;

	$pacwtt_activities_list_table->prepare_items();
?>
	<h1><?php _e('All Activities' , 'pacwtt-plugin' ); ?></h1>
		<div class="wrap">
		<!--  Message Area -->
		<?php echo $pacwtt_activities_list_table->get_html_message(); ?>
		<!--  Activities List -->
		<form method="POST" >
		<?php $pacwtt_activities_list_table->display(); ?>
		</form>
	</div>	
<?php
}
	
/** "Add new" MENU PAGE */
function pacwtt_settings_new_activities(){
	// Database Models
	$pacwtt_model_activity = new PACWTT_Model_activity();
	
	echo '<h1>' . __( 'New Activity' , 'pacwtt-plugin' ) . '</h1>';
	echo "<div class='wrap'>";
	
	$name = '';
	$description = '';
	
	$message = '' ;
	
	if ( isset( $_POST['submit'] ) ) {
		// Form submitted
		// Chech submission origin
		
		if (check_admin_referer( 'pacwtt-activity-form' )) {			
			// Validation of sanitized input
			// Validation rule: no empty activity name.
			
			$name = sanitize_text_field( $_POST['pacwtt-activity-name']);
			$description = sanitize_text_field( $_POST['pacwtt-activity-description']);
			
			if ('' == $name) {
				$message = pacwtt_error_message( [ __( "Field 'name' can't be empty." , 'pacwtt-plugin' ) ] );
			}
			else {
				$result = $pacwtt_model_activity->insert_item( $name, $description );
				if ( FALSE === $result) {
					$message = pacwtt_error_message( [ __( "Database insertion failed." , 'pacwtt-plugin' ) ] );
				}
				else {
					// Success
					
					$message = pacwtt_updated_message( __( 'New Activity Added.' , 'pacwtt-plugin' ) );
					// Reset form
					$name = '';
					$description = '';
				}
			}
		} else {
			wp_die( __( 'Insufficient privileges. Operation aborted.', 'pacwtt-plugin' ) );
		}
	}
	
?>
	<!--  Message Area -->
	<?php echo $message; ?>
	<!--  Activity Form -->
	<form id="pacwtt-activity-form" method="POST" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<hr />
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="pacwtt-activity-name">
							<?php _e( 'Name' , 'pacwtt-plugin' ); ?>:<br />
							<em><?php _e( '(HTML tags are not allowed)', 'pacwtt-plugin' )?></em>
						</label>
					</th>	
					<td>
						<input type='text' id='pacwtt-activity-name' name='pacwtt-activity-name' value='<?php echo $name; ?>' maxlength='32' size='32'>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="pacwtt-activity-description">
							<?php _e( 'Description' , 'pacwtt-plugin' ); ?>:<br />
							<em><?php _e( '(HTML tags are not allowed)', 'pacwtt-plugin' )?></em>
						</label>
					</th>
					<td>
						<input type='text' id='pacwtt-activity-description' name='pacwtt-activity-description' value='<?php echo $description; ?>' maxlength='255' size='64'>
					</td>			
				</tr>
			</tbody>
		</table>
		<?php 
		wp_nonce_field( 'pacwtt-activity-form' );
		submit_button( 'Add', 'primary' ); ?>
	</form>
<?php
	echo "</div>";
}

/******************************
 * 
 * Plugin's options management
 * 
 ******************************/

function pacwtt_register_settings(){
	global $pacwtt_plugin_dir_path;
	
	// Options: all the available ones, and their sanitize callbacks
	register_setting( 'pacwtt-options-group', 'pacwtt_option_layout' );
	register_setting( 'pacwtt-options-group', 'pacwtt_option_monday' );
	register_setting( 'pacwtt-options-group', 'pacwtt_option_css', 'pacwtt_sanitize_options_css');

	// Options: default values (after activation)
	$pacwtt_option_layout = get_option('pacwtt_option_layout');
	$pacwtt_option_monday = get_option('pacwtt_option_monday');
	$pacwtt_option_css = get_option('pacwtt_option_css');

	if(empty($pacwtt_option_layout) && empty($pacwtt_option_monday) && empty($pacwtt_option_css)){
		update_option('pacwtt_option_layout', 'text');
		update_option('pacwtt_option_monday', 'on');
		update_option('pacwtt_option_css', file_get_contents( $pacwtt_plugin_dir_path . "css/default.css"));
	}
}

function pacwtt_sanitize_options_css( $input ) {
	// Options: Sanitize (removing unwanted inputs), removes any html realted stuff
	return wp_strip_all_tags( $input );
}

/** "Options" MENU PAGE */
function pacwtt_settings_options(){
	global $pacwtt_plugin_dir_path;
	
	// Options: current values
	$pacwtt_option_layout = get_option('pacwtt_option_layout');
	$pacwtt_option_monday = get_option('pacwtt_option_monday');
	$pacwtt_option_css = get_option('pacwtt_option_css');
	
	// Options: input form
	?>
	<h1><?php _e( 'Options' , 'pacwtt-plugin' ); ?></h1>
	<div class="wrap">
		<form method="post" action="options.php">
			<?php 
				settings_fields( 'pacwtt-options-group'); 
				do_settings_sections( 'pacwtt-options-group' );
			?>
			<hr />
			<h3><?php _e('Shortcodes settings', 'pacwtt-plugin') ?></h3>
			<p><?php _e('Defaut values', 'pacwtt-plugin') ?></p>
			<table class="form-table">
			  <tbody>
				<tr valign="top">
					<th scope="row"><?php _e('Layout' , 'pacwtt-plugin'); ?></th>
					<td>
						<label><input type="radio" id="pacwtt_option_layout[text]" name="pacwtt_option_layout" <?php if($pacwtt_option_layout == 'text') echo 'checked="checked"'; ?> value="text" /><?php _e( 'Text' , 'pacwtt-plugin');  ?></label>
						<br />
						<label><input type="radio" id="pacwtt_option_layout[time_boxes]" name="pacwtt_option_layout" <?php if($pacwtt_option_layout == 'time_boxes') echo 'checked="checked"'; ?> value="time_boxes" /><?php _e( 'Time Boxes' , 'pacwtt-plugin');  ?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Week starts on monday', 'pacwtt-plugin') ?></th>
					<td>
						<fieldset>
						<legend class="screen-reader-text"><span><?php _e('Week starts on monday', 'pacwtt-plugin') ?></span></legend>
						<label for="pacwtt_option_monday">
							<input type="checkbox" id="pacwtt_option_monday" name="pacwtt_option_monday" <?php if( 'on' == $pacwtt_option_monday ) echo 'checked="checked"'; ?> />					
						</label>
						</fieldset>
					</td>
				</tr>
				</tbody>
			</table>
			<hr />
			<h3><?php _e('CSS Settings', 'pacwtt-plugin') ?></h3>
			<p><?php _e('Customize the look of the timetables', 'pacwtt-plugin') ?></p>
			<table class="form-table">
			  <tbody>	
				<tr valign="top">
					<th scope="row">
						<label for="pacwtt_option_css">
							<?php _e( 'Custom CSS', 'pacwtt-plugin' ) ?>
						</label>
						<br />
						<em><?php _e( '(HTML tags are not allowed)', 'pacwtt-plugin' )?></em>
					</th>
					<td>
						<textarea rows="10" cols="50" id="pacwtt_option_css" name="pacwtt_option_css" style="font-family:monospace;" ><?php echo esc_textarea($pacwtt_option_css); ?></textarea>					
					</td>
				</tr>
			  </tbody>
			</table>
			<?php submit_button(); ?>	
		</form>
	</div><!--/wrap -->
	<?php
}

/*******************
 * 
 * Helper Functions
 * 
 *******************/

/** Forms: html error message */
function pacwtt_error_message( $messages ) {
	$html_error = "<div class='error'><h3>" . __( 'Error' , 'pacwtt-plugin' ) . ":</h3><p><ul>";
	foreach ( $messages as $m) {
		$html_error .= "<li>$m</li>";
	}
	$html_error .= '</ul></p></div>';
	
	return $html_error;
}

/** Forms: html update ( i.e. success) message */
function pacwtt_updated_message( $message ) {
	$html_update = "<div class='updated'><h3>" . __( 'Success' , 'pacwtt-plugin' ) . ":</h3><p>$message</p></div>";

	return $html_update;
}