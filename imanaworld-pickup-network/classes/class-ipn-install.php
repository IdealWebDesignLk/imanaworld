<?php
defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades every ipn_ custom table. Runs on activation and on
 * plugins_loaded whenever the stored ipn_db_version is behind IPN_DB_VERSION,
 * so a plugin update that adds columns/tables self-heals without a manual step.
 */
class IPN_Install {

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . IPN_TABLE_PREFIX . $name;
	}

	public static function maybe_upgrade() {
		if ( get_option( 'ipn_db_version' ) !== IPN_DB_VERSION ) {
			self::create_tables();
			update_option( 'ipn_db_version', IPN_DB_VERSION );
		}
	}

	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$branches         = self::table( 'branches' );
		$branch_hours     = self::table( 'branch_hours' );
		$branch_closures  = self::table( 'branch_closures' );
		$branch_stock     = self::table( 'branch_stock' );
		$otp_codes        = self::table( 'otp_codes' );
		$audit_log        = self::table( 'audit_log' );
		$import_log       = self::table( 'import_log' );
		$import_log_rows  = self::table( 'import_log_rows' );

		$sql = array();

		$sql[] = "CREATE TABLE {$branches} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			vendor_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(190) NOT NULL,
			code VARCHAR(40) NOT NULL,
			address TEXT NULL,
			phone VARCHAR(40) NULL,
			email VARCHAR(190) NULL,
			latitude DECIMAL(10,7) NULL,
			longitude DECIMAL(10,7) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			disabled_reason VARCHAR(255) NULL,
			express_enabled TINYINT(1) NOT NULL DEFAULT 0,
			express_surcharge DECIMAL(10,2) NOT NULL DEFAULT 0,
			express_prep_minutes INT UNSIGNED NOT NULL DEFAULT 60,
			standard_prep_minutes INT UNSIGNED NOT NULL DEFAULT 1440,
			otp_expiry_hours INT UNSIGNED NOT NULL DEFAULT 72,
			collection_window_days INT UNSIGNED NOT NULL DEFAULT 5,
			reminder_after_hours INT UNSIGNED NOT NULL DEFAULT 48,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY vendor_id (vendor_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$branch_hours} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT UNSIGNED NOT NULL,
			day_of_week TINYINT UNSIGNED NOT NULL,
			open_time TIME NULL,
			close_time TIME NULL,
			is_closed TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY branch_id (branch_id),
			UNIQUE KEY branch_day (branch_id, day_of_week)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$branch_closures} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT UNSIGNED NOT NULL,
			closure_date DATE NOT NULL,
			reason VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY branch_id (branch_id),
			KEY closure_date (closure_date)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$branch_stock} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			branch_id BIGINT UNSIGNED NOT NULL,
			total_stock INT NOT NULL DEFAULT 0,
			reserved_stock INT NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY product_branch (product_id, branch_id),
			KEY branch_id (branch_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$otp_codes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL,
			branch_id BIGINT UNSIGNED NOT NULL,
			otp_hash VARCHAR(255) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			verified_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY branch_id (branch_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$audit_log} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NULL,
			branch_id BIGINT UNSIGNED NULL,
			event_type VARCHAR(60) NOT NULL,
			actor_id BIGINT UNSIGNED NULL,
			actor_type VARCHAR(20) NOT NULL DEFAULT 'staff',
			data LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY branch_id (branch_id),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$import_log} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			filename VARCHAR(255) NOT NULL,
			total_rows INT UNSIGNED NOT NULL DEFAULT 0,
			created_count INT UNSIGNED NOT NULL DEFAULT 0,
			updated_count INT UNSIGNED NOT NULL DEFAULT 0,
			skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
			failed_count INT UNSIGNED NOT NULL DEFAULT 0,
			run_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$import_log_rows} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			import_id BIGINT UNSIGNED NOT NULL,
			row_number INT UNSIGNED NOT NULL,
			sku VARCHAR(100) NULL,
			status VARCHAR(20) NOT NULL,
			message VARCHAR(255) NULL,
			PRIMARY KEY  (id),
			KEY import_id (import_id)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			self::table( 'branches' ),
			self::table( 'branch_hours' ),
			self::table( 'branch_closures' ),
			self::table( 'branch_stock' ),
			self::table( 'otp_codes' ),
			self::table( 'audit_log' ),
			self::table( 'import_log' ),
			self::table( 'import_log_rows' ),
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
	}
}
