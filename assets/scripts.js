jQuery(function($) {

	$('.button[data-loader]').each(function() {

		var button = $(this),
			data = button.data('loader'),
			offset = data.offset,
			section = button.parents(data.wrapper),
			wrapper = section.find('.items'),
			loader = $('<div class="wait"><span></span><span></span><span></span></div>');

		wrapper.on('loader:reset', function() {

			wrapper.children().remove();

			offset = 0;

			data.offset = 0;

			button.trigger('click');

		});

		button.click(function(e) {

			data = button.data('loader');

			data.action = 'rio_load';

			data.noncer = rio.nonce;

			$.ajax({
				url: rio.ajaxurl,
				type: 'post',
				dataType: 'json',
				data: data,
				success: function(response) {

					if (response['result']) {

						var posts = $(response['result']);

						wrapper.append(posts);

						offset = offset + data.number;

						data.offset = offset;

						if (response['more']) {
							button.removeClass('hidden');
						} else {
							button.addClass('hidden');
						}

						section.trigger('loader:init');

					} else {

						button.addClass('hidden');

					}

				},
				beforeSend: function() {
					wrapper.append(loader);
					loader.addClass('is_visible');
				},
				complete: function() {
					loader.removeClass('is_visible');
					wrapper.removeClass('loading');
				}
			});

			e.stopPropagation();

			return false;

		});

	});

	$('.entry-summary .rio_stars').click(function() {

		$('#tab-title-reviews > a').trigger('click');

		smoothScrollTo($('#tab-title-reviews'));

	});

	function smoothScrollTo(element, speed) {

		var $ = jQuery;

		speed = parseInt(speed) || 1000;

		element = $(element);

		if (element.length > 0) {

			var offset = element.offset().top - 40;

			$('html, body').stop().animate({
				'scrollTop': offset
			}, speed);

		}

	}

});