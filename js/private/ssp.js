jQuery(document).ready(function($){
	
	// do something after jQuery has loaded
	
	debug('private js script loaded!');
	
	// hint: displays a message and data in the console debugger
	function debug(msg, data) {
		return false;
		try {
			console.log(msg);
			if( typeof data !== "undefined" ) {
				console.log(data);
			}
		} catch(e){
			
		}
	}
	
	// setup our wp ajax URL
	var wpajax_url = document.location.protocol + '//' + document.location.host + '/wp-admin/admin-ajax.php';
	
	// dynamically load in stats when survey select html form element is changed...
	$(document).on('change','.ssp-stats-admin-page [name="ssp_survey"]',function(e){
		
		var survey_id = $('option:selected',this).val();
		
		debug('selected survey', survey_id);
		
		$stats_div = $('.ssp-survey-stats','.ssp-stats-admin-page');
		
		$.ajax({
			cache: false,
			method: 'post',
			url: wpajax_url + '?action=ssp_ajax_get_stats_html',
			dataType: 'json',
			data: {
				survey_id: survey_id
			},
			success: function( response ) {
				// return response in console for debugging...
				debug(response);
				
				// IF submission was successful...
				if( response.status ) {
					// update the stats_div html
					$stats_div.replaceWith(response.html);
				} else {
					// IF submission was unsuccessful...
					// notify user
					alert(response.message);
				}
			},
			error: function( jqXHR, textStatus, errorThrown ) {
				// output error information for debugging...
				debug('error', jqXHR);
				debug( 'textStatus', textStatus );
				debug( 'errorThrown', errorThrown );
			}
		});
		
	});
	
	// stop our admin menus from collapsing
	if( $('body[class*=" ssp_"]').length || $('body[class*=" post-type-ssp_"]').length ) {

		$ssp_menu_li = $('#toplevel_page_ssp_welcome_page');
		
		$ssp_menu_li
		.removeClass('wp-not-current-submenu')
		.addClass('wp-has-current-submenu')
		.addClass('wp-menu-open');
		
		$('a:first',$ssp_menu_li)
		.removeClass('wp-not-current-submenu')
		.addClass('wp-has-submenu')
		.addClass('wp-has-current-submenu')
		.addClass('wp-menu-open');
		
	}
	
});