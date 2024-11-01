<?php
	
/*
Plugin Name: Snappy Surveys
Plugin URI: http://wordpressplugincourse.com/plugins/snappy-surveys
Description: Get to know your audience. Create simple surveys that capture annonymous data. See insightful statistics from your surveys.
Author: Joel Funk
Author URI: http://joelfunk.codecollege.ca
Version: 1.0.2
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: snappy-surveys
*/

/* !0. TABLE OF CONTENTS */

/*
	
	1. HOOKS
		1.1 - admin menus and pages
		1.2 - plugin activation
		1.3 - shortcodes
	
	2. SHORTCODES
		2.1 - ssp_register_shortcodes()
		2.2 - ssp_survey_shortcode()
		
	3. FILTERS
		3.1 - ssp_admin_menus()
		
	4. EXTERNAL SCRIPTS
		
	5. ACTIONS
		5.1 - ssp_create_plugin_tables()
		5.2 - ssp_activate_plugin()
		
	6. HELPERS
		6.1 - ssp_get_question_html()
		6.2 - ssp_survey_is_complete()
		6.3 - ssp_get_client_ip()
		
	7. CUSTOM POST TYPES
		7.1 - ssp_survey
	
	8. ADMIN PAGES
		8.1 - ssp_welcome_page()
		8.2 - ssp_stats_page()
	
	9. SETTINGS
	
	10. MISCELLANEOUS 

*/




/* !1. HOOKS */

// 1.1
// hint: register custom admin menus and pages
add_action('admin_menu', 'ssp_admin_menus');

// 1.2
// hint: plugin activation
register_activation_hook( __FILE__, 'ssp_activate_plugin' );

// 1.3
// hint: register shortcodes
add_action('init', 'ssp_register_shortcodes');

// 1.4
// hint: load external scripts
add_action('admin_enqueue_scripts', 'ssp_admin_scripts');
add_action('wp_enqueue_scripts', 'ssp_public_scripts');

// 1.5
// hint: register ajax functions
add_action('wp_ajax_ssp_ajax_save_response', 'ssp_ajax_save_response'); // admin user
add_action('wp_ajax_nopriv_ssp_ajax_save_response', 'ssp_ajax_save_response'); // website user
add_action('wp_ajax_ssp_ajax_get_stats_html', 'ssp_ajax_get_stats_html'); // admin user

// 1.6
// hint: custom admin columns
add_filter('manage_edit-ssp_survey_columns','ssp_survey_column_headers');
add_filter('manage_ssp_survey_posts_custom_column','ssp_survey_column_data',1,2);




/* !2. SHORTCODES */

// 2.1
// hint: registers custom shortcodes for this plugin
function ssp_register_shortcodes() {
	
	// hint: [ssp_survey id="123"]
	add_shortcode('ssp_survey', 'ssp_survey_shortcode');
	
}

// 2.2
// hint: displays a survey
function ssp_survey_shortcode( $args, $content='' ) {
	
	// setup our return variable
	$output = '';
	
	try {
	
		// begin building our output html
		$output = '<div class="ssp ssp-survey">';
		
		// get the survey id
		$survey_id = ( isset($args['id']) ) ? (int)$args['id'] : 0;
		
		// get the survey object
		$survey = get_post($survey_id);
		
		// IF the survey is not a valid ssp_survey post, return a message
		if( !$survey_id || $survey->post_type !== 'ssp_survey' ):
			
			$output .= '<p>The requested survey does not exist.</p>';
		// IF survey is valid...	
		else:
		
			// build form html
			$form = '';
			
			if(strlen($content)):
				$form = '
					<div class="ssp-survey-content">
					'. wpautop($content) .'
					</div>
				';
			endif;
				
			$submit_button = '';
			
			$responses = ssp_get_survey_responses( $survey_id );
			
			if( !ssp_survey_is_complete( $survey_id ) ):
				$submit_button = '
					<div class="ssp-survey-footer">
						<p><em>Submit your response to see the results of all '. $responses .' participants surveyed.</em></p>
						<p class="ssp-input-container ssp-submit">
							<input type="submit" name="ssp_submit" value="Submit Your Response" />
						</p>
					</div>
				';
			endif;
			
			$nounce = wp_nonce_field( 'ssp-save-survey-submission_'. $survey_id, '_wpnonce', true, false );
			
			$form .= '
				<form id="survey_'.$survey_id.'" class="ssp-survey-form">
					'. $nounce .'
					'. ssp_get_question_html( $survey_id ) . $submit_button .'
				</form>
			';
			
			// append form html to $output
			$output .= $form;
		
		endif;
		
		// close out output html div
		$output .= '</div>';
		
		// IF survey is complete don't display anything 
		// by setting output to empty string
		//if( ssp_survey_is_complete( $survey_id ) ) $output = '';
	
	} catch( Exception $e ) {
		
		// php error
		var_dump($e->getMessage());
		die();
		
	}
	
	// return output
	return $output;
	
}




/* !3. FILTERS */

// 3.1
// hint: registers custom plugin admin menus
function ssp_admin_menus() {
	
	/* main menu */
	
		$top_menu_item = 'ssp_welcome_page';
	    
	    add_menu_page( '', 'Snappy Surveys', 'manage_options', $top_menu_item, $top_menu_item, 'dashicons-chart-bar' );
    
    /* submenu items */
    
	    // welcome
	    add_submenu_page( $top_menu_item, '', 'Welcome', 'manage_options', $top_menu_item, $top_menu_item );
	    
	    // surveys
	    add_submenu_page( $top_menu_item, '', 'Surveys', 'manage_options', 'edit.php?post_type=ssp_survey' );
	    
	    // stats
	    add_submenu_page( $top_menu_item, '', 'Stats', 'manage_options', 'ssp_stats_page', 'ssp_stats_page' );

}

// 3.2
function ssp_survey_column_headers( $columns ) {
	
	// creating custom column header data
	$columns = array(
		'cb'=>'<input type="checkbox" />',
		'title'=>__('Survey'),	
		'responses'=>__('Responses'),
		'shortcode'=>__('Shortcode'),	
	);
	
	// returning new columns
	return $columns;
	
}

// 3.3
function ssp_survey_column_data( $column, $post_id ) {
	
	// setup our return text
	$output = '';
	
	switch( $column ) {
		
		case 'responses':
			$stats_url = admin_url('admin.php?page=ssp_stats_page&survey_id='. $post_id);
			$responses = ssp_get_survey_responses( $post_id );
			$output .= '<a href="'. $stats_url .'" title="See Survey Statistics">'. $responses .'</a>';
			break;
		case 'shortcode':
			$shortcode = '[ssp_survey id="'. $post_id .'"]';
			$output .= '<input onClick="this.select();" type="text" value="' . htmlspecialchars($shortcode) . '" readonly>';
			break;
		
	}
	
	// echo the output
	echo $output;
	
}




/* !4. EXTERNAL SCRIPTS */




/* !5. ACTIONS */

// 5.1
// hint: installs custom plugin database tables
function ssp_create_plugin_tables() {
	
	global $wpdb;
	
	// setup return value
	$return_value = false;
	
	try {
		
		// get the appropriate charset for your database
		$charset_collate = $wpdb->get_charset_collate();
	
		// $wpdb->prefix returns the custom database prefix 
		// orignally setup in your wp-config.php
	
		// sql for our custom table creation
		$sql = "CREATE TABLE {$wpdb->prefix}ssp_survey_responses (
			id mediumint(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			ip_address varchar(32) NOT NULL,
			survey_id mediumint(11) UNSIGNED  NOT NULL,
			response_id mediumint(11) UNSIGNED  NOT NULL,
			created_at TIMESTAMP DEFAULT '1970-01-01 00:00:00',
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE INDEX ix (ip_address,survey_id)
			) $charset_collate;";
		
		// make sure we include wordpress functions for dbDelta	
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			
		// dbDelta will create a new table if none exists or update an existing one
		dbDelta($sql);
		
		// return true
		$return_value = true;
	
	} catch( Exception $e ) {
		
		// php error
		var_dump($e->getMessage());
		die();
		
	}
	
	// return result
	return $return_value;
	
}

// 5.2
// hint: runs functions for plugin activation
function ssp_activate_plugin() {
	
	// create/update custom plugin tables
	ssp_create_plugin_tables();
	
}

// 5.3
// hint: loads external files into wordpress ADMIN
function ssp_admin_scripts() {
	
	// register scripts with WordPress's internal library
	wp_register_script('ssp-js-private', plugins_url('/js/private/ssp.js',__FILE__), array('jquery'),'',true);
	
	// add to que of scripts that get loaded into every admin page
	wp_enqueue_script('ssp-js-private');
	
}

// 5.4
// hint: loads external files into PUBLIC WEBSITE
function ssp_public_scripts() {
	
	// register scripts with WordPress's internal library
	wp_register_script('ssp-js-public', plugins_url('/js/public/ssp.js',__FILE__), array('jquery'),'',true);
	wp_register_style('ssp-css-public', plugins_url('/css/public/ssp.css',__FILE__));
	
	// add to que of scripts that get loaded into every admin page
	wp_enqueue_script('ssp-js-public');
	wp_enqueue_style('ssp-css-public');
	
}

// 5.5
// hint: ajax form handler for saving question responses 
// expects: $_POST['survey_id'] and $_POST['response_id']
function ssp_ajax_save_response() {
	
	$result = array(
		'status'=>0,
		'message'=>'Could not save response.',
		'survey_complete'=>false
	);
	
	try {
		
		$survey_id = (isset($_POST['survey_id'])) ? (int)$_POST['survey_id'] : 0;
		$response_id = (isset($_POST['response_id'])) ? (int)$_POST['response_id'] : 0;
		
		// verify nounce
		if( check_ajax_referer( 'ssp-save-survey-submission_'. $survey_id, false, false ) ):
		
			$saved = ssp_save_response( $survey_id, $response_id );
			
			if( $saved ):
			
				$survey = get_post( $survey_id );
				
				if( isset($survey->post_type) && $survey->post_type = 'ssp_survey'):
			
					$complete = true;
					
					$html = ssp_get_question_html( $survey_id );
				
					$result = array(
						'status'=>1,
						'message'=>'Response saved!',
						'survey_complete'=>$complete,
						'html'=>$html,
					);
					
					if( $complete ):
					
						$result['message']='Survey complete!';
					
					endif;
					
				else:
				
					$result['message'] .= ' Invalid survey.';
					
				endif;
			
			endif;
		
		endif;
	
	} catch( Exception $e ) {
		// php error
	}
	
	ssp_return_json( $result );
}

// 5.6
// hint: saves single question response
function ssp_save_response( $survey_id, $response_id ) {
	
	global $wpdb;
	
	$return_value = false;
	
	try {
		
		$ip_address = ssp_get_client_ip();
		
		// get question post object
		$survey = get_post( $survey_id );
		
		if( $survey->post_type == 'ssp_survey' ):
			
			// get current timestamp
			$now = new DateTime();
			$ts = $now->format('Y-m-d H:i:s');
			
			// query sql
			$sql = "
				INSERT INTO {$wpdb->prefix}ssp_survey_responses (ip_address,survey_id,response_id,created_at)
				VALUES ( %s, %d, %d, %s	)
				ON DUPLICATE KEY UPDATE survey_id = %d
			";
			
			// prepare query
			$sql = $wpdb->prepare($sql,$ip_address, $survey_id, $response_id, $ts, $survey_id);
			
			// run query
			$entry_id = $wpdb->query($sql);
			
			// IF response saved successfully...
			if( $entry_id ):
				
				// return true
				$return_value = true;
			
			endif;
		
		endif;
	
	} catch( Exception $e ) {
		
		// php error
		ssp_debug( 'ssp_save_response php error', $e->getMessage());
		
	}
	
	return $return_value;
	
}

// 5.7
function ssp_ajax_get_stats_html() {
	
	// setup default return variable
	$result = array(
		'status'=>0,
		'message'=>'Could not get stats html',
		'html'=>''
	);
	
	// get survey id from GET scope
	$survey_id = (isset($_POST['survey_id'])) ? (int)$_POST['survey_id'] : 0;
	
	// IF survey id is not 0
	if( $survey_id ):
	
		// build success result
		$result = array(
			'status'=>1,
			'message'=>'Stats html retrieved successfully!',
			'html'=> ssp_get_stats_html( $survey_id )
		);
	
	endif;
	
	// return json result
	ssp_return_json( $result );
	
}






/* !6. HELPERS */

// 6.1
// hint: returns html for survey question
function ssp_get_question_html( $survey_id, $force_results=false ) {
	
	$html = '';
	
	// get the survey post object
	$survey = get_post($survey_id);
	
	// IF $survey is a valid ssp_survey post type...
	if( $survey->post_type == 'ssp_survey' ):
	
		// get the survey question text
		$question_text = $survey->post_content;
		
		// setup our default question options
		$question_opts = array(
			'Strongly Agree'=>5,
			'Somewhat Agree'=>4,
			'Nuetral'=>3,
			'Somewhat Disagree'=>2,
			'Strongly Disagree'=>1
		);
		
		// check if the current user has already answered this survey question
		// or is force_results is true, treat as answered
		$answered = ($force_results) ? true : ssp_survey_is_complete( $survey_id );
		
		// default complete class is blank
		$complete_class = '';
			
		// setup our inputs html
		$inputs = '';
		
		if( !$answered ):
			
			// loop over all the $question_opts
			foreach( $question_opts as $key=>$value ):
			
				// append input html for each option
				$inputs .= '<li><label><input type="radio" name="ssp_question_'. $survey_id .'" value="'. $value .'" /> '. $key .'</label></li>';
			
			endforeach;
		
		else:
		
			// survey is complete, add a real complete class
			$complete_class = ' ssp-question-complete';
			
			// loop over all the $question_opts
			foreach( $question_opts as $key=>$value ):
			
				$stats = ssp_get_response_stats( $survey_id, $value );
			
				// append input html for each option
				$inputs .= '<li><label>'. $key .' - '. $stats['percentage'] .'</label></li>';
			
			endforeach;
		
		endif;
		
		$html .= '
		<dl id="ssp_'. $survey_id .'_question" class="ssp-question '. $complete_class .'">
			<dt>'. $question_text .'</dt>
			<dd><ul class="ssp-question-options">'. $inputs .'</ul></dd>
		</dl>';
	
	endif;
	
	return $html;
	
}

// 6.2
// hint: returns true or false depending on 
// whether or not the current user has answered the survey
function ssp_survey_is_complete( $survey_id ) {
	
	global $wpdb;
	
	// setup default return value
	$return_value = false;
	
	try {
		
		// get user ip address
		$ip_address = ssp_get_client_ip();
		
		//ssp_debug( 'ip address', $ip_address );
		
		// sql to check if this user has completed the survey
		$sql = "
			SELECT response_id FROM {$wpdb->prefix}ssp_survey_responses 
			WHERE survey_id = %d AND ip_address = %s
		";
		
		// prepare query
		$sql = $wpdb->prepare($sql, $survey_id, $ip_address);
		
		// run query, returns entry id if successful
		$entry_id = $wpdb->get_var($sql);
		
		// IF query worked and entry_id is not null...
		if( $entry_id !== NULL ):
		
			// set our return value to the entry_id
			$return_value = $entry_id;
		
		endif;
		
	} catch( Exception $e ) {
		// php error
	}
		
	// return result
	return $return_value;
	
}

// 6.3 
// hint: makes it's best attempt to get the ip address of the current user
function ssp_get_client_ip() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP')):
        $ipaddress = getenv('HTTP_CLIENT_IP');
    elseif(getenv('HTTP_X_FORWARDED_FOR')):
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    elseif(getenv('HTTP_X_FORWARDED')):
        $ipaddress = getenv('HTTP_X_FORWARDED');
    elseif(getenv('HTTP_FORWARDED_FOR')):
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    elseif(getenv('HTTP_FORWARDED')):
       $ipaddress = getenv('HTTP_FORWARDED');
    elseif(getenv('REMOTE_ADDR')):
        $ipaddress = getenv('REMOTE_ADDR');
    else:
        $ipaddress = 'UNKNOWN';
    endif;
    return $ipaddress;
}

// 6.4
// hint: returns json string and exits php processes
function ssp_return_json( $php_array ) {
	
	// encode result as json string
	$json_result = json_encode( $php_array );
	
	// return result
	die( $json_result );
	
	// stop all other processing 
	exit;
	
}

// 6.5
// hint: get's the statistics for a survey response
function ssp_get_response_stats( $survey_id, $response_id ) {
	
	// setup default return variable
	$stats = array(
		'percentage'=>'0%',
		'votes'=>0
	);
	
	try {
		
		// get responses for this item
		$item_responses = ssp_get_item_responses( $survey_id, $response_id);
		// get total responses for this survey
		$survey_responses = ssp_get_survey_responses( $survey_id );
		
		if( $survey_responses && $item_responses ):
			$stats = array(
				'percentage'=>ceil(($item_responses/$survey_responses)*100) .'%',
				'votes'=>$item_responses
			);
		endif;
		
	
	} catch( Exception $e ) {
		
		// php error
		ssp_debug( 'ssp_get_response_stats exception', $e);
		
	}
	
	// return stats
	return $stats;
	
	
}

// 6.7
function ssp_get_item_responses( $survey_id, $response_id ) {
	
	global $wpdb;
	
	$item_responses = 0;
	
	try { 
	
		// sql to check if this user has completed the survey
		$sql = "
			SELECT count(id) AS total FROM {$wpdb->prefix}ssp_survey_responses 
			WHERE survey_id = %d AND response_id = %d
		";
		
		// prepare query
		$sql = $wpdb->prepare($sql, $survey_id, $response_id);
		
		// run query, returns total item responses
		$item_responses = $wpdb->get_var($sql);
	
	} catch( Exception $e ) {
		
		// php error
		ssp_debug( 'ssp_get_item_responses php error', $e->getMessage());
		
	}
	
	return $item_responses;
}

// 6.8
function ssp_get_survey_responses( $survey_id ) {
	
	global $wpdb;
	
	$survey_responses = 0;
	
	try { 
	
		// sql to check if this user has completed the survey
		$sql = "
			SELECT count(id) AS total FROM {$wpdb->prefix}ssp_survey_responses 
			WHERE survey_id = %d
		";
		
		// prepare query
		$sql = $wpdb->prepare($sql, $survey_id);
		
		// run query, returns total survey responses
		$survey_responses = (int)$wpdb->get_var($sql);
	
	} catch( Exception $e ) {
		
		// php error
		ssp_debug( 'ssp_get_survey_responses php error', $e->getMessage());
		
	}
	
	return $survey_responses;
	
}

// 6.9
function ssp_get_stats_html( $survey_id ) {
	
	// setup default return value
	$output = '<div class="ssp-survey-stats"></div>';
	
	if( $survey_id ):
	
		$question_html = ssp_get_question_html( $survey_id, true );
		$responses = ssp_get_survey_responses( $survey_id );
		$submissions_received = ssp_get_submissions_received( $survey_id );
	
		// build output
		$output = '
			<div class="ssp-survey-stats">
				'. $question_html .'
				<p>'. $responses .' total participants.</p>
				<p>'. $submissions_received .' submissions received today.</p>
			</div>
		';
	
	endif;
	
	return $output;
	
}

// 6.10
// hint: returns the number of submission received today
// either for all surveys or specific survey
function ssp_get_submissions_received( $survey_id = 0 ) {
	
	global $wpdb;
	
	// set default return value
	$submissions_received = 0;
	
	// get todays date
	$today = date('Y-m-d');
	$today .= ' 00:00:00';
	
	try { 
	
		// IF survey_id is provided
		if( $survey_id ):
	
			// sql to check if this user has completed the survey
			$sql = "
				SELECT count(id) AS total FROM {$wpdb->prefix}ssp_survey_responses 
				WHERE updated_at >= '{$today}' 
				AND survey_id = %d
			";
			
			// prepare query
			$sql = $wpdb->prepare($sql, $survey_id);
		
		else: 
		// IF no survey_id is provided
		// get total of all survey submissions
		
			// sql to check if this user has completed the survey
			$sql = "
				SELECT count(id) AS total FROM {$wpdb->prefix}ssp_survey_responses
				WHERE updated_at >= '{$today}'
			";
		
		endif;
		
		// run query, returns total survey responses
		$submissions_received = (int)$wpdb->get_var($sql);
	
	} catch( Exception $e ) {
		
		// php error
		ssp_debug( 'ssp_get_submission_received php error', $e->getMessage());
		
	}
	
	return $submissions_received;
	
}




/* !7. CUSTOM POST TYPES */

// 7.1
// ssp_survey
include_once( plugin_dir_path( __FILE__ ) . 'cpt/ssp_survey.php');




/* !8. ADMIN PAGES */

// 8.1
function ssp_welcome_page() {
	
	$submissions_received = ssp_get_submissions_received();
	
	$submissions_received_msg = 'No submissions received today... yet!';
	
	if( $submissions_received ):
		
		$submissions_received_msg = 'Woohoo! '. $submissions_received .' submissions received today.';
	
	endif;
	
	$output = '
		<div class="wrap">
			
			<h2>Snappy Surveys</h2>
			
			<h3>'. $submissions_received_msg .'</h3>
			
			<ol>
				<li>Get to know your audience.</li>
				<li><a href="'. admin_url('post-new.php?post_type=ssp_survey') .'">Create simple surveys</a> that capture annonymous data.</li>
				<li><a href="'. admin_url('admin.php?page=ssp_stats_page') .'">See insightful statistics</a> from your surveys.</li>
			</ol>
		
		</div>
	';
	
	echo $output;
}

// 8.2
function ssp_stats_page() {
	
	// query surveys
	$surveys = get_posts(array(
		'post_type'=>'ssp_survey',
		'post_status'=>array('publish','draft'),
		'posts_per_page'=>-1,
		'orderby'          => 'post_title',
		'order'            => 'ASC',
	));
	
	// get selected survey
	$selected_survey_id = ( isset($_GET['survey_id']) ) ? (int)$_GET['survey_id'] : 0;
	
	// IF there are surveys...
	if( count($surveys) ):
	
		// build form select html
		$select_html = '<label>Selected Survey:</label><select name="ssp_survey"><option> - Select One - </option>';
		
		foreach ( $surveys as $survey ) {
			
			// determine selected attribute for this option
			$selected = '';
			if( $survey->ID == $selected_survey_id ):
				$selected = ' selected="selected"';
			endif;
			
			// append option to select html
			$select_html .= '<option value="'. $survey->ID .'" '. $selected .'>'. $survey->post_title .'</option>';
		}
		
		// close select input html
		$select_html .= '</select>';
		
	else: 
	
		// IF no surveys, display friendly message
		$select_html .= 'You don\'t have any surveys yet! Why not <a href="'. admin_url('post-new.php?post_type=ssp_survey') .'">Create a New Survey?</a>';
	
	endif;
	
	// get stats html
	$stats_html = '<div class="ssp-survey-stats"></div>';
	
	if( $selected_survey_id ):
		
		$stats_html = ssp_get_stats_html( $selected_survey_id );
		
	
	endif;
	
	// build output
	$output = '
		<div class="wrap ssp-stats-admin-page">
			
			<h2>Survey Statistics</h2>
			
			<p>
				'. $select_html .'
			</p>
			
			'. $stats_html .'
		
		</div>
	';
	
	// return output
	echo $output;
}





/* !9. SETTINGS */




/* !10. MISCELLANEOUS */

// 10.1
// hint: writes an output to the browser and runs kills php processes
function ssp_debug( $msg='', $data=false, $die=true ) {
	
	echo '<pre>';
	
	if(strlen($msg)):
		echo $msg .' <br />';
	endif;
	
	if($data !== false):
		var_dump($data);
	endif;
	
	echo '</pre>';
	
	if($die) die();
	
}


