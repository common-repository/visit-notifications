<?php
declare( strict_types=1 );

namespace WatchTheDot\VisitNotifications\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Admin API class.
 */
class AdminApi {
	/**
	 * The main plugin object.
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $parent = null;

	/**
	 * Constructor function
	 */
	public function __construct( $parent ) {
		$this->parent = $parent;
	}

	public function build_field( $data = [], $post = null, $prefix = '' ): string {
		// Get field info.
		$field = $data['field'] ?? $data;

		// Check for prefix on option name.
		$option_name = $data['prefix'] ?? $prefix;

		if ( $field['type'] === 'group' ) {
			return $this->build_field_group( $field, $post, $option_name );
		}

		// Get saved data.
		$data = '';
		if ( $post ) {
			// Get saved field data.
			$option_name .= $field['id'];
			$option       = get_post_meta( $post->ID, $field['id'], true );

			// Get data to display in field.
			if ( isset( $option ) ) {
				$data = $option;
			}
		} else {
			// Get saved option.
			$option_name .= $field['id'];
			$option       = get_option( $option_name );

			// Get data to display in field.
			if ( isset( $option ) ) {
				$data = $option;
			}
		}

		// Show default data if no option saved and default is supplied.
		if ( false === $data ) {
			$data = $field['default'] ?? '';
		}

		$html  = '';
		$html .= $this->build_input( $field, $post, $option_name, $data );

		if ( isset( $field['description'] ) ) {
			$html .= $this->build_description( $field, $post, $option_name, $data );
		}

		return $html;
	}

	public function build_input( $field, $post, $option_name, $data ): string {
		$html = '';

		switch ( $field['type'] ) {
			case 'text':
			case 'url':
			case 'email':
			case 'password':
			case 'number':
				$html .= $this->build_input_text( $field, $post, $option_name, $data );
				break;

			case 'text_secret':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="text" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ?? '' ) . '" value="" />' . "\n";
				break;

			case 'textarea':
				$html .= '<textarea id="' . esc_attr( $field['id'] ) . '" rows="5" cols="50" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ?? '' ) . '">' . $data . '</textarea><br/>' . "\n";
				break;

			case 'checkbox':
				$checked = '';
				if ( $data && 'on' === $data ) {
					$checked = 'checked="checked"';
				}
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . esc_attr( $field['type'] ) . '" name="' . esc_attr( $option_name ) . '" ' . $checked . '/>' . "\n";
				break;

			case 'checkbox_multi':
			case 'radio':
				$html .= $this->build_input_list_options( $field, $post, $option_name, $data );
				break;

			case 'select':
			case 'select_multi':
				$html .= $this->build_input_select( $field, $post, $option_name, $data, $field['type'] === 'select_multi' );
				break;

			case 'image':
				$html .= $this->build_input_image( $field, $post, $option_name, $data );
				break;

			case 'color':
				$html .= $this->build_input_color( $field, $post, $option_name, $data );
				break;

			case 'editor':
				ob_start();
				wp_editor(
					$data,
					$option_name,
					[
						'textarea_name' => $option_name,
					]
				);
				$html .= ob_get_clean();
				break;

			case 'string_list':
				$html .= $this->build_input_string_list( $field, $post, $option_name, $data );
				break;

			case 'code':
				$html .= $this->build_input_code( $field, $post, $option_name, $data );
		}

		return $html;
	}

	public function build_description( $field, $post, $option_name, $data ): string {
		$html = '<br>';

		switch ( $field['type'] ) {
			case 'checkbox_multi':
			case 'radio':
			case 'select_multi':
				$html .= '<span class="description">' . $field['description'] . '</span>';
				break;

			default:
				if ( ! $post ) {
					$html .= '<label for="' . esc_attr( $field['id'] ) . '">' . "\n";
				}

				$html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

				if ( ! $post ) {
					$html .= '</label>' . "\n";
				}
				break;
		}

		return $html;
	}

	public function build_field_group( $data, $post, $prefix ): string {
		ob_start();
		?>
		<table
			id="<?php echo esc_attr( $data['id'] ); ?>"
			class="form-table"
			role="presentation"
			<?php echo isset( $data['conditional'] ) ? 'data-conditional="' . esc_attr( $data['conditional'] ) . '"' : ''; ?>
		>
			<tbody>
				<?php foreach ( $data['fields'] as $field ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $field['label'] ); ?></th>
						<td><?php echo $this->build_field( $field, $post, $prefix ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		return ob_get_clean();
	}

	public function build_input_text( $field, $post, $option_name, $data ): string {
		$attributes = '';

		if ( isset( $field['min'] ) ) {
			$key = $field['type'] === 'number' ? 'min' : 'minlength';

			$attributes .= "{$key}=" . esc_attr( $field['min'] ) . ' ';
		}

		if ( isset( $field['max'] ) ) {
			$key = $field['type'] === 'number' ? 'max' : 'maxlength';

			$attributes .= "{$key}=" . esc_attr( $field['max'] ) . ' ';
		}

		return '<input
			id="' . esc_attr( $field['id'] ) . '"
			type="' . esc_attr( $field['type'] ) . '"
			name="' . esc_attr( $option_name ) . '"
			placeholder="' . esc_attr( $field['placeholder'] ?? '' ) . '"
			value="' . esc_attr( $data ) . '"
			' . $attributes . '
		>';
	}

	public function build_input_list_options( $field, $post, $option_name, $data ): string {
		$html = '';

		$option_name = esc_attr( $option_name );
		$type        = $field['type'];

		if ( $field['type'] === 'checkbox_multi' ) {
			$type         = 'checkbox';
			$option_name .= '[]';
		}

		foreach ( $field['options'] as $k => $v ) {
			$checked = false;
			if ( $field['type'] === 'checkbox_multi' && in_array( (string) $k, (array) $data, true ) ) {
				$checked = true;
			} elseif ( $field['type'] === 'radio' && $k == $data ) {
				$checked = true;
			}

			$html .= '<p>
				<label for="' . esc_attr( $field['id'] . '_' . $k ) . '">
					<input
						type="' . $type . '"
						' . checked( $checked, true, false ) . '
						name="' . $option_name . '"
						value="' . esc_attr( $k ) . '"
						id="' . esc_attr( $field['id'] . '_' . $k ) . '"
					> ' . $v . '
				</label>
			</p>';
		}

		return $html;
	}

	public function build_input_select( $field, $post, $option_name, $data, $multiple = false ): string {
		$html = '<select
			name="' . esc_attr( $option_name ) . ( $multiple ? '[]' : '' ) . '"
			id="' . esc_attr( $field['id'] ) . '"
			' . ( $multiple ? ' multiple="multiple"' : '' ) . '
		>';
		foreach ( $field['options'] as $k => $v ) {
			$selected = $multiple ? in_array( $k, (array) $data ) : $k == $data;
			$html    .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '">' . $v . '</option>';
		}
		$html .= '</select>';

		return $html;
	}

	public function build_input_image( $field, $post, $option_name, $data ): string {
		$images = explode( ',', $data );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $option_name ); ?>_preview">
			<?php foreach ( $images as $image ) : ?>
				<img src="<?php echo esc_url( wp_get_attachment_thumb_url( $image ) ); ?>" class="image-preview">
			<?php endforeach; ?>
		</div><br>

		<input
			id="<?php echo esc_attr( $option_name ); ?>_button"
			type="button"
			data-uploader_title="<?php echo esc_attr( __( 'Choose image', 'visitnotifications' ) ); ?>"
			data-uploader_button_text="<?php echo esc_attr( __( 'Use image', 'visitnotifications' ) ); ?>"
			data-multiple="<?php echo isset( $field['multiple'] ) && $field['multiple'] ? '1' : '0'; ?>"
			class="image_upload_button button"
			value="<?php echo esc_attr( __( 'Choose image', 'visitnotifications' ) ); ?>"
		>
		<input
			id="<?php echo esc_attr( $option_name ); ?>"
			class="image_data_field"
			type="hidden"
			name="<?php echo esc_attr( $option_name ); ?>"
			value="<?php echo esc_attr( $data ); ?>"
		>
		<br>
		<?php
		return ob_get_clean();
	}

	public function build_input_color( $field, $post, $option_name, $data ): string {
		//phpcs:disable
		ob_start();
		?>
		<div class="color-picker" style="position:relative;">
			<input type="text" name="<?php echo esc_attr( $option_name ); ?>" class="color" value="<?php echo esc_attr( $data ); ?>" />
			<div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;" class="colorpicker"></div>
		</div>
		<?php
		//phpcs:enable
		return ob_get_clean();
	}

	public function build_input_string_list( $field, $post, $option_name, $data ): string {
		ob_start();
		?>
		<style>
			.settings_string_list {
				width: 75%;
				border: 1px solid #666;
			}

			.settings_string_list {
				border-bottom: 1px solid #888;
			}

			.settings_string_list tr.new-line {
				display: none;
			}

			.settings_string_list td:first-child {
				width: 90%;
			}

			.settings_string_list td:first-child input {
				width: 100%;
			}

			.settings_string_list td:last-child {
				width: 10%;
				text-align: center;
			}

			.settings_string_list td:last-child span {
				border-radius: 50%;
				border: 1px solid #999;
				width: 1.5em;
				height: 1.5em;
				display: block;
				line-height: 1.5em;
				cursor: pointer;
				user-select: none;
			}

			.settings_string_list td:last-child span:hover {
				background-color: #2271b1;
				color: #fff;
			}
		</style>
		<table class="settings_string_list">
			<tbody>
				<?php foreach ( ( empty( $data ) ? [] : $data ) as $line ) : ?>
					<tr>
						<td><input type="text" name="<?php echo esc_attr( $option_name ); ?>[]" value="<?php echo esc_attr( $line ); ?>"></td>
						<td><span class="remove">&minus;</span></td>
					</tr>
				<?php endforeach; ?>
				<tr class="add-line">
					<td></td>
					<td><span class="add">&plus;</span></td>
				</tr>
				<tr class="new-line" data-name="<?php echo esc_attr( $option_name ); ?>[]">
					<td><input type="text"></td>
					<td><span class="remove">&minus;</span></td>
				</tr>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	public function build_input_code( $field, $post, $option_name, $data ): string {
		wp_enqueue_style( 'wp-codemirror' );
		wp_enqueue_script( 'wp-codemirror' );
		wp_add_inline_script(
			'wp-codemirror',
			"
			jQuery(function ($) {
				const cm = wp.CodeMirror.fromTextArea(
					document.getElementById('" . esc_js( $field['id'] ) . "'),
					{ 'mode': '" . $field['language'] . "', 'lineNumbers': true }
				);
			});
		"
		);
		ob_start();
		?>
		<textarea
			name="<?php echo esc_attr( $option_name ); ?>"
			id="<?php echo esc_attr( $field['id'] ); ?>"
			cols="70"
			rows="30"
		>
			<?php echo esc_textarea( $data ); ?>
		</textarea>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate HTML for displaying fields.
	 *
	 * @param  array   $data Data array.
	 * @param  object  $post Post object.
	 *
	 * @return void
	 */
	public function display_field( $data = [], $post = null ): void {
		echo $this->build_field( $data, $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
