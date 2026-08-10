<?php
defined( 'ABSPATH' ) || exit;

/**
 * IPN operational reporting (Section 3.15) — the 8 admin-only reports,
 * filterable by date range and branch. Orders are found via wc_get_orders()
 * against the real _ipn_branch_id order meta IPN_Order writes at checkout
 * (works whether the store uses HPOS or legacy post-based orders), and the
 * two duration reports read paired audit-log timestamps rather than a
 * separate "started preparing" table that doesn't exist.
 */
class IPN_Reports {

	public static function orders_by_branch( $date_from, $date_to, $branch_id = 0 ) {
		$branches = $branch_id ? array_filter( array( IPN_Branch::get( $branch_id ) ) ) : IPN_Branch::get_all();
		$rows     = array();

		foreach ( $branches as $branch ) {
			$rows[] = (object) array(
				'branch_id'   => $branch->id,
				'branch_name' => $branch->name,
				'count'       => count( self::get_order_ids( $date_from, $date_to, $branch->id ) ),
			);
		}

		return $rows;
	}

	/**
	 * @return array{collected:int,lost:int,total:int,success_rate:float|null}
	 */
	public static function collection_success_rate( $date_from, $date_to, $branch_id = 0 ) {
		$collected = 0;
		$lost      = 0;

		foreach ( self::get_orders( $date_from, $date_to, $branch_id ) as $order ) {
			$status = $order->get_status();

			if ( 'completed' === $status ) {
				$collected++;
			} elseif ( in_array( $status, array( 'cancelled', 'refunded', 'ipn-expired', 'ipn-disputed' ), true ) ) {
				$lost++;
			}
		}

		$total = $collected + $lost;

		return array(
			'collected'    => $collected,
			'lost'         => $lost,
			'total'        => $total,
			'success_rate' => $total ? round( ( $collected / $total ) * 100, 1 ) : null,
		);
	}

	/**
	 * Orders currently sitting Ready for Collection within the filtered
	 * range — the live "who still needs to come pick this up" list.
	 */
	public static function uncollected_orders( $date_from, $date_to, $branch_id = 0 ) {
		$rows = array();

		foreach ( self::get_orders( $date_from, $date_to, $branch_id ) as $order ) {
			if ( 'ipn-ready' !== $order->get_status() ) {
				continue;
			}

			$meta   = IPN_Order::get_meta( $order->get_id() );
			$branch = ( $meta && $meta->branch_id ) ? IPN_Branch::get( $meta->branch_id ) : null;

			$rows[] = (object) array(
				'order_id'     => $order->get_id(),
				'order_number' => $order->get_order_number(),
				'branch_name'  => $branch ? $branch->name : '',
				'ready_at'     => ( $meta && $meta->ready_at ) ? $meta->ready_at : '',
			);
		}

		return $rows;
	}

	/**
	 * Accepted -> Ready, per branch, in seconds. There's no dedicated
	 * "accepted at" / "ready at" pair of columns for this — it's read from
	 * the audit log's order_accepted/order_ready timestamps instead, which
	 * IPN_Order already writes on every real transition.
	 */
	public static function average_preparation_time( $date_from, $date_to, $branch_id = 0 ) {
		return self::average_duration_between( 'order_accepted', 'order_ready', $date_from, $date_to, $branch_id );
	}

	/**
	 * Ready -> Collected, per branch, in seconds.
	 */
	public static function collection_turnaround_time( $date_from, $date_to, $branch_id = 0 ) {
		return self::average_duration_between( 'order_ready', 'collection_completed', $date_from, $date_to, $branch_id );
	}

	/**
	 * Top sellers by quantity, counting only orders that actually reached
	 * Collected — an order that was never picked up isn't a "sale".
	 */
	public static function product_performance_by_branch( $date_from, $date_to, $branch_id = 0 ) {
		$totals = array();

		foreach ( self::get_orders( $date_from, $date_to, $branch_id ) as $order ) {
			if ( 'completed' !== $order->get_status() ) {
				continue;
			}

			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();

				if ( ! isset( $totals[ $product_id ] ) ) {
					$totals[ $product_id ] = array(
						'name' => $item->get_name(),
						'qty'  => 0,
					);
				}

				$totals[ $product_id ]['qty'] += $item->get_quantity();
			}
		}

		$rows = array();

		foreach ( $totals as $product_id => $data ) {
			$rows[] = (object) array(
				'product_id' => $product_id,
				'name'       => $data['name'],
				'qty_sold'   => $data['qty'],
			);
		}

		usort( $rows, function ( $a, $b ) {
			return $b->qty_sold <=> $a->qty_sold;
		} );

		return array_slice( $rows, 0, 10 );
	}

	/**
	 * Revenue + order volume per branch, collected orders only, sorted
	 * highest revenue first.
	 */
	public static function branch_sales_performance( $date_from, $date_to ) {
		$rows = array();

		foreach ( IPN_Branch::get_all() as $branch ) {
			$revenue = 0.0;
			$count   = 0;

			foreach ( self::get_orders( $date_from, $date_to, $branch->id ) as $order ) {
				if ( 'completed' !== $order->get_status() ) {
					continue;
				}
				$revenue += (float) $order->get_total();
				$count++;
			}

			$rows[] = (object) array(
				'branch_id'   => $branch->id,
				'branch_name' => $branch->name,
				'revenue'     => $revenue,
				'orders'      => $count,
			);
		}

		usort( $rows, function ( $a, $b ) {
			return $b->revenue <=> $a->revenue;
		} );

		return $rows;
	}

	/**
	 * @return array{standard:array{count:int,revenue:float},express:array{count:int,revenue:float}}
	 */
	public static function express_vs_standard_split( $date_from, $date_to, $branch_id = 0 ) {
		$split = array(
			'standard' => array(
				'count'   => 0,
				'revenue' => 0.0,
			),
			'express'  => array(
				'count'   => 0,
				'revenue' => 0.0,
			),
		);

		foreach ( self::get_orders( $date_from, $date_to, $branch_id ) as $order ) {
			$meta = IPN_Order::get_meta( $order->get_id() );
			$type = ( $meta && 'express' === $meta->collection_type ) ? 'express' : 'standard';

			$split[ $type ]['count']++;
			$split[ $type ]['revenue'] += (float) $order->get_total();
		}

		return $split;
	}

	// ---- shared helpers ----

	/**
	 * Every IPN order (has _ipn_branch_id order meta) created within the
	 * date range, optionally scoped to one branch. Uses wc_get_orders()
	 * rather than raw SQL against wp_posts so this works whether the store
	 * is on HPOS or legacy post-based orders.
	 *
	 * @return int[]
	 */
	protected static function get_order_ids( $date_from, $date_to, $branch_id = 0 ) {
		$args = array(
			'return'       => 'ids',
			'limit'        => -1,
			'date_created' => $date_from . '...' . $date_to,
			'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				$branch_id
					? array(
						'key'   => '_ipn_branch_id',
						'value' => $branch_id,
					)
					: array(
						'key'     => '_ipn_branch_id',
						'compare' => 'EXISTS',
					),
			),
		);

		return wc_get_orders( $args );
	}

	/**
	 * @return WC_Order[]
	 */
	protected static function get_orders( $date_from, $date_to, $branch_id = 0 ) {
		$orders = array();

		foreach ( self::get_order_ids( $date_from, $date_to, $branch_id ) as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order ) {
				$orders[] = $order;
			}
		}

		return $orders;
	}

	/**
	 * Pairs two audit-log event types per order (e.g. order_accepted and
	 * order_ready) and averages the time between them, per branch.
	 *
	 * @return object[] Each: branch_id, branch_name, avg_seconds (float|null), sample_size (int).
	 */
	protected static function average_duration_between( $start_event, $end_event, $date_from, $date_to, $branch_id ) {
		global $wpdb;
		$table = IPN_Audit_Log::table();

		$where  = array( 'event_type IN (%s, %s)', 'created_at >= %s', 'created_at <= %s' );
		$params = array( $start_event, $end_event, $date_from . ' 00:00:00', $date_to . ' 23:59:59' );

		if ( $branch_id ) {
			$where[]  = 'branch_id = %d';
			$params[] = $branch_id;
		}

		$sql = "SELECT order_id, branch_id, event_type, created_at FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY order_id ASC, created_at ASC'; // phpcs:ignore WordPress.DB.PreparedSQL

		$log_rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		$by_order = array();

		foreach ( $log_rows as $row ) {
			$by_order[ $row->order_id ][ $row->event_type ] = $row;
		}

		$durations_by_branch = array();

		foreach ( $by_order as $events ) {
			if ( ! isset( $events[ $start_event ], $events[ $end_event ] ) ) {
				continue;
			}

			$seconds = strtotime( $events[ $end_event ]->created_at ) - strtotime( $events[ $start_event ]->created_at );

			if ( $seconds < 0 ) {
				continue;
			}

			$branch_key = $events[ $end_event ]->branch_id ? (int) $events[ $end_event ]->branch_id : (int) $events[ $start_event ]->branch_id;
			$durations_by_branch[ $branch_key ][] = $seconds;
		}

		$branches = $branch_id ? array_filter( array( IPN_Branch::get( $branch_id ) ) ) : IPN_Branch::get_all();
		$rows     = array();

		foreach ( $branches as $branch ) {
			$durations   = isset( $durations_by_branch[ $branch->id ] ) ? $durations_by_branch[ $branch->id ] : array();
			$avg_seconds = $durations ? array_sum( $durations ) / count( $durations ) : null;

			$rows[] = (object) array(
				'branch_id'   => $branch->id,
				'branch_name' => $branch->name,
				'avg_seconds' => $avg_seconds,
				'sample_size' => count( $durations ),
			);
		}

		return $rows;
	}
}
