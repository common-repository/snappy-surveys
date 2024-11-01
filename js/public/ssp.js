jQuery(document).ready(function($){
	
	// do something after jQuery has loaded
	debug('public js script loaded!');
	
	// hint: displays a message and data in the console debugger
	function debug(msg, data) {
		return false;
		try {
			// print message to console
			console.log(msg);
			if( typeof data !== "undefined" ) {
				// print data to console
				console.log(data);
			}
		} catch(e){
			// do nothing
		}
	}
	
	// setup our wp ajax URL
	var wpajax_url = document.location.protocol + '//' + document.location.host + '/wp-admin/admin-ajax.php';
	
	// bind custom functin to survey form submit event
	$(document).on('submit','.ssp-survey-form',function(e){
		
		// prevent form from submitting normally
		e.preventDefault();
		
		$form = $(this);
		$survey = $form.closest('.ssp-survey');
		
		// get selected radio button
		$selected = $('input[name^="ssp_question_"]:checked',this);
		
		// split field name into array
		var name_arr = $selected.attr('name').split('_');
		// get the survey id from the last item in name array
		var survey_id = name_arr[2];
		// get the response id from the value of the selected item
		var response_id = $selected.val();
		
		// get the closest dl.ssp-question element
		$dl = $selected.closest('dl.ssp-question');
		
		var data = {
			_wpnonce: $('[name="_wpnonce"]',$form).val(),
			_wp_http_referer: $('[name="_wp_http_referer"]',$form).val(),
			survey_id: survey_id,
			response_id: response_id
		}; 
		
		debug('data', data);
		
		// submit the chosen item via ajax
		$.ajax({
			cache: false,
			method: 'post',
			url: wpajax_url + '?action=ssp_ajax_save_response',
			dataType: 'json',
			data: data,
			success: function( response ) {
				// return response in console for debugging...
				debug(response);
				
				// IF submission was successful...
				if( response.status ) {
					// update the html of the current li
					$dl.replaceWith(response.html);
					// hide survey content message
					$('.ssp-survey-footer',$survey).hide();
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
	
});