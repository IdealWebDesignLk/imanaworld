<?php
defined( 'ABSPATH' ) || exit;

/**
 * CRUD for the ipn_branches table plus per-branch operating hours and
 * one-off closure dates. Every other module (stock, staff, checkout,
 * reporting) resolves a branch through this class.
 */
class IPN_Branch {

	public static function table() {
		return IPN_Install::table( 'branches' );
	}

	public static function hours_table() {
		return IPN_Install::table( 'branch_hours' );
	}

	public static function closures_table() {
		return IPN_Install::table( 'branch_closures' );
	}

	public static function get( $branch_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $branch_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function get_all( $args = array() ) {
		global $wpdb;
		$table   = self::table();
		$where   = array( '1=1' );
		$params  = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['vendor_id'] ) ) {
			$where[]  = 'vendor_id = %d';
			$params[] = (int) $args['vendor_id'];
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY name ASC';

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function create( array $data ) {
		global $wpdb;

		$defaults = array(
			'status'                 => 'active',
			'express_enabled'        => 0,
			'express_surcharge'      => 0,
			'express_prep_minutes'   => 60,
			'standard_prep_minutes'  => 1440,
			'otp_expiry_hours'       => (int) get_option( 'ipn_otp_expiry_hours', 72 ),
			'collection_window_days' => (int) get_option( 'ipn_collection_window_days', 5 ),
			'reminder_after_hours'   => (int) get_option( 'ipn_reminder_after_hours', 48 ),
			'created_at'             => current_time( 'mysql' ),
			'updated_at'             => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		$wpdb->insert( self::table(), $data ); // phpcs:ignore WordPress.DB.PreparedSQL

		$branch_id = (int) $wpdb->insert_id;

		if ( $branch_id ) {
			IPN_Audit_Log::log( 'branch_created', array(
				'branch_id' => $branch_id,
				'data'      => array( 'name' => $data['name'] ),
			) );
		}

		return $branch_id;
	}

	public static function update( $branch_id, array $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$updated = $wpdb->update( self::table(), $data, array( 'id' => (int) $branch_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( false !== $updated ) {
			IPN_Audit_Log::log( 'branch_updated', array( 'branch_id' => $branch_id ) );
		}

		return $updated;
	}

	public static function set_status( $branch_id, $status, $reason = '' ) {
		return self::update( $branch_id, array(
			'status'          => $status,
			'disabled_reason' => $reason,
		) );
	}

	public static function get_hours( $branch_id ) {
		global $wpdb;
		$table = self::hours_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE branch_id = %d ORDER BY day_of_week ASC", $branch_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function is_open_now( $branch_id ) {
		global $wpdb;

		$closures_table = self::closures_table();
		$today          = current_time( 'Y-m-d' );

		$closed_today = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$closures_table} WHERE branch_id = %d AND closure_date = %s", $branch_id, $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( $closed_today ) {
			return false;
		}

		$hours_table = self::hours_table();
		$day         = (int) current_time( 'w' );
		$now         = current_time( 'H:i:s' );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$hours_table} WHERE branch_id = %d AND day_of_week = %d", $branch_id, $day ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( ! $row || $row->is_closed ) {
			return false;
		}

		return $now >= $row->open_time && $now <= $row->close_time;
	}
}
