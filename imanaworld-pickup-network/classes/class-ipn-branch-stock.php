<?php
defined( 'ABSPATH' ) || exit;

/**
 * The custom per-branch stock layer (ipn_branch_stock). WooCommerce's native
 * stock field is not used for IPN-enabled vendor products — this table is
 * the single source of truth. Available stock = total_stock - reserved_stock.
 */
class IPN_Branch_Stock {

	public static function table() {
		return IPN_Install::table( 'branch_stock' );
	}

	public static function get_row( $product_id, $branch_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE product_id = %d AND branch_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL
			$product_id,
			$branch_id
		) );
	}

	public static function get_available( $product_id, $branch_id ) {
		$row = self::get_row( $product_id, $branch_id );
		if ( ! $row ) {
			return 0;
		}
		return max( 0, (int) $row->total_stock - (int) $row->reserved_stock );
	}

	public static function set_total( $product_id, $branch_id, $total_stock ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql' );

		$existing = self::get_row( $product_id, $branch_id );

		if ( $existing ) {
			return $wpdb->update(
				$table,
				array(
					'total_stock' => (int) $total_stock,
					'updated_at'  => $now,
				),
				array(
					'product_id' => (int) $product_id,
					'branch_id'  => (int) $branch_id,
				)
			);
		}

		return $wpdb->insert(
			$table,
			array(
				'product_id'     => (int) $product_id,
				'branch_id'      => (int) $branch_id,
				'total_stock'    => (int) $total_stock,
				'reserved_stock' => 0,
				'updated_at'     => $now,
			)
		);
	}

	/**
	 * Moves quantity from available into reserved. Fails (returns false)
	 * rather than oversell if two customers race for the last unit —
	 * the UPDATE ... WHERE guard makes the check-and-reserve atomic.
	 */
	public static function reserve( $product_id, $branch_id, $quantity, $order_id = 0 ) {
		global $wpdb;
		$table = self::table();

		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET reserved_stock = reserved_stock + %d, updated_at = %s
			 WHERE product_id = %d AND branch_id = %d AND (total_stock - reserved_stock) >= %d", // phpcs:ignore WordPress.DB.PreparedSQL
			$quantity,
			current_time( 'mysql' ),
			$product_id,
			$branch_id,
			$quantity
		) );

		if ( $updated ) {
			IPN_Audit_Log::log( 'stock_reserved', array(
				'order_id'  => $order_id,
				'branch_id' => $branch_id,
				'data'      => array( 'product_id' => $product_id, 'quantity' => $quantity ),
			) );
		}

		return (bool) $updated;
	}

	public static function release( $product_id, $branch_id, $quantity, $order_id = 0, $reason = 'cancelled' ) {
		global $wpdb;
		$table = self::table();

		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET reserved_stock = GREATEST(0, reserved_stock - %d), updated_at = %s
			 WHERE product_id = %d AND branch_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL
			$quantity,
			current_time( 'mysql' ),
			$product_id,
			$branch_id
		) );

		if ( $updated ) {
			IPN_Audit_Log::log( 'stock_released', array(
				'order_id'  => $order_id,
				'branch_id' => $branch_id,
				'data'      => array( 'product_id' => $product_id, 'quantity' => $quantity, 'reason' => $reason ),
			) );
		}

		return (bool) $updated;
	}

	/**
	 * Order collected — reserved units are permanently deducted from total_stock.
	 */
	public static function deduct_sold( $product_id, $branch_id, $quantity, $order_id = 0 ) {
		global $wpdb;
		$table = self::table();

		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET total_stock = GREATEST(0, total_stock - %d), reserved_stock = GREATEST(0, reserved_stock - %d), updated_at = %s
			 WHERE product_id = %d AND branch_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL
			$quantity,
			$quantity,
			current_time( 'mysql' ),
			$product_id,
			$branch_id
		) );

		if ( $updated ) {
			IPN_Audit_Log::log( 'stock_sold', array(
				'order_id'  => $order_id,
				'branch_id' => $branch_id,
				'data'      => array( 'product_id' => $product_id, 'quantity' => $quantity ),
			) );
		}

		return (bool) $updated;
	}

	public static function get_stock_for_branch( $branch_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE branch_id = %d", $branch_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
