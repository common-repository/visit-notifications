<?php
declare( strict_types=1 );

namespace WatchTheDot\VisitNotifications;

/**
 * Plugin Name:       Visit Notifications
 * Plugin URI:        https://support.watchthedot.com/our-plugins/visit-notifications
 * Description:       Quickly receive notifications when a someone looks at a page or get a summary of the visitors in a time span
 * Version:           3.1.0
 * Requires at least: 5.2
 * Tested up to:      6.0
 * Requires PHP:      7.4
 * Author:            Watch The Dot
 * Author URI:        https://www.watchthedot.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

use WP_Post;
use WP_Term;
use StoutLogic\AcfBuilder\FieldsBuilder;
use WatchTheDot\VisitNotifications\Settings;
use WatchTheDot\VisitNotifications\Library\AdminApi;
use WatchTheDot\VisitNotifications\Migrator\DataMigrator;

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			?>
		<div class="notice error">
			<p>Cannot activate plugin. Composer has not been run, most likely because this has been cloned from GitHub.</p>
			<p>If this is a development environment, run <code>composer install</code> on command line</p>
			<p>If this is a production environment, run <code>composer install --no-dev</code> on command line or download from the releases section of GitHub</p>
		</div>
			<?php
		}
	);

	if ( function_exists( 'deactivate_plugins' ) ) {
		// Fix error when plugin is still active when accessing the site on the front end.
		deactivate_plugins( __FILE__, true );
	}

	return;
}

require_once 'vendor/autoload.php';

class VisitNotifications {
	/**
	 * The name of the plugin displayed in the admin panel
	 */
	const NAME = 'Visit Notifications';

	/**
	 * The version number used for certain errors when raised
	 */
	const VERSION = '3.1.0';

	/**
	 * The namespace for the plugins settings
	 */
	const TOKEN = 'visitnotifications';

	/**
	 * The ONLY instance of the plugin.
	 * Accessable via ::instance().
	 * Ensures that the hooks are only added once
	 */
	private static ?self $instance;

	/**
	 * The FULL filepath to this file
	 */
	private string $file;

	/**
	 * The FULL directory path to this folder
	 */
	private string $dir;

	/**
	 * The directory where the plugin's assets are stored
	 */
	private string $assets_dir;

	/**
	 * The URL to the plugin's assets.
	 * Used when enqueuing styles and scripts
	 */
	private string $assets_url;

	/**
	 * The ONLY settings instance that should be made
	 * Stores related functions to register and display the settings page
	 */
	private Settings $settings;

	/**
	 * The ONLY admin API instance that should be made
	 * Provides a wrapper for admin functions. Should only be defined when accessing the admin backend
	 */
	private AdminApi $admin_api;

	/**
	 * The ONLY data migrator instance that should exist
	 * Wraps updating data storage and database migrations between versions
	 */
	private DataMigrator $datamigrator;

	private function __construct() {
		$this->file       = __FILE__;
		$this->dir        = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		register_activation_hook( $this->file, [ $this, 'activation' ] );
		register_deactivation_hook( $this->file, [ $this, 'deactivation' ] );

		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			$this->load_acf();
		}

		add_action( 'init', [ $this, 'action_init' ] );
		add_action( 'init', [ $this, 'action_init_acf' ], PHP_INT_MAX );
		add_action( 'admin_init', [ $this, 'action_admin_init' ] );

		$this->settings     = new Settings( $this );
		$this->datamigrator = new DataMigrator( $this );

		if ( ! is_admin() ) {
			return;
		}

		$this->admin_api = new AdminApi( $this );
	}

	public function load_acf() {
		include __DIR__ . '/lib/advanced-custom-fields/acf.php';

		add_filter(
			'acf/settings/url',
			fn () => plugins_url( '/lib/advanced-custom-fields/', $this->file ),
		);

		add_filter( 'acf/settings/show_admin', '__return_false' );
	}

	public function activation() {
	}

	public function deactivation() {
		// phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
		if ( wp_next_scheduled( self::TOKEN . '_send_emails' ) ) {
			wp_clear_scheduled_hook( self::TOKEN . '_send_emails' );
		}
	}

	public function action_init() {
		if ( $this->settings->get( 'enable_notifications' ) ) {
			add_action( 'wp', [ $this, 'action_wp' ] );
			add_action( self::TOKEN . '_send_emails', [ $this, 'cron_send_emails' ] );
		}

		// phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
		if ( ! wp_next_scheduled( self::TOKEN . '_send_emails', [ 'hourly' ] ) ) {
			wp_schedule_event( time(), 'hourly', self::TOKEN . '_send_emails', [ 'hourly' ] );
		}

		// phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
		if ( ! wp_next_scheduled( self::TOKEN . '_send_emails', [ 'daily' ] ) ) {
			wp_schedule_event( time(), 'daily', self::TOKEN . '_send_emails', [ 'daily' ] );
		}
	}

	public function action_init_acf() {
		foreach ( glob( __DIR__ . '/src/ACF/Fields/*.php' ) as $file ) {
			$class = 'WatchTheDot\\VisitNotifications\\ACF\\Fields\\' . pathinfo( $file, PATHINFO_FILENAME );
			$field = ( new $class( $this ) )();

			if ( ! ( $field instanceof FieldsBuilder ) ) {
				continue;
			}

			acf_add_local_field_group( $field->build() );
		}
	}

	public function action_admin_init() {
		// Add to bulk actions
		$post_types = array_keys( get_post_types() );
		$taxonomies = array_keys( get_taxonomies() );

		foreach ( $post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", [ $this, 'filter_manage_post_columns' ] );
			add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'action_manage_post_custom_column' ], 10, 2 );

			add_filter( "bulk_actions-edit-{$post_type}", [ $this, 'filter_bulk_actions_edit_screen' ] );
			add_filter(
				"handle_bulk_actions-edit-{$post_type}",
				fn ( ...$args ) => $this->filter_handle_bulk_actions( 'post', ...$args ),
				10,
				3
			);
		}

		foreach ( $taxonomies as $taxonomy ) {
			add_filter( "bulk_actions-edit-{$taxonomy}", [ $this, 'filter_bulk_actions_edit_screen' ] );
			add_filter(
				"handle_bulk_actions-edit-{$taxonomy}",
				fn ( ...$args ) => $this->filter_handle_bulk_actions( 'tax', ...$args ),
				10,
				3
			);
		}

		// The information here doesn't matter if it's not nonce verified as no action is being performed because of it
		// All that is happening is it is being outputted as a notice
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['enable-vn'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wpdesk_wp_notice_success( esc_html( sprintf( 'Enabled visit notifications for %d posts', wp_unslash( $_GET['enable-vn'] ) ) ) );
		}

		if ( ! empty( $_GET['disable-vn'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wpdesk_wp_notice_success( esc_html( sprintf( 'Disabled visit notifications for %d posts', wp_unslash( $_GET['disable-vn'] ) ) ) );
		}
		// phpcs:enable
	}

	public function action_wp() {
		if ( ! $this->check_to_record_visit() ) {
			return;
		}

		$this->record_visit( $this->get_object() );
	}

	public function filter_bulk_actions_edit_screen( array $actions ): array {
		$actions['enable-vn-visit']  = __( 'Enable Visit Notifications (On Visit)', 'visitnotifications' );
		$actions['enable-vn-hourly'] = __( 'Enable Visit Notifications (Hourly)', 'visitnotifications' );
		$actions['disable-vn']       = __( 'Disable Visit Notifications', 'visitnotifications' );

		return $actions;
	}

	public function filter_handle_bulk_actions( $screen_type, $redirect_url, $action, $ids ): string {
		if ( str_starts_with( $action, 'enable-vn' ) || $action === 'disable-vn' ) {
			$objects = array_filter(
				array_map(
					static function ( $id ) use ( $screen_type ) {
						return $screen_type === 'post'
							? get_post( $id )
							: get_term_by( 'ID', $id, get_current_screen()->taxonomy );
					},
					$ids
				)
			);

			foreach ( $objects as $object ) {
				$this->set_field( 'vn_enable_notifications', str_starts_with( $action, 'enable-vn' ), $object );
				if ( ! str_starts_with( $action, 'enable-vn' ) ) {
					continue;
				}

				$this->set_field( 'vn_schedule', $action === 'enable-vn-visit' ? 'visit' : 'hourly', $object );
			}

			if ( $redirect_url !== false ) {
				$redirect_url = add_query_arg( str_starts_with( $action, 'enable-vn' ) ? 'enable-vn' : 'disable-vn', count( $objects ), $redirect_url );
			}
		}

		return $redirect_url;
	}

	public function filter_manage_post_columns( array $columns ): array {
		$columns['vn'] = __( 'Visit Notifications', 'visitnotifications' );

		return $columns;
	}

	public function action_manage_post_custom_column( $column_key, $post_id ) {
		if ( $column_key !== 'vn' ) {
			return;
		}

		$post = get_post( $post_id );

		if ( $this->get_field( 'vn_enable_notifications', $post ) ) {
			$schedule = ucfirst( $this->get_field( 'vn_schedule', $post ) );
			echo esc_html__(
				sprintf(
					'Enabled (%s)',
					__( $schedule, 'visitnotifications' )
				),
				'visitnotifications'
			);
		} else {
			echo esc_html__( 'Disabled', 'visitnotifications' );
		}
	}

	public function cron_send_emails( $frequency ) {
		$meta_query = [
			'relation' => 'AND',
			[
				'key'     => 'vn_enable_notifications',
				'compare' => '=',
				'value'   => '1',
			],
			[
				'key'     => 'vn_schedule',
				'compare' => '=',
				'value'   => $frequency,
			],
		];

		// Do this filtering on the database side to avoid getting tons of data
		$objects = array_merge(
			get_posts(
				[
					'post_type'   => 'any',
					'meta_query'  => $meta_query,
					'numberposts' => -1,
				]
			),
			get_terms(
				[
					'taxonomy'   => array_keys( get_taxonomies( [], 'names' ) ),
					'hide_empty' => false,
					'number'     => 'all',
					'meta_query' => $meta_query,
				]
			)
		);

		foreach ( $objects as $object ) {
			// Only send the notification email if the visitor list is not empty
			$data = $this->get_meta( $object, self::TOKEN . '_visitors', true );

			if ( empty( $data ) ) {
				continue;
			}

			$this->send_notification_email( $object, $data, true );
			$this->set_meta( $object, self::TOKEN . '_visitors', [] );
		}
	}

	protected function record_visit( $object ) {
		if ( is_null( $object ) ) {
			return;
		}

		// Get the schedule for the post
		$schedule = $this->get_field( 'vn_schedule', $object );

		// Create the visitor data
		$visitor = [
			'time'       => time(),

			// Browser Information
			// @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/wc-core-functions.php#L1369
			'user_agent' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? 'unknown' ) ),
		];

		$referer            = sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$visitor['referer'] = filter_var( $referer, FILTER_VALIDATE_URL ) ? $referer : 'unknown';

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			// Collect the visitors IP address
			$ip                 = $this->anonymize_ip_address( $ip );
			$visitor['ip_addr'] = $ip;

			// Use the IP address to access the users location and timezone
			$loc_timezone = $this->get_location_and_timezone( $ip );

		}

		// If the location and timezone information exist, add the information to the visitor data
		if ( ! is_null( $loc_timezone ) ) {
			$visitor['location'] = $loc_timezone['location'];
			$visitor['timezone'] = $loc_timezone['timezone'];
		}

		// If the send schedule is set to send an email on the visit, create and send the email
		// If it is setup to be sent hourly, store the visitor information in the post_meta for the post
		if ( $schedule === 'visit' ) {
			// TODO: Investigate whether it is better to send this on a delay. I.e using schedule_single_event
			$this->send_notification_email( $object, $visitor );
		} else {
			$visitors   = $this->get_meta( $object, self::TOKEN . '_visitors', true ) ?: [];
			$visitors[] = $visitor;
			$this->set_meta( $object, self::TOKEN . '_visitors', $visitors );
		}

		if ( ! $this->settings->get( 'ip_grace_period' ) ) {
			return;
		}

		// We are going to hash the annoyomised IP address to do this otherwise it would be possible to
		// get a users IP address by putting the two data points together.

		if ( $this->settings->get( 'ip_grace_period_context' ) === 'site' ) {
			$graceperiod = get_option( self::TOKEN . '_ip_grace_period_data', [] );

			$graceperiod[ md5( $ip ) ] = time() + $this->settings->get( 'ip_grace_period_duration' );

			update_option( self::TOKEN . '_ip_grace_period_data', $graceperiod );
		} elseif ( $this->settings->get( 'ip_grace_period_context' ) === 'post' ) {
			$graceperiod = $this->get_meta( $object, self::TOKEN . '_ip_grace_period_data', true ) ?: [];

			$graceperiod[ md5( $ip ) ] = time() + $this->settings->get( 'ip_grace_period_duration' );

			$this->set_meta( $object, self::TOKEN . '_ip_grace_period_data', $graceperiod );
		}
	}

	/**
	 * Use IP API to get the location and timezone of an IP Address (Either IPv4 or IPv6)
	 *
	 * @param string $ip
	 * @return ?array
	 */
	protected function get_location_and_timezone( string $ip ): ?array {
		// Access the API endpoint
		// TODO: Add the ability to insert an API key
		$response = wp_remote_get(
			'https://ipapi.co/' . $ip . '/json/',
			[
				'timeout' => 1,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( $response['response']['code'] !== 200 ) {
			return null;
		}

		// Decode the JSON body of the request
		$body = json_decode( $response['body'] );

		// If the response indicates an error, return null
		if ( $body->error ) {
			return null;
		}

		// Create the data array
		return [
			'location' => $body->city . ', ' . $body->country_name,
			'timezone' => $body->timezone,
		];
	}

	/**
	 * @param WP_Post|WP_Term $object
	 */
	protected function send_notification_email( $object, array $data, bool $grouped = false ) {
		// Is the email to be sent the delayed summary email or sent on visit?
		if ( $grouped ) {
			$subject       = __( sprintf( '[%s] Visitor Report for %s', get_option( 'blogname' ), $object->post_title ?? $object->name ), 'visitnotifications' );
			$template_name = 'delayed.php';
			$args          = [
				'visitors' => $data,
			];
		} else {
			$subject       = __( sprintf( '[%s] New Visitor on %s', get_option( 'blogname' ), $object->post_title ?? $object->name ), 'visitnotifications' );
			$template_name = 'single.php';
			$args          = [
				'visitor' => $data,
			];
		}

		// Set general variables for template
		$args['schedule'] = $this->get_meta( $object, 'vn_schedule', true );
		$args['object']   = $object;

		ob_start();
		$template = locate_template( 'templates/' . self::TOKEN . '/' . $template_name, false, false )
			?: __DIR__ . '/templates/emails/' . $template_name;

		$output_fun = static function ( $args ) use ( $template ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
			require $template;
		};
		$output_fun( $args );

		$message = ob_get_clean();

		// TODO: Add setting for email for notifications to be sent to
		wp_mail(
			get_option( 'admin_email' ),
			$subject,
			$message,
			[
				'Content-Type: text/html; charset=UTF-8',
			]
		);
	}

	/**
	 * Get an overridable option from a post id and compare it to the global default in the settings.
	 * The ACF field requires 'global', 'on' or 'off' for this method
	 */
	protected function get_overridable_checkbox( $object, $acf_name, $setting_name ): bool {
		// Step 1) Get the ACF field result
		$acf = $this->get_field( $acf_name, $object );

		if ( ! is_null( $acf ) && $acf !== 'global' ) {
			// If ACF field controls the option, use this value
			return $acf === 'on';
		}

		// Step 2) Get the global default if ACF is not defined or using global option
		return $this->settings->get( $setting_name );
	}

	/**
	 * This should replace all calls to get_post, we need to either get the WP_Post or the WP_Taxonomy
	 *
	 * @return WP_Post|WP_Term|null
	 */
	protected function get_object() {
		if ( is_singular() ) {
			return get_post();
		}

		if ( is_archive() ) {
			if ( is_tag() ) { // Is archive the built in post tags?
				$id  = get_query_var( 'tag_id' );
				$tax = 'post_tag';
			} elseif ( is_category() ) { // Is archive the built in post category?
				$id  = get_query_var( 'cat' );
				$tax = 'category';
			} elseif ( is_tax() ) { // Is archive a custom taxonomy?
				$tax = get_query_var( 'taxonomy' );
				$id  = get_term_by( 'slug', get_query_var( 'term' ), $tax )->term_id;
			}

			$term = get_term( $id, $tax );

			if ( is_wp_error( $term ) ) {
				return null;
			}

			return $term;
		}

		return null;
	}

	/**
	 * This should replace all calls to get_field, we need to amend the post_id to get the data for a post or for a taxonomy
	 *
	 * @param WP_Post|WP_Term|null $object
	 *
	 * @return mixed
	 */
	protected function get_field( string $name, $object = null ) {
		if ( is_null( $object ) ) {
			$object = $this->get_object();
		}

		if ( is_null( $object ) ) {
			return false;
		}

		if ( $object instanceof WP_Post ) {
			$acf_id = $object->ID;
		} elseif ( $object instanceof WP_Term ) {
			$acf_id = 'term_' . $object->term_id;
		}

		return get_field( $name, $acf_id );
	}

	/**
	 * This should replace all calls to update_field
	 *
	 * @param WP_Post|WP_Term|null $object
	 *
	 * @return bool
	 */
	protected function set_field( string $name, $value, $object = null ): bool {
		if ( is_null( $object ) ) {
			$object = $this->get_object();
		}

		if ( is_null( $object ) ) {
			return false;
		}

		if ( $object instanceof WP_Post ) {
			$acf_id = $object->ID;
		} elseif ( $object instanceof WP_Term ) {
			$acf_id = 'term_' . $object->term_id;
		}

		return update_field( $name, $value, $acf_id );
	}

	/**
	 * This should replace all $this->get_meta calls, we need to abstract this for posts and taxs
	 *
	 * @param WP_Post|WP_Term $object
	 */
	protected function get_meta( $object, string $name, bool $single = false ) {
		if ( $object instanceof WP_Post ) {
			return get_post_meta( $object->ID, $name, $single );
		}

		if ( $object instanceof WP_Term ) {
			return get_term_meta( $object->term_id, $name, $single );
		}

		return null;
	}

	/**
	 * This should replace all set_post_meta calls, we need to abstract this for posts and taxs
	 *
	 * @param WP_Post|WP_Term $object
	 * @param mixed $value
	 */
	protected function set_meta( $object, string $name, $value ) {
		if ( $object instanceof WP_Post ) {
			return update_post_meta( $object->ID, $name, $value );
		}

		if ( $object instanceof WP_Term ) {
			return update_term_meta( $object->term_id, $name, $value );
		}

		return null;
	}

	private function check_to_record_visit(): bool {
		// Zeroly if we are in the admin section, is_archive is fired therefore if is_admin is true
		// We don't want to send any notifications
		if ( is_admin() ) {
			return false;
		}

		// Firstly if the page visited is NOT a single, singular or archive page, we don't care about it
		if ( ! ( is_singular() || is_archive() ) ) {
			return false;
		}

		$object = $this->get_object();

		if ( is_null( $object ) ) {
			return false;
		}

		// Secondly, check if the field is enabled in the post settings
		if ( ! $this->get_field( 'vn_enable_notifications' ) ) {
			return false;
		}

		// Now we get to more conditional checks
		// Check if user agent could be a bot
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

			if ( str_contains( strtolower( $user_agent ), 'bot' ) ) {
				return false;
			}
		}

		// Check what situation we have if a user is logged in
		$send_if_logged_in = $this->get_overridable_checkbox( $object, 'vn_logged_in', 'enable_logged_in_users' );
		if ( is_user_logged_in() && ! $send_if_logged_in ) {
			return false;
		}

		// Check if IP grace period is enabled and IP address has already fired
		if ( $this->settings->get( 'ip_grace_period' ) ) {
			$ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
			$key = md5( $this->anonymize_ip_address( $ip ) );

			if ( $this->settings->get( 'ip_grace_period_context' ) === 'site' ) {
				$graceperiod = get_option( self::TOKEN . '_ip_grace_period_data', [] );
			} elseif ( $this->settings->get( 'ip_grace_period_context' ) === 'post' ) {
				$post        = get_post();
				$graceperiod = $this->get_meta( $post, self::TOKEN . '_ip_grace_period_data', true ) ?: [];
			}
			$oldcount = count( $graceperiod );

			// 1) Filter out expired graces
			$graceperiod = array_filter(
				$graceperiod,
				static fn ( $value ) => $value > time(),
			);

			// 2) If number of graced IP addresses has changed, update the database
			if ( $oldcount !== count( $graceperiod ) ) {
				if ( $this->settings->get( 'ip_grace_period_context' ) === 'site' ) {
					update_option( self::TOKEN . '_ip_grace_period_data', $graceperiod );
				} elseif ( $this->settings->get( 'ip_grace_period_context' ) === 'post' ) {
					$this->set_meta( $post, self::TOKEN . '_ip_grace_period_data', $graceperiod );
				}
			}

			// 3) If key exists, return false
			if ( array_key_exists( $key, $graceperiod ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Anonymize the IP address given by the Request. Following what Google does in Google Analytics.
	 * @link https://support.google.com/analytics/answer/2763052?hl=en
	 *
	 * @param string $ip
	 *
	 * @return string
	 */
	private function anonymize_ip_address( string $ip ): string {
		$ip_length = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
			? 4
			: ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ? 16 : null );

		$overwrite = $ip_length === 4 ? 1 : 10;

		// Adapted from link below, to overwrite either the last octlet from IPv4 or the last 10 bytes of IPv6
		// https://stackoverflow.com/a/17533430/5682913

		// Convert the IP Address given from a string of either decimal (IPv4) or hexadecimal (IPv6)
		$value = current( unpack( "a{$ip_length}", inet_pton( $ip ) ) );

		// Based upon the version of the IP address, remove either 1 or 10 bytes from the value
		// and replace them with 0s
		$value = substr( $value, 0, $overwrite * -1 ) . implode( '', array_pad( [], $overwrite, "\x00" ) );

		// Convert back to decimal or hexadecimal for ease of reading
		return inet_ntop( pack( "A{$ip_length}", $value ) );
	}

	/* === GETTERS AND INSTANCES === */

	public function get_filename(): string {
		return $this->file;
	}

	public function get_directory(): string {
		return $this->dir;
	}

	public function get_assets_dir(): string {
		return $this->assets_dir;
	}

	public function get_assets_url(): string {
		return $this->assets_url;
	}

	public function settings_instance(): Settings {
		return $this->settings;
	}

	public function adminapi_instance(): AdminApi {
		return $this->admin_api;
	}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( 'Cloning of ' . self::class . ' is forbidden' ), esc_attr( self::VERSION ) );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( 'Unserializing instances of ' . self::class . ' is forbidden' ), esc_attr( self::VERSION ) );
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', function () {
		VisitNotifications::instance();
	} );
}
