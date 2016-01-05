<?php
//The actual page does not exists: wordpress gives a 404 error code that must be removed, giving a 200 OK insted
global $wp_query;
if ($wp_query->is_404) {
	$wp_query->is_404 = false;
}
header("HTTP/1.1 200 OK");
header('Content-type: text/css');
header("Cache-Control: must-revalidate");
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 1209600) . ' GMT');
echo get_option('pacwtt_option_css');
