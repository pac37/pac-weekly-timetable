// Activity form management
jQuery(document).ready(function($){
	// Global object injected from WordPress
	// pacwtt_vars.ATTRIBUTE
	
	// clear the page
	function pacwtt_reset_page() {
		pacwtt_clear_form();
		pacwtt_clear_messages();
		return;
	}
	
	// clear the new interval form
	function pacwtt_clear_form() {
		$("#pacwtt-new-interval-form")[0].reset();
		return
	}
	
	// clear all the messages area
	function pacwtt_clear_messages() {
		$('#pacwtt-form-messages').html('');
		$('#pacwtt-list-messages').html('');
		return;
	}
	
	//Error message (HTML)
	function pacwtt_error_message( id, message ) {
		$( id ).html('<div class=\"error\"><h3>' + pacwtt_vars.error + ':</h3><p>' + message + '</p></div>');
		return;
	}
	
	//Success message (HTML)
	function pacwtt_success_message( id, message ) {
		$( id ).html('<div class=\"updated\"><h3>' + pacwtt_vars.success + ':</h3><p>' + message + '</p></div>');
		return;
	}
	
	
	function pacwtt_update_intervals_table() {
		// Global data
		activity_id = $("#pacwtt-activity-id").val()
		
		// initialization
		data = {
					'action': 'pacwtt_interval_table_update', // Handler (function)
					'pacwtt-activity-id': activity_id
				};
		jQuery.post(
				ajaxurl,	// ajax handler url (admin side wp-admin/admin-ajax.php)
				data, 		// function and other data
				function(response) {
					jQuery("#pacwtt-intervals-list").html(response);
				});
	};
	
	// init only if we are in page=pacwtt-settings-all-activities&action=edit_intervals
	if ($("#pacwtt-activity-id").length > 0) { 
		// Page access: // Update intervals list
		pacwtt_update_intervals_table();
	}
	

	// submit a new interval
	$("#pacwtt-new-interval-form").submit(function(){
		
		// Waiting: Animated gif "loading" on, button disabled
		$('#pacwtt-loading').show();
		$('#pacwtt-activity-submit').attr( 'disabled' , true );
		
		activity_id = $("#pacwtt-activity-id").val()
		// Interval data
		var weekday = $("#pacwtt-weekday").val();
		var time_start_h = $("#pacwtt-time-start-h").val();
		var time_start_m = $("#pacwtt-time-start-m").val();
		var time_end_h = $("#pacwtt-time-end-h").val();
		var time_end_m = $("#pacwtt-time-end-m").val();
		var description = $("#pacwtt-description").val();
		
		data = {
				'action': 'pacwtt_new_interval', 			// Handler	(php function)
				'pacwtt_nonce': pacwtt_vars.pacwtt_nonce,	// Nonce	(resend received nonce)
				'pacwtt-activity-id': activity_id,
				'pacwtt-weekday': weekday,
				'pacwtt-time-start-h': time_start_h,
				'pacwtt-time-start-m': time_start_m,
				'pacwtt-time-end-h': time_end_h,
				'pacwtt-time-end-m': time_end_m,
				'pacwtt-description': description
		};
		
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(
				ajaxurl,	// ajax handler url (admin side wp-admin/admin-ajax.php)
				data, 		// function and other data
				function(response_string) {
					var response = JSON.parse(response_string);
					if ( 'nonce_errors' in response) {
						pacwtt_clear_messages();
						pacwtt_error_message( '#pacwtt-form-messages', response['nonce_errors'] );
					} else if ('consistency_errors' in response) {
						pacwtt_clear_messages();
						pacwtt_error_message( '#pacwtt-form-messages', response['consistency_errors'] );
					} else if ('database_errors' in response) {
						pacwtt_clear_messages();
						pacwtt_error_message( '#pacwtt-form-messages', response['database_errors'] );
					} else {
						pacwtt_reset_page();
						pacwtt_update_intervals_table();
						pacwtt_success_message( '#pacwtt-form-messages', response['success'] ); 
					}
					
					// Animated gif "loading" off, button reenabled 
					$('#pacwtt-loading').hide();
					$('#pacwtt-activity-submit').attr( 'disabled' , false );
				});
		
		return false; // Prevents default
	});
	
	// delete exixting interval
	$("#pacwtt-intervals-list").on("click", "a.pacwtt-interval-delete-link", function() {
		 
		// Remove old table
		jQuery("#pacwtt-intervals-list").html('');
		
		// Waiting: Animated gif "loading" on, buttons (links) disabled
		$('#pacwtt-loading').show();
		$('a.pacwtt-interval-delete-link').attr( 'disabled' , true );
		
		interval_id = $(this).attr('id');
		data = {
					'action': 'pacwtt_delete_interval', 		// Handler ( php function)
					'pacwtt_nonce': pacwtt_vars.pacwtt_nonce,	// Nonce   ( resend received nonce )
					'pacwtt-interval-id': interval_id
		};
		 
		 
		 // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(
				ajaxurl,	// ajax handler url (admin side wp-admin/admin-ajax.php)
				data, 		// function and other data
				function(response_string) {
					var response = JSON.parse(response_string);
					if ( 'nonce_errors' in response) {
						pacwtt_clear_messages();
						pacwtt_error_message( '#pacwtt-list-messages', response['nonce_errors'] );
					} else if ('database_errors' in response) {
						pacwtt_clear_messages();
						pacwtt_error_message( '#pacwtt-list-messages', response['database_errors'] );
					} else {
						pacwtt_reset_page();
						pacwtt_success_message( '#pacwtt-list-messages', response['success'] );
					}
					
					// Animated gif "loading" off, buttons (links) enabled, updated table
					$('#pacwtt-loading').hide();
					pacwtt_update_intervals_table();
					$('a.pacwtt-interval-delete-link').attr( 'disabled' , false );
				});
		 
		 
		 return false; // Prevents default
	 });
});

