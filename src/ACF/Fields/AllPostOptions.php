<?php
declare( strict_types=1 );

namespace WatchTheDot\VisitNotifications\ACF\Fields;

defined( 'ABSPATH' ) || exit;

use StoutLogic\AcfBuilder\FieldsBuilder;

class AllPostOptions {
	private $parent;

	public function __construct( $parent ) {
		$this->parent = $parent;
	}

	public function load_vn_enable_notifications( array $field ): array {
		if ( is_admin() ) {
			$screen = get_current_screen();

			if ( $screen->base === 'term' || $screen->base === 'edit-tags' ) {
				$field['instructions'] = 'This will be enabled for the taxonomy page (Not each post under this taxonomy)';
			}
		}

		return $field;
	}

	public function load_vn_logged_in( array $field ): array {
		$status = $this->parent->settings_instance()->get( 'enable_logged_in_users' ) ? 'Enabled' : 'Disabled';

		$field['choices']['global'] = sprintf(
			$field['choices']['global'],
			__( $status, 'visitnotifications' ),
		);

		return $field;
	}

	public function __invoke(): FieldsBuilder {
		$builder = new FieldsBuilder( $this->parent::NAME, [ 'position' => 'side' ] );

		$builder
			->addTrueFalse(
				'vn_enable_notifications',
				[
					'label' => __( 'Enable Notifications', 'visitnotifications' ),
					'ui'    => true,
				]
			)
			->addSelect(
				'vn_schedule',
				[
					'label'         => __( 'Schedule', 'visitnotifications' ),
					'instructions'  => __( 'What schedule should notifications be sent?', 'visitnotifications' ),
					'choices'       => [
						'visit'  => __( 'On Visit', 'visitnotifications' ),
						'hourly' => __( 'Hourly', 'visitnotifications' ),
						'daily'  => __( 'Daily', 'visitnotifications' ),
					],
					'default_value' => 'default',
					'ui'            => true,
				]
			)->conditional( 'vn_enable_notifications', '==', '1' )
			->addSelect(
				'vn_logged_in',
				[
					'label'         => __( 'Logged In Users', 'visitnotifications' ),
					'instructions'  => __( 'Send notifications when logged in users visit the page', 'visitnotifications' ),
					'choices'       => [
						'global' => __( 'Respect Global (%s)', 'visitnotifications' ),
						'on'     => __( 'Enabled', 'visitnotifications' ),
						'off'    => __( 'Disabled', 'visitnotifications' ),
					],
					'default_value' => 'global',
				]
			)->conditional( 'vn_enable_notifications', '==', '1' );

		$func = 'setLocation';
		$obj  = $builder;
		foreach ( array_keys( get_post_types() ) as $post_type ) {
			$obj = $obj->{$func}( 'post_type', '==', $post_type );

			$func = 'or';
		}

		$obj->or( 'taxonomy', '==', 'all' );

		add_filter( 'acf/load_field/name=vn_enable_notifications', [ $this, 'load_vn_enable_notifications' ] );
		add_filter( 'acf/load_field/name=vn_logged_in', [ $this, 'load_vn_logged_in' ] );
		return $builder;
	}
}
