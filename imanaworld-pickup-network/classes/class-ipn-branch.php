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

	/**
	 * Resolves the human-readable branch code (e.g. "CHP-GBE-01") a
	 * catalogue import file references, since Choppies has no reason to
	 * know our internal numeric branch IDs.
	 */
	public static function get_by_code( $code ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s", $code ) ); // phpcs:ignore WordPress.DB.PreparedSQL
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

	/**
	 * Replaces all 7 days of a branch's weekly hours in one call — the admin
	 * Branches modal always submits the full week, not a single day.
	 *
	 * @param int   $branch_id
	 * @param array $days Keyed 0 (Sunday) through 6 (Saturday), each an
	 *                     array with is_closed (bool) and, when open,
	 *                     open_time/close_time ("H:i" strings).
	 */
	public static function set_hours( $branch_id, array $days ) {
		global $wpdb;
		$table = self::hours_table();

		for ( $day_of_week = 0; $day_of_week <= 6; $day_of_week++ ) {
			$day       = isset( $days[ $day_of_week ] ) ? $days[ $day_of_week ] : array();
			$is_closed = ! empty( $day['is_closed'] );

			$wpdb->replace( $table, array( // phpcs:ignore WordPress.DB.PreparedSQL
				'branch_id'   => (int) $branch_id,
				'day_of_week' => $day_of_week,
				'open_time'   => ( ! $is_closed && ! empty( $day['open_time'] ) ) ? $day['open_time'] : null,
				'close_time'  => ( ! $is_closed && ! empty( $day['close_time'] ) ) ? $day['close_time'] : null,
				'is_closed'   => $is_closed ? 1 : 0,
			) );
		}
	}

	public static function get_closures( $branch_id ) {
		global $wpdb;
		$table = self::closures_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE branch_id = %d ORDER BY closure_date ASC", $branch_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function add_closure( $branch_id, $closure_date, $reason = '' ) {
		global $wpdb;

		$wpdb->insert( self::closures_table(), array( // phpcs:ignore WordPress.DB.PreparedSQL
			'branch_id'    => (int) $branch_id,
			'closure_date' => $closure_date,
			'reason'       => $reason,
			'created_at'   => current_time( 'mysql' ),
		) );

		return (int) $wpdb->insert_id;
	}

	public static function delete_closure( $closure_id ) {
		global $wpdb;
		return $wpdb->delete( self::closures_table(), array( 'id' => (int) $closure_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function is_open_now( $branch_id ) {
		$state = self::open_state( $branch_id );
		return $state->is_open;
	}

	/**
	 * Whether a branch is open *right now*, and why not when it isn't.
	 *
	 * This is deliberately separate from the branch's `status` column:
	 * `status` is the lifecycle flag an admin sets ("this branch is part of
	 * the network" vs. "taken offline"), while this is today's operating
	 * hours plus any one-off closure date. A branch can perfectly well be
	 * Active and Closed now — orders placed while it's closed are simply
	 * prepared once it reopens (operating hours are advisory, confirmed
	 * decision) — but the two were previously indistinguishable in the
	 * admin, where a branch shut for the day still read only as "Active".
	 *
	 * @return object {
	 *     @type bool        $is_open
	 *     @type string      $reason  One of: open, closure, day_closed, outside_hours, no_hours.
	 *     @type string      $label   Short human-readable state, e.g. "Closed now".
	 *     @type string      $detail  Why, e.g. "Public holiday" or "Opens 08:00".
	 *     @type object|null $hours   Today's ipn_branch_hours row, when there is one.
	 * }
	 */
	public static function open_state( $branch_id ) {
		global $wpdb;

		$closures_table = self::closures_table();
		$today          = current_time( 'Y-m-d' );

		$closure = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$closures_table} WHERE branch_id = %d AND closure_date = %s", $branch_id, $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( $closure ) {
			return self::open_state_result( false, 'closure', $closure->reason ? $closure->reason : __( 'Closed for the day', 'ipn' ) );
		}

		$hours_table = self::hours_table();
		$day         = (int) current_time( 'w' );
		$now         = current_time( 'H:i:s' );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$hours_table} WHERE branch_id = %d AND day_of_week = %d", $branch_id, $day ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( ! $row ) {
			return self::open_state_result( false, 'no_hours', __( 'Opening hours not set', 'ipn' ) );
		}

		if ( $row->is_closed || ! $row->open_time || ! $row->close_time ) {
			return self::open_state_result( false, 'day_closed', __( 'Closed today', 'ipn' ), $row );
		}

		$open  = substr( (string) $row->open_time, 0, 5 );
		$close = substr( (string) $row->close_time, 0, 5 );

		if ( $now < $row->open_time ) {
			/* translators: %s: today's opening time, e.g. "08:00" */
			return self::open_state_result( false, 'outside_hours', sprintf( __( 'Opens %s', 'ipn' ), $open ), $row );
		}

		if ( $now > $row->close_time ) {
			/* translators: %s: today's closing time, e.g. "19:00" */
			return self::open_state_result( false, 'outside_hours', sprintf( __( 'Closed since %s', 'ipn' ), $close ), $row );
		}

		/* translators: %s: today's closing time, e.g. "19:00" */
		return self::open_state_result( true, 'open', sprintf( __( 'Until %s', 'ipn' ), $close ), $row );
	}

	protected static function open_state_result( $is_open, $reason, $detail, $hours = null ) {
		return (object) array(
			'is_open' => (bool) $is_open,
			'reason'  => $reason,
			'label'   => $is_open ? __( 'Open now', 'ipn' ) : __( 'Closed now', 'ipn' ),
			'detail'  => $detail,
			'hours'   => $hours,
		);
	}
}
