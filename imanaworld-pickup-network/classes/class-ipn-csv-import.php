<?php
defined( 'ABSPATH' ) || exit;

/**
 * CSV/Excel catalogue import (Section 3.3). Creates/updates Choppies Dokan
 * products by SKU from an uploaded file with a Branch ID stock-mapping column.
 * Handler wiring lives in IPN_Admin; parsing/mapping logic is Phase 2 work.
 */
class IPN_CSV_Import {

	public static function log_table() {
		return IPN_Install::table( 'import_log' );
	}

	public static function log_rows_table() {
		return IPN_Install::table( 'import_log_rows' );
	}

	/**
	 * @param string $file_path Path to the uploaded CSV/XLSX file.
	 * @return int|WP_Error Import log ID on success.
	 */
	public static function process_file( $file_path, $run_by = 0 ) {
		return new WP_Error( 'ipn_not_implemented', __( 'Catalogue import is not implemented yet.', 'ipn' ) );
	}

	public static function get_recent_runs( $limit = 20 ) {
		global $wpdb;
		$table = self::log_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
