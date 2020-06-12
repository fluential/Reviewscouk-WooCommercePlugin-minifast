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

});