<?php
declare( strict_types=1 );

namespace WatchTheDot\VisitNotifications\Migrator;

defined( 'ABSPATH' ) || exit;

class DataMigrator {
	/**
	 * The main plugin object.
	 */
	private $parent;

	public function __construct( $parent ) {
		$this->parent = $parent;

		register_activation_hook( $parent->get_filename(), [ $this, 'check_versions' ] );
		add_action( 'init', [ $this, 'check_versions' ], 1 );
	}

	public function check_versions() {
		$version = get_option( $this->parent::TOKEN . '_version', '' );

		$option = empty( $version ) ? 'add_option' : 'update_option';
		$option( $this->parent::TOKEN . '_version', $this->parent::VERSION );
	}
}
