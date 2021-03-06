<?php
/**
 * Handles installation of Blocks plugin dependencies.
 *
 * @package WooCommerce/Blocks
 */

namespace Automattic\WooCommerce\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Installer class.
 */
class Installer {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Installation tasks ran on admin_init callback.
	 */
	public function install() {
		$this->maybe_create_tables();
		$this->maybe_create_cronjobs();
	}

	/**
	 * Initialize class features.
	 */
	protected function init() {
		add_action( 'admin_init', array( $this, 'install' ) );
	}

	/**
	 * Set up the database tables which the plugin needs to function.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$schema_version    = 260;
		$db_schema_version = (int) get_option( 'wc_blocks_db_schema_version', 0 );

		if ( $db_schema_version > $schema_version ) {
			return;
		}

		$show_errors = $wpdb->hide_errors();
		$table_name  = strtolower($wpdb->prefix . 'wc_reserved_stock');
		$collate     = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';
		$exists      = $this->maybe_create_table(
			$wpdb->prefix . 'wc_reserved_stock',
			"
			CREATE TABLE $table_name (
				<code>order_id</code> bigint(20) NOT NULL,
				<code>product_id</code> bigint(20) NOT NULL,
				<code>stock_quantity</code> double NOT NULL DEFAULT 0,
				<code>timestamp</code> datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				<code>expires</code> datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (<code>order_id</code>, <code>product_id</code>)
			) $collate;
			"
		);

		if ( $show_errors ) {
			$wpdb->show_errors();
		}

		if ( ! $exists ) {
			return $this->add_create_table_notice( $table_name );
		}

		// Update succeeded. This is only updated when successful and validated.
		// $schema_version should be incremented when changes to schema are made within this method.
		update_option( 'wc_blocks_db_schema_version', $schema_version );
	}

	/**
	 * Create database table, if it doesn't already exist.
	 *
	 * Based on admin/install-helper.php maybe_create_table function.
	 *
	 * @param string $table_name Database table name.
	 * @param string $create_sql Create database table SQL.
	 * @return bool False on error, true if already exists or success.
	 */
	protected function maybe_create_table( $table_name, $create_sql ) {
		global $wpdb;

		if ( in_array( strtolower($table_name), $wpdb->get_col( 'SHOW TABLES', 0 ), true ) ) {
			return true;
		}

		$wpdb->query( $create_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return in_array( strtolower($table_name), $wpdb->get_col( 'SHOW TABLES', 0 ), true );
	}

	/**
	 * Add a notice if table creation fails.
	 *
	 * @param string $table_name Name of the missing table.
	 */
	protected function add_create_table_notice( $table_name ) {
		add_action(
			'admin_notices',
			function() use ( $table_name ) {
				echo '<div class="error"><p>';
				printf(
					/* Translators: %1$s table name, %2$s database user, %3$s database name. */
					esc_html__( 'WooCommerce %1$s table creation failed. Does the %2$s user have CREATE privileges on the %3$s database?', 'woocommerce' ),
					'<code>' . esc_html( $table_name ) . '</code>',
					'<code>' . esc_html( DB_USER ) . '</code>',
					'<code>' . esc_html( DB_NAME ) . '</code>'
				);
				echo '</p></div>';
			}
		);
	}

	/**
	 * Maybe create cron events.
	 */
	protected function maybe_create_cronjobs() {
		if ( function_exists( 'as_next_scheduled_action' ) && false === as_next_scheduled_action( 'woocommerce_cleanup_draft_orders' ) ) {
			as_schedule_recurring_action( strtotime( 'midnight tonight' ), DAY_IN_SECONDS, 'woocommerce_cleanup_draft_orders' );
		}
	}
}
