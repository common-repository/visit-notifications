/**
 * Plugin Template js settings.
 *
 *  @package WordPress Plugin Template/Settings
 */

 jQuery( document ).ready(
	function ($) {

		/***** Colour picker *****/

		$( '.colorpicker' ).hide();
		$( '.colorpicker' ).each(
			function () {
				$( this ).farbtastic( $( this ).closest( '.color-picker' ).find( '.color' ) );
			}
		);

		$( '.color' ).click(
			function () {
				$( this ).closest( '.color-picker' ).find( '.colorpicker' ).fadeIn();
			}
		);

		$( document ).mousedown(
			function () {
				$( '.colorpicker' ).each(
					function () {
						var display = $( this ).css( 'display' );
						if (display == 'block') {
							$( this ).fadeOut();
						}
					}
				);
			}
		);

		/***** Uploading images *****/

		var file_frame;

		jQuery.fn.uploadMediaFile = function (button, preview_media) {
			var button_id  = button.attr( 'id' );
			var field_id   = button_id.replace( '_button', '' );
			var preview_id = button_id.replace( '_button', '_preview' );

			// If the media frame already exists, reopen it.
			if (file_frame) {
				file_frame.open();
				return;
			}

			// Create the media frame.
			file_frame = wp.media.frames.file_frame = wp.media(
				{
					title: button.data( 'uploader_title' ),
					button: {
						text: button.data( 'uploader_button_text' ),
					},
					multiple: button.data('multiple') === 1
				}
			);

			// When an image is selected, run a callback.
			file_frame.on(
				'select',
				function () {
					jQuery( "#" + preview_id ).html('');
					let ids = [];

					for (let model of file_frame.state().get( 'selection' ).models) {
						const attachment = model.toJSON();
						const preview = jQuery('<img>');

						ids.push(attachment.id);

						preview.addClass('image_preview');
						preview.attr('src', attachment.sizes.thumbnail.url);
						console.log(preview);
						jQuery( "#" + preview_id ).append(preview);
					}

					jQuery("#" + field_id).val(ids.join(','));
					file_frame = false;
				}
			);

			// Finally, open the modal.
			file_frame.open();
		}

		jQuery( '.image_upload_button' ).click(
			function () {
				jQuery.fn.uploadMediaFile( jQuery( this ), true );
			}
		);

		jQuery( '.image_delete_button' ).click(
			function () {
				jQuery( this ).closest( 'td' ).find( '.image_data_field' ).val( '' );
				jQuery( this ).closest( 'td' ).find( '.image_preview' ).remove();
				return false;
			}
		);


		jQuery( 'table[data-conditional]' ).each(function () {
			const $element = $(this).closest('tr');
			const $conditional = $('#' + $(this).data('conditional'));

			function hideOrShow() {
				if ($conditional.is(':checked')) {
					$element.show();
				} else {
					$element.hide();
				}
			}

			$conditional.on('change', hideOrShow);

			hideOrShow();
		});

		jQuery( '.settings_string_list' ).on( 'click', '.add', function () {
			const $this = $(this);
			const $table = $this.closest('.settings_string_list');
			const $newline = $table.find('.new-line');

			let name = $newline.data('name')

			let $clone = $newline.clone();
			console.log($clone);
			$clone.removeClass('new-line').removeAttr('data-name');
			$clone.find('input').attr('name', name);
			$clone.insertBefore($table.find('.add-line'));

		} );

		jQuery( '.settings_string_list' ).on( 'click', '.remove', function () {
			$(this).closest('tr').remove();
		} );
	}
);
