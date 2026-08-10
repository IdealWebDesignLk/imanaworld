<?php
defined( 'ABSPATH' ) || exit;

/**
 * IPN operational reporting (Section 3.15) — the 8 admin-only reports,
 * each filterable by date range and branch and exportable as CSV.
 * Query implementations are Phase 5 build-out; signatures fixed now so
 * the admin reports page can be wired against a stable contract.
 */
class IPN_Reports {

	public static function orders_by_branch( $date_from, $date_to, $branch_id = 0 ) {
		return array();
	}

	public static function collection_success_rate( $date_from, $date_to, $branch_id = 0 ) {
		return array();
	}

	public static function uncollected_orders( $date_from, $date_to, $branch_id = 0 ) {
		return array();
	}

	public static function average_preparation_time( $date_from, $date_to, $branch_id = 0 ) {
		return array();
	}

	public static function collection_turnaround_time( $date_from, $date_to, $branch_id = 0 ) {
		return array();
	}

	public static function product_performance_by_branch( $date_from, $date_to, $branch_id = 0 ) {
		return array();
	}

	public static function branch_sales_performance( $date_from, $date_to ) {
		return array();
	}

	public static function express_vs_standard_split( $date_from, $date_to, $branch_id = 0 ) {
		return array();
	}
}
