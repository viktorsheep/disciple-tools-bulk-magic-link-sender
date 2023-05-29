jQuery(function ($) {

	$(document).ready(function () {
		$('#txt_ekballo_url').val(window.dt_magic_links.dt_ekballo_url)
	});

  // Event Listeners
  $(document).on('click', '#ml_general_main_col_general_update_but', function () {
    handle_update_request();
  });

  $(document).on('click', '.ml-general-docs', function (evt) {
    handle_docs_request($(evt.currentTarget).data('title'), $(evt.currentTarget).data('content'));
  });

  $(document).on('click', '#btn_UpdateEkballo', function () {
		handle_update_ekballo_url();
	});

  // Helper Functions
  function handle_update_request() {
    // Fetch values to be saved
    let all_scheduling_enabled = $('#ml_general_main_col_general_all_scheduling_enabled').prop('checked');
    let all_channels_enabled = $('#ml_general_main_col_general_all_channels_enabled').prop('checked');
    let default_time_zone = $('#ml_general_main_col_general_default_time_zone').val();

    // Update hidden form values
    $('#ml_general_main_col_general_form_all_scheduling_enabled').val(all_scheduling_enabled ? '1' : '0');
    $('#ml_general_main_col_general_form_all_channels_enabled').val(all_channels_enabled ? '1' : '0');
    $('#ml_general_main_col_general_form_default_time_zone').val(default_time_zone);

    // Submit form
    $('#ml_general_main_col_general_form').submit();
  }

  function handle_docs_request(title_div, content_div) {
    $('#ml_general_right_docs_section').fadeOut('fast', function () {
      $('#ml_general_right_docs_title').html($('#' + title_div).html());
      $('#ml_general_right_docs_content').html($('#' + content_div).html());

      $('#ml_general_right_docs_section').fadeIn('fast');
    });
  }

	function handle_update_ekballo_url() {
		let validation = {
			flag: true,
			error: ''
		}

		let txtURL = $('#txt_ekballo_url').val().replace('/#/', '')

		const isValidUrl = urlString=> {
			var urlPattern = new RegExp('^(https?:\\/\\/)?'+ // validate protocol
		    '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|'+ // validate domain name
		    '((\\d{1,3}\\.){3}\\d{1,3}))'+ // validate OR ip (v4) address
		    '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*'+ // validate port and path
		    '(\\?[;&a-z\\d%_.~+=-]*)?'+ // validate query string
		    '(\\#[-a-z\\d_]*)?$','i'); // validate fragment locator
		  return !!urlPattern.test(urlString);
		}

		txtURL === ''
			? validation = { flag: false, error: 'URL cannot be blank.' }
			: ( !isValidUrl(txtURL)
					? validation = { flag: false, error: 'Invalid URL.' }
					: validation = { flag: true, error: '' }
			)

		if(!validation.flag) {
			console.error(validation.error)
			$('#p_Error').text(validation.error)
			return
		}

		$('#p_Error').text('')

		$.ajax({
			url: window.dt_magic_links.dt_endpoint_update_ekballo_url,
			method: 'post',
			data: {
				url: txtURL
			},
			beforeSend: (xhr) => {
        xhr.setRequestHeader("X-WP-Nonce", window.dt_admin_scripts.nonce)
				$('#btn_UpdateEkballo').text('Updating...').prop('disabled', true)
			},
			success: function (data) {
				$('#txt_ekballo_url').val(data.value)
				$('#btn_UpdateEkballo').text('Update').prop('disabled', false)
			},
			error: function (data) {
				console.log(data)
				$('#btn_UpdateEkballo').text('Update').prop('disabled', false)
			}
		});
	}

});
