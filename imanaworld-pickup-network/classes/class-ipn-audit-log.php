<?php
defined( 'ABSPATH' ) || exit;

/**
 * Single write path for the operational audit trail (ipn_audit_log).
 * Every other class calls IPN_Audit_Log::log() instead of writing to the
 * table directly, so the event vocabulary stays centralized in one place.
 */
class IPN_Audit_Log {

	public static function table() {
		return IPN_Install::table( 'audit_log' );
	}

	public static function log( $event_type, array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'order_id'   => null,
			'branch_id'  => null,
			'actor_id'   => get_current_user_id() ?: null,
			'actor_type' => is_admin() ? 'admin' : 'staff',
			'data'       => array(),
		) );

		return $wpdb->insert( self::table(), array(
			'order_id'   => $args['order_id'],
			'branch_id'  => $args['branch_id'],
			'event_type' => $event_type,
			'actor_id'   => $args['actor_id'],
			'actor_type' => $args['actor_type'],
			'data'       => wp_json_encode( $args['data'] ),
			'created_at' => current_time( 'mysql' ),
		) );
	}

	public static function for_order( $order_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at ASC", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function query( array $args = array() ) {
		global $wpdb;
		$table  = self::table();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['branch_id'] ) ) {
			$where[]  = 'branch_id = %d';
			$params[] = (int) $args['branch_id'];
		}

		if ( ! empty( $args['event_type'] ) ) {
			$where[]  = 'event_type = %s';
			$params[] = $args['event_type'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['date_to'];
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT 500';

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Human label for an event_type value — shared by the staff order-detail
	 * audit card and the admin Orders/Disputes screens' order-detail modal.
	 */
	public static function describe_event( $event_type ) {
		$labels = array(
			'stock_reserved'        => __( 'Stock reserved', 'ipn' ),
			'stock_released'        => __( 'Stock released', 'ipn' ),
			'stock_sold'            => __( 'Stock deducted (sold)', 'ipn' ),
			'stock_adjusted'        => __( 'Stock manually adjusted', 'ipn' ),
			'otp_generated'         => __( 'Collection code generated', 'ipn' ),
			'otp_verify_failed'     => __( 'Collection code entry failed', 'ipn' ),
			'otp_verify_success'    => __( 'Collection code verified', 'ipn' ),
			'order_accepted'        => __( 'Order accepted by branch', 'ipn' ),
			'order_preparing'       => __( 'Order marked preparing', 'ipn' ),
			'order_ready'           => __( 'Order marked ready for collection', 'ipn' ),
			'collection_completed'  => __( 'Order collected', 'ipn' ),
			'collection_disputed'   => __( 'Collection rejected / disputed', 'ipn' ),
			'collection_expired'    => __( 'Collection window expired', 'ipn' ),
			'branch_created'        => __( 'Branch created', 'ipn' ),
			'branch_updated'        => __( 'Branch updated', 'ipn' ),
		);

		return isset( $labels[ $event_type ] ) ? $labels[ $event_type ] : ucfirst( str_replace( '_', ' ', $event_type ) );
	}
}
