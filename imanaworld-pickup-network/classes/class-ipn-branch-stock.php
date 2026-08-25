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

	/**
	 * Builds the shared WHERE clause behind query_products()/count_products()
	 * so the paginated page and its total row count can never disagree.
	 *
	 * @return array [ (string) sql, (array) params ]
	 */
	protected static function product_query_where( array $args ) {
		global $wpdb;

		$where  = array( "p.post_type IN ( 'product', 'product_variation' )" );
		$params = array();

		if ( ! empty( $args['branch_id'] ) ) {
			$where[]  = 's.branch_id = %d';
			$params[] = (int) $args['branch_id'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'p.post_title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * One row per *product* (not per product-branch pair), with its stock
	 * summed across whichever branches are in scope — the aggregated,
	 * server-paginated backbone of the admin Stock screen.
	 *
	 * The screen used to load every product-branch combination into a
	 * single flat table and filter it in the browser, which is fine for a
	 * pilot with two branches and five products and completely unworkable
	 * at real catalogue size (issue #7). Search, branch scoping, ordering,
	 * and paging all happen in SQL here instead.
	 *
	 * @param array $args {
	 *     @type int    $branch_id Restrict to one branch (0 = all branches).
	 *     @type string $search    Product-title substring.
	 *     @type int    $per_page
	 *     @type int    $page      1-based.
	 * }
	 * @return object[] product_id, product_name, total_stock, reserved_stock, branch_count.
	 */
	public static function query_products( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'branch_id' => 0,
			'search'    => '',
			'per_page'  => 25,
			'page'      => 1,
		) );

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = max( 0, ( max( 1, (int) $args['page'] ) - 1 ) * $per_page );

		$table                = self::table();
		list( $where, $params ) = self::product_query_where( $args );

		$sql = "SELECT s.product_id,
					p.post_title AS product_name,
					SUM(s.total_stock) AS total_stock,
					SUM(s.reserved_stock) AS reserved_stock,
					COUNT(DISTINCT s.branch_id) AS branch_count
				FROM {$table} s
				INNER JOIN {$wpdb->posts} p ON p.ID = s.product_id
				WHERE {$where}
				GROUP BY s.product_id, p.post_title
				ORDER BY p.post_title ASC
				LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Total number of products query_products() would return unpaginated —
	 * i.e. what the pager counts against.
	 */
	public static function count_products( array $args = array() ) {
		global $wpdb;

		$table                  = self::table();
		list( $where, $params ) = self::product_query_where( $args );

		$sql = "SELECT COUNT(DISTINCT s.product_id)
				FROM {$table} s
				INNER JOIN {$wpdb->posts} p ON p.ID = s.product_id
				WHERE {$where}";

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Per-branch breakdown for a specific set of products — one query for
	 * the whole page of products rather than one per row, so expanding a
	 * product's branch detail costs nothing extra at render time.
	 *
	 * @param int[] $product_ids
	 * @param int   $branch_id Optional single-branch restriction.
	 * @return array Keyed by product_id, each an array of rows with branch_id/branch_name/total_stock/reserved_stock.
	 */
	public static function get_branch_breakdown( array $product_ids, $branch_id = 0 ) {
		global $wpdb;

		$product_ids = array_values( array_unique( array_map( 'intval', $product_ids ) ) );

		if ( ! $product_ids ) {
			return array();
		}

		$table        = self::table();
		$branch_table = IPN_Branch::table();
		$placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
		$params       = $product_ids;
		$branch_where = '';

		if ( $branch_id ) {
			$branch_where = ' AND s.branch_id = %d';
			$params[]     = (int) $branch_id;
		}

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT s.product_id, s.branch_id, s.total_stock, s.reserved_stock, b.name AS branch_name, b.status AS branch_status
			 FROM {$table} s
			 INNER JOIN {$branch_table} b ON b.id = s.branch_id
			 WHERE s.product_id IN ({$placeholders}){$branch_where}
			 ORDER BY b.name ASC",
			$params
		) );

		$by_product = array();

		foreach ( $rows as $row ) {
			$by_product[ (int) $row->product_id ][] = $row;
		}

		return $by_product;
	}

	/**
	 * Which branches currently stock a product, for the customer-facing
	 * "available at" panel on the single product page (issue #8). Only
	 * active branches — a branch taken offline isn't somewhere a customer
	 * can collect from.
	 *
	 * @return object[] branch_id, branch_name, address, total_stock, reserved_stock, available.
	 */
	public static function get_availability_by_branch( $product_id ) {
		global $wpdb;

		$table        = self::table();
		$branch_table = IPN_Branch::table();

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT s.branch_id, s.total_stock, s.reserved_stock, b.name AS branch_name, b.address
			 FROM {$table} s
			 INNER JOIN {$branch_table} b ON b.id = s.branch_id
			 WHERE s.product_id = %d AND b.status = 'active'
			 ORDER BY b.name ASC",
			$product_id
		) );

		foreach ( $rows as $row ) {
			$row->available = max( 0, (int) $row->total_stock - (int) $row->reserved_stock );
		}

		return $rows;
	}

	/**
	 * True when a product has been brought into the per-branch stock model
	 * at all (via the CSV importer or the product-edit meta box). Products
	 * without a single stock row are plain WooCommerce products and are
	 * deliberately left alone by every branch-aware rule.
	 */
	public static function is_tracked( $product_id ) {
		global $wpdb;
		$table = self::table();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_id = %d", $product_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Product IDs with available (total - reserved) stock at a branch.
	 */
	public static function get_in_stock_product_ids( $branch_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT product_id FROM {$table} WHERE branch_id = %d AND (total_stock - reserved_stock) > 0", // phpcs:ignore WordPress.DB.PreparedSQL
			$branch_id
		) );
	}

	/**
	 * Every product a vendor has ever put into the per-branch stock table,
	 * across all of that vendor's branches — i.e. "every IPN-tracked
	 * product for this vendor," regardless of which branch. Used to work
	 * out which of a vendor's products should be hidden from the catalogue
	 * for a shopper at a branch where that product isn't in stock, without
	 * touching products this vendor never brought into the IPN stock model
	 * at all (or products belonging to other vendors entirely).
	 */
	public static function get_product_ids_for_vendor( $vendor_id ) {
		global $wpdb;
		$stock_table  = self::table();
		$branch_table = IPN_Branch::table();

		return $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT DISTINCT s.product_id FROM {$stock_table} s INNER JOIN {$branch_table} b ON b.id = s.branch_id WHERE b.vendor_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL
			$vendor_id
		) );
	}
}
