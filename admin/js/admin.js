(function ($) {
	'use strict';

	$(function () {
		let frame;
		const $logoId = $('#mpmm-logo-id');
		const $preview = $('#mpmm-logo-preview');
		const $remove = $('#mpmm-remove-logo');

		$('#mpmm-select-logo').on('click', function (event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			const strings = window.mpmmAdmin || {};

			frame = wp.media({
				title: strings.frameTitle || 'Choose a logo',
				button: { text: strings.frameButton || 'Use this logo' },
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', function () {
				const attachment = frame.state().get('selection').first().toJSON();
				const source = attachment.sizes && attachment.sizes.medium
					? attachment.sizes.medium.url
					: attachment.url;

				$logoId.val(attachment.id);
				$preview.empty().append($('<img>', { src: source, alt: '' }));
				$remove.prop('hidden', false);
			});

			frame.open();
		});

		$remove.on('click', function (event) {
			event.preventDefault();
			$logoId.val('0');
			$preview.empty();
			$remove.prop('hidden', true);
		});
	});
})(jQuery);
