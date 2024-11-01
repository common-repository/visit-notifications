<?php
declare( strict_types=1 );

namespace WatchTheDot\VisitNotifications;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Settings class.
 */
class Settings {
	/**
	 * The main plugin object.
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 *
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = [];

	/**
	 * Cache flat settings
	 *
	 * @var array<string, array>
	 * @since 1.0.0
	 */
	public $settings_ids = [];

	/**
	 * Constructor function.
	 *
	 * @param object $parent Parent object.
	 */
	public function __construct( $parent ) {
		$this->parent = $parent;

		$this->base = $parent::TOKEN . '_';

		// Initialise settings.
		add_action( 'init', [ $this, 'init_settings' ], 9 );

		// Register plugin settings.
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// Add settings page to menu.
		add_action( 'admin_menu', [ $this, 'add_menu_item' ] );

		// Add settings link to plugins page.
		add_filter(
			'plugin_action_links_' . plugin_basename( $this->parent->get_filename() ),
			[
				$this,
				'add_settings_link',
			]
		);

		// Configure placement of plugin settings page. See readme for implementation.
		add_filter( $this->base . 'menu_settings', [ $this, 'configure_settings' ] );
	}

	/**
	 * Initialise settings
	 *
	 * @return void
	 */
	public function init_settings(): void {
		$this->settings = $this->settings_fields();

		foreach ( $this->settings as $group ) {
			$this->init_settings_group( $group );
		}
	}

	public function init_settings_group( $group ) {
		foreach ( $group['fields'] as $field ) {
			if ( $field['type'] === 'group' ) {
				$this->init_settings_group( $field );
				continue;
			}

			$this->settings_ids[ $field['id'] ] = $field;

			if ( ! isset( $field['on_update'] ) ) {
				continue;
			}

			add_action(
				"add_option_{$this->base}{$field['id']}",
				static function ( $option, $value ) use ( $field ) {
                    call_user_func( $field['on_update'], $field['id'], null, $value ); //phpcs:ignore
				},
				10,
				2
			);
			add_action(
				"update_option_{$this->base}{$field['id']}",
				static function ( $old, $new ) use ( $field ) {
                    call_user_func( $field['on_update'], $field['id'], $old, $new ); //phpcs:ignore
				},
				10,
				2
			);
		}
	}

	/**
	 * Add settings page to admin menu
	 *
	 * @return void
	 */
	public function add_menu_item(): void {

		$args = $this->menu_settings();

		// Do nothing if wrong location key is set.
		if ( ! is_array( $args ) || ! isset( $args['location'] ) || ! function_exists( 'add_' . $args['location'] . '_page' ) ) {
			return;
		}

		switch ( $args['location'] ) {
			case 'options':
			case 'submenu':
				$page = add_submenu_page( $args['parent_slug'], $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'] );
				break;
			case 'menu':
				$page = add_menu_page( $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'], $args['icon_url'], $args['position'] );
				break;
			default:
				return;
		}
		add_action( 'admin_print_styles-' . $page, [ $this, 'settings_assets' ] );
	}

	/**
	 * Prepare default settings page arguments
	 *
	 * @return mixed|void
	 */
	private function menu_settings(): array {
		return apply_filters(
			$this->base . 'menu_settings',
			[
				'location'    => 'options', // Possible settings: options, menu, submenu.
				'parent_slug' => 'options-general.php',
				'page_title'  => __( $this->parent::NAME, 'visitnotifications' ),
				'menu_title'  => __( $this->parent::NAME, 'visitnotifications' ),
				'capability'  => 'manage_options',
				'menu_slug'   => $this->parent::TOKEN . '_settings',
				'function'    => [ $this, 'settings_page' ],
				'icon_url'    => '',
				'position'    => null,
			]
		);
	}

	/**
	 * Container for settings page arguments
	 *
	 * @param array $settings Settings array.
	 *
	 * @return array
	 */
	public function configure_settings( $settings = [] ): array {
		return $settings;
	}

	/**
	 * Load settings JS & CSS
	 *
	 * @return void
	 */
	public function settings_assets(): void {

		// We're including the farbtastic script & styles here because they're needed for the colour picker
		// If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below.
		wp_enqueue_style( 'farbtastic' );
		wp_enqueue_script( 'farbtastic' );

		// We're including the WP media scripts here because they're needed for the image upload field.
		// If you're not including an image upload then you can leave this function call out.
		wp_enqueue_media();

		wp_register_script( $this->parent::TOKEN . '-settings-js', $this->parent->get_assets_url() . 'js/settings.js', [ 'farbtastic', 'jquery' ], $this->parent::VERSION, true );
		wp_enqueue_script( $this->parent::TOKEN . '-settings-js' );
	}

	/**
	 * Add settings link to plugin list table
	 *
	 * @param  array $links Existing links.
	 * @return array        Modified links.
	 */
	public function add_settings_link( $links ): array {
		$settings_link = sprintf(
			"<a href='options-general.php?page=%s'>%s</a>",
			$this->parent::TOKEN,
			__( 'Settings', 'visitnotifications' )
		);
		array_push( $links, $settings_link );
		return $links;
	}

	/**
	 * Build settings fields
	 *
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields(): array {
		$settings = [];

		$settings['page'] = [
			'title'       => __( 'Page Options', 'visitnotifications' ),
			'description' => __( '', 'visitnotifications' ),
			'fields'      => [
				[
					'id'          => 'enable_notifications',
					'type'        => 'checkbox',
					'label'       => __( 'Enable Notifications', 'visitnotifications' ),
					'description' => __( 'Enable the ability to send notifications (I.e. the master off switch)', 'visitnotifications' ),
					'default'     => 'on',
				],
				[
					'id'          => 'enable_logged_in_users',
					'type'        => 'checkbox',
					'label'       => __( 'Logged In Users', 'visitnotifications' ),
					'description' => __( 'Send notifications when logged in users access pages (This can be overriden on each page)', 'visitnotifications' ),
					'default'     => 'on',
				],
				[
					'id'          => 'disable_crawlers',
					'type'        => 'checkbox',
					'label'       => __( 'Disable Crawlers', 'visitnotifications' ),
					'description' => __( 'Don\'t fire when web crawlers visit pages. (Most likely won\'t be 100% accurate due to crawlers not using "bot" in their user agent.', 'visitnotifications' ),
					'default'     => 'on',
				],
				[
					'id'      => 'ip_grace_period',
					'type'    => 'checkbox',
					'label'   => __( 'IP Grace Period', 'visitnotifications' ),
					'default' => 'off',
				],
				[
					'id'          => 'ip_grace_period_group',
					'type'        => 'group',
					'conditional' => 'ip_grace_period',
					'fields'      => [
						[
							'id'      => 'ip_grace_period_context',
							'type'    => 'select',
							'label'   => __( 'Grace Period For', 'visitnotifications' ),
							'options' => [
								'site' => __( 'Site Wide', 'visitnotifications' ),
								'post' => __( 'Local to Post', 'visitnotifications' ),
							],
							'default' => 'site',
						],
						[
							'id'          => 'ip_grace_period_duration',
							'type'        => 'number',
							'label'       => __( 'Duration of grace period', 'visitnotifications' ),
							'description' => __( 'Duration of grace period (in seconds)', 'visitnotifications' ),
							'default'     => 60 * 5,
						],
					],
				],
			],
		];

		return $settings;
	}

	/**
	 * Register plugin settings
	 *
	 * @return void
	 */
	public function register_settings(): void {
		if ( ! is_array( $this->settings ) ) {
			return;
		}

		// Check posted/selected tab.
		// This doesn't need to be nonce verified since all that is changing is the tab
		// No actions are being performed.
		// phpcs:ignore WordPress.Security.NonceVerification
		$current_section = sanitize_key( $_POST['tab'] ?? $_GET['tab'] ?? '' );
		if ( ! in_array( $current_section, array_keys( $this->settings ), true ) ) {
			$current_section = '';
		}

		foreach ( $this->settings as $section => $data ) {
			if ( $current_section && $current_section !== $section ) {
				continue;
			}

			// Add section to page.
			add_settings_section( $section, $data['title'], [ $this, 'settings_section' ], $this->parent::TOKEN . '_settings' );

			$fields = $this->flatten_groups( $data['fields'] );

			foreach ( $fields as $field ) {
				// Register field.
				$option_name = $this->base . $field['id'];
				register_setting(
					$this->parent::TOKEN . '_settings',
					$option_name,
					[
						'sanitize_callback' => function ( $data ) use ( $field ) {
							return $this->sanitize_field( $data, $field );
						},
					]
				);
			}

			foreach ( $data['fields'] as $field ) {
				// Add field to page.
				add_settings_field(
					$field['id'],
					$field['label'] ?? '',
					[ $this->parent->adminapi_instance(), 'display_field' ],
					$this->parent::TOKEN . '_settings',
					$section,
					[
						'field'  => $field,
						'prefix' => $this->base,
					]
				);
			}

			if ( ! $current_section ) {
				break;
			}
		}
	}

	public function get( $name, $default = false, $format = true ) {
		if ( ! isset( $this->settings_ids[ $name ] ) ) {
			throw new InvalidArgumentException( "Unknown Option '{$name}'. This id has not been defined." );
		}

		if ( $default === false ) {
			$default = $this->settings_ids[ $name ]['default'] ?? false;
		}

		$option = get_option( $this->base . $name, $default );

		$option = $this->sanitize_field( $option, $this->settings_ids[ $name ] );

		if ( ! $format ) {
			return $option;
		}

		// Do formatting based on type.
		switch ( $this->settings_ids[ $name ]['type'] ) {
			case 'checkbox':
				return $option === 'on';
			case 'number':
				return is_numeric( $option ) ? intval( $option ) : $default;
		}

		return $option;
	}

	/**
	 * Validate form field
	 *
	 * @param  string|array $data Submitted value.
	 * @param  array $field The field
	 *
	 * @return mixed       Validated value
	 */
	public function sanitize_field( $data, $field ) {
		switch ( $field['type'] ) {
			case 'text':
				return sanitize_text_field( $data );

			case 'url':
				return sanitize_url( $data );

			case 'email':
				return is_email( $data );

			case 'password':
				return $data;

			case 'number':
				return is_numeric( $data ) ? intval( $data ) : ( $field['default'] ?? 0 );

			case 'text_secret':
				return sanitize_text_field( $data );

			case 'textarea':
				return sanitize_textarea_field( $data );

			case 'checkbox':
				return $data === 'on' ? 'on' : '';

			case 'radio':
			case 'select':
				return in_array( $data, $field['options'], true ) ? $field['options'] : $field['default'];

			case 'checkbox_multi':
			case 'select_multi':
				if ( ! is_array( $data ) ) {
					$data = [ $data ];
				}

				return array_filter( $data, static fn ( $d ) => in_array( $d, $field['options'], true ) );

			case 'image':
				return implode( ',', array_filter( array_map( static fn( $d ) => is_numeric( $d ) ? intval( $d ) : '', explode( ',', $data ) ) ) );

			case 'color':
				return sanitize_hex_color( $data );

			case 'string_list':
				return array_map( 'sanitize_text_field', $data );

			case 'editor':
			case 'code':
				return $data;

			default:
				throw new InvalidArgumentException( 'Input type is not sanitized' );
		}
	}

	/**
	 * Settings section.
	 *
	 * @param array $section Array of section ids.
	 * @return void
	 */
	public function settings_section( $section ): void {
		if ( ! isset( $this->settings[ $section['id'] ]['description'] ) ) {
			return;
		}

		echo '<p> ' . esc_html( $this->settings[ $section['id'] ]['description'] ) . '</p>';
	}

	/**
	 * Load settings page content.
	 *
	 * @return void
	 */
	public function settings_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = sanitize_key( $_GET['tab'] ?? '' );

		if ( ! in_array( $tab, array_keys( $this->settings ), true ) ) {
			$tab = '';
		}

		// Build page HTML.
		?>
		<div class="wrap" id="<?php echo esc_attr( $this->parent::TOKEN ); ?>_settings">
			<h2><?php echo esc_html__( $this->parent::NAME . ' Settings', 'visitnotifications' ); ?></h2>

			<?php // Show page tabs. ?>
			<?php if ( is_array( $this->settings ) && 1 < count( $this->settings ) ) : ?>
				<h2 class="nav-tab-wrapper">
					<?php
					$c = 0;
					foreach ( $this->settings as $section => $data ) :
						// Set tab class.
						$class  = 'nav-tab';
						$class .= ( empty( $tab ) && 0 === $c || $section === $tab ) ? ' nav-tab-active' : '';

						// Set tab link.
						$tab_link = add_query_arg( [ 'tab' => $section ] );

						// Only being used as a flag therefore nonce not required.
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						if ( isset( $_GET['settings-updated'] ) ) {
							$tab_link = remove_query_arg( 'settings-updated', $tab_link );
						}

						// Output tab.
						?>
						<a href="<?php echo esc_url( $tab_link ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $data['title'] ); ?></a>

						<?php
						++$c;
					endforeach;
					?>
				</h2>
			<?php endif; ?>

			<form method="post" action="options.php" enctype="multipart/form-data">
				<?php
					// Get settings fields.
					settings_fields( $this->parent::TOKEN . '_settings' );
					do_settings_sections( $this->parent::TOKEN . '_settings' );
				?>

				<p class="submit">
					<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
					<input name="Submit" type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Save Settings', 'visitnotifications' ) ); ?>">
				</p>
			</form>
		</div>
		<?php
	}

	private function flatten_groups( array $fields ): array {
		$nongroups = array_filter(
			$fields,
			static function ( $field ) {
				return $field['type'] !== 'group';
			}
		);

		$groups = array_column(
			array_filter(
				$fields,
				static function ( $field ) {
					return $field['type'] === 'group';
				}
			),
			'fields'
		);
		$groups = array_merge( ...$groups );

		if ( ! empty( $groups ) ) {
			return array_merge( $nongroups, $this->flatten_groups( $groups ) );
		}
		return $nongroups;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( 'Cloning of ' . self::class . ' is forbidden.' ), esc_attr( $this->parent::VERSION ) );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( 'Unserializing instances of ' . self::class . ' is forbidden.' ), esc_attr( $this->parent::VERSION ) );
	}
}
