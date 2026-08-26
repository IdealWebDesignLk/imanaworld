<?php
defined( 'ABSPATH' ) || exit;

/**
 * The order-status wiring the rest of the plugin depends on (checklist item
 * #1 — almost every other "done" piece was waiting on this). Maps a
 * WooCommerce/Dokan order to its IPN branch, collection type, and nominated
 * recipient (ipn_order_meta), registers the wc-ipn-* branch-fulfilment
 * statuses, and drives stock reserve/release/deduct, OTP generation, and the
 * ipn_order_* notification hooks off real order-status transitions.
 *
 * Flow: wc-processing (payment done) -> wc-ipn-accepted -> wc-ipn-preparing
 * -> wc-ipn-ready -> wc-completed (collected). wc-ipn-disputed and
 * wc-ipn-expired are exception branches off wc-ipn-ready.
 */
class IPN_Order {

	public static function table() {
		return IPN_Install::table( 'order_meta' );
	}

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'init', $this, 'register_order_statuses' );
		$loader->add_filter( 'wc_order_statuses', $this, 'register_order_status_labels' );

		$loader->add_action( 'woocommerce_order_status_processing', $this, 'on_processing' );
		$loader->add_action( 'woocommerce_order_status_cancelled', $this, 'on_cancelled_or_refunded' );
		$loader->add_action( 'woocommerce_order_status_refunded', $this, 'on_cancelled_or_refunded' );
		$loader->add_action( 'woocommerce_order_status_failed', $this, 'on_cancelled_or_refunded' );

		$loader->add_action( 'woocommerce_order_status_ipn-accepted', $this, 'on_accepted' );
		$loader->add_action( 'woocommerce_order_status_ipn-preparing', $this, 'on_preparing' );
		$loader->add_action( 'woocommerce_order_status_ipn-ready', $this, 'on_ready' );
		$loader->add_action( 'woocommerce_order_status_completed', $this, 'on_completed' );
		$loader->add_action( 'woocommerce_order_status_ipn-disputed', $this, 'on_disputed' );
		$loader->add_action( 'woocommerce_order_status_ipn-expired', $this, 'on_expired' );
	}

	/**
	 * Maps a WooCommerce order status (no wc- prefix) to the simplified
	 * display status the staff dashboard and My Account tracker render.
	 *
	 * Anything not listed here is not shown as an IPN order at all, so the
	 * map is a deliberate allow-list rather than an oversight — but it has to
	 * include the states an order genuinely passes through. Awaiting-payment
	 * orders are listed so a branch can see them, and are deliberately given
	 * their own display status rather than being folded into "new": stock is
	 * only reserved once payment lands (see on_processing), so a branch must
	 * not start preparing one. IPN_Staff_Dashboard::NEXT_STATUS has no entry
	 * for it, which is what withholds the Accept action.
	 */
	public static function display_status( $wc_status ) {
		$map = array(
			// An order awaiting payment is still a real Click & Collect order
			// and the branch needs to know it exists. WooCommerce puts every
			// offline payment method here — bank transfer, cheque, cash on
			// delivery — so leaving these unmapped made those orders invisible
			// to the vendor and to branch staff alike, which is exactly what
			// issue #24 reported.
			'on-hold'       => 'awaiting-payment',
			'pending'       => 'awaiting-payment',
			'processing'    => 'new',
			'ipn-accepted'  => 'accepted',
			'ipn-preparing' => 'preparing',
			'ipn-ready'     => 'ready',
			'completed'     => 'collected',
			'ipn-disputed'  => 'disputed',
			'ipn-expired'   => 'expired',
		);

		return isset( $map[ $wc_status ] ) ? $map[ $wc_status ] : null;
	}

	/**
	 * Shared "who's picking this up" label for an order — used by the staff
	 * dashboard, admin Orders/Disputes screens, and reports.
	 */
	public static function customer_name( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return '';
		}
		$name = trim( $order->get_formatted_billing_full_name() );
		return $name ? $name : $order->get_billing_email();
	}

	public static function time_label( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return '';
		}

		$created = $order->get_date_created();

		if ( ! $created ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created->getTimestamp() );
	}

	/**
	 * Human label for a dispute_reason value stored on ipn_order_meta —
	 * the staff order-detail reject panel only ever submits one of these
	 * fixed keys (see templates/staff/order-detail.php).
	 */
	public static function dispute_reason_label( $reason ) {
		$labels = array(
			'damaged'               => __( 'Item damaged', 'ipn' ),
			'wrong_item'            => __( 'Wrong item picked', 'ipn' ),
			'customer_changed_mind' => __( 'Customer changed mind', 'ipn' ),
			'other'                 => __( 'Other', 'ipn' ),
		);

		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
	}

	public function register_order_statuses() {
		register_post_status( 'wc-ipn-accepted', array(
			'label'                     => _x( 'Accepted by Branch', 'Order status', 'ipn' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders. */
			'label_count'               => _n_noop( 'Accepted by Branch <span class="count">(%s)</span>', 'Accepted by Branch <span class="count">(%s)</span>', 'ipn' ),
		) );

		register_post_status( 'wc-ipn-preparing', array(
			'label'                     => _x( 'Preparing', 'Order status', 'ipn' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders. */
			'label_count'               => _n_noop( 'Preparing <span class="count">(%s)</span>', 'Preparing <span class="count">(%s)</span>', 'ipn' ),
		) );

		register_post_status( 'wc-ipn-ready', array(
			'label'                     => _x( 'Ready for Collection', 'Order status', 'ipn' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders. */
			'label_count'               => _n_noop( 'Ready for Collection <span class="count">(%s)</span>', 'Ready for Collection <span class="count">(%s)</span>', 'ipn' ),
		) );

		register_post_status( 'wc-ipn-disputed', array(
			'label'                     => _x( 'Disputed', 'Order status', 'ipn' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders. */
			'label_count'               => _n_noop( 'Disputed <span class="count">(%s)</span>', 'Disputed <span class="count">(%s)</span>', 'ipn' ),
		) );

		register_post_status( 'wc-ipn-expired', array(
			'label'                     => _x( 'Collection Expired', 'Order status', 'ipn' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders. */
			'label_count'               => _n_noop( 'Collection Expired <span class="count">(%s)</span>', 'Collection Expired <span class="count">(%s)</span>', 'ipn' ),
		) );
	}

	public function register_order_status_labels( $order_statuses ) {
		$new = array();

		foreach ( $order_statuses as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'wc-processing' === $key ) {
				$new['wc-ipn-accepted']  = _x( 'Accepted by Branch', 'Order status', 'ipn' );
				$new['wc-ipn-preparing'] = _x( 'Preparing', 'Order status', 'ipn' );
				$new['wc-ipn-ready']     = _x( 'Ready for Collection', 'Order status', 'ipn' );
				$new['wc-ipn-disputed']  = _x( 'Disputed', 'Order status', 'ipn' );
				$new['wc-ipn-expired']   = _x( 'Collection Expired', 'Order status', 'ipn' );
			}
		}

		return $new;
	}

	// ---- ipn_order_meta CRUD ----

	public static function get_meta( $order_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Called from IPN_Checkout::save_collection_fields() once per order.
	 */
	public static function save_checkout_meta( $order_id, array $data ) {
		global $wpdb;
		$table = self::table();

		$row = array(
			'branch_id'           => (int) $data['branch_id'],
			'collection_type'     => 'express' === $data['collection_type'] ? 'express' : 'standard',
			'nominated_name'      => isset( $data['nominated_name'] ) ? $data['nominated_name'] : '',
			'nominated_phone'     => isset( $data['nominated_phone'] ) ? $data['nominated_phone'] : '',
			'nominated_id_number' => isset( $data['nominated_id_number'] ) ? $data['nominated_id_number'] : '',
		);

		if ( self::get_meta( $order_id ) ) {
			$wpdb->update( $table, $row, array( 'order_id' => (int) $order_id ) );
		} else {
			$row['order_id'] = (int) $order_id;
			$wpdb->insert( $table, $row );
		}

		// Mirrored onto the WC order itself (not just ipn_order_meta) because
		// the email templates under templates/emails/ read collection type
		// and recipient name via $order->get_meta() directly. Written through
		// the order CRUD rather than update_post_meta() so it lands in the
		// right place under HPOS too — with custom order tables on, post meta
		// is not where WooCommerce (or wc_get_orders()' meta_query) looks.
		$order = wc_get_order( $order_id );

		if ( $order ) {
			$order->update_meta_data( '_ipn_branch_id', (int) $data['branch_id'] );
			$order->update_meta_data( '_ipn_collection_type', $row['collection_type'] );
			$order->update_meta_data( '_ipn_nominated_recipient_name', $row['nominated_name'] );
			$order->save();
		} else {
			update_post_meta( $order_id, '_ipn_branch_id', (int) $data['branch_id'] );
			update_post_meta( $order_id, '_ipn_collection_type', $row['collection_type'] );
			update_post_meta( $order_id, '_ipn_nominated_recipient_name', $row['nominated_name'] );
		}
	}

	/**
	 * The collection branch for an order, from whichever of the two places
	 * it was recorded.
	 *
	 * ipn_order_meta is authoritative, but the branch is also mirrored onto
	 * the order itself, and the admin order list was reading only the former
	 * — so any order whose ipn_order_meta row never got written showed a
	 * blank Branch column with no way to tell which branch it belonged to
	 * (issue #16). Checking both means the column is populated whenever
	 * *either* source has it.
	 *
	 * @param WC_Order|int $order
	 * @return int 0 when this isn't a Click & Collect order at all.
	 */
	public static function branch_id_for( $order ) {
		$order_object = is_object( $order ) ? $order : wc_get_order( $order );
		$order_id     = is_object( $order ) && method_exists( $order, 'get_id' ) ? $order->get_id() : (int) $order;

		$meta = self::get_meta( $order_id );

		if ( $meta && $meta->branch_id ) {
			return (int) $meta->branch_id;
		}

		if ( $order_object && method_exists( $order_object, 'get_meta' ) ) {
			$mirrored = (int) $order_object->get_meta( '_ipn_branch_id' );

			if ( $mirrored ) {
				return $mirrored;
			}
		}

		return 0;
	}

	/**
	 * Order IDs that have an ipn_order_meta row, most recent first —
	 * optionally scoped to one branch. Filtering here rather than in PHP is
	 * what makes the admin list's branch filter reach the whole order
	 * history instead of only the most recent page of it.
	 *
	 * @param array $args branch_id, limit.
	 * @return int[]
	 */
	public static function get_order_ids( array $args = array() ) {
		global $wpdb;

		$args   = wp_parse_args( $args, array( 'branch_id' => 0, 'branch_ids' => array(), 'limit' => 200 ) );
		$table  = self::table();

		// A row with branch_id 0 is not a Click & Collect order — it is a row
		// that was written without a branch ever being chosen. Treating those
		// as IPN orders is what filled the admin list with ordinary orders
		// showing a blank Branch column.
		$where  = array( 'branch_id > 0' );
		$params = array();

		if ( ! empty( $args['branch_id'] ) ) {
			$where[]  = 'branch_id = %d';
			$params[] = (int) $args['branch_id'];
		} elseif ( ! empty( $args['branch_ids'] ) ) {
			$ids          = array_map( 'intval', (array) $args['branch_ids'] );
			$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
			$where[]      = "branch_id IN ( {$placeholders} )";
			$params       = array_merge( $params, $ids );
		}

		$params[] = max( 1, (int) $args['limit'] );

		$sql = "SELECT order_id FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY order_id DESC LIMIT %d';

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	protected static function set_fields( $order_id, array $fields ) {
		global $wpdb;
		$wpdb->update( self::table(), $fields, array( 'order_id' => (int) $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Advances an order to a new WooCommerce status, optionally guarding on
	 * the status it must currently be in. Used by the staff dashboard's
	 * Accept / Preparing / Ready / Reject actions and by OTP verification.
	 *
	 * @param int         $order_id
	 * @param string|null $expected_current Unprefixed status required before advancing, or null to skip the check.
	 * @param string      $to_status        Unprefixed target status.
	 * @return bool
	 */
	public static function advance( $order_id, $expected_current, $to_status ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		if ( $expected_current && $order->get_status() !== $expected_current ) {
			return false;
		}

		$order->update_status( $to_status );

		return true;
	}

	// ---- status transition handlers ----

	/**
	 * Payment confirmed: reserve stock per line item at the chosen branch.
	 * No collection code yet — there's nothing to collect until the branch
	 * marks the order ready (see on_ready()).
	 */
	public function on_processing( $order_id ) {
		$meta = self::get_meta( $order_id );

		if ( ! $meta || ! $meta->branch_id ) {
			return; // Not an IPN order — no branch was selected at checkout.
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			IPN_Branch_Stock::reserve( $item->get_product_id(), $meta->branch_id, $item->get_quantity(), $order_id );
		}

		do_action( 'ipn_order_placed', $order_id );
	}

	/**
	 * Cancelled, refunded, or payment failed: release any reserved stock
	 * back to available. $reason is the most recent order note (e.g. one
	 * IPN_Uncollected_Workflow left before auto-cancelling), so the
	 * cancellation email can explain why without extra plumbing.
	 */
	public function on_cancelled_or_refunded( $order_id ) {
		$meta = self::get_meta( $order_id );

		if ( ! $meta || ! $meta->branch_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			IPN_Branch_Stock::release( $item->get_product_id(), $meta->branch_id, $item->get_quantity(), $order_id, 'order_' . $order->get_status() );
		}

		do_action( 'ipn_order_cancelled', $order_id, self::latest_order_note( $order_id ) );
	}

	/**
	 * Orders that auto-expired (collection_expired audit event) since a
	 * given datetime — backs both the admin Daily Digest screen and the
	 * scheduled digest email, so both work from identical data.
	 *
	 * @param string $since MySQL datetime string.
	 * @return object[]
	 */
	public static function expired_since( $since ) {
		global $wpdb;
		$audit_table = IPN_Audit_Log::table();

		$order_ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT order_id FROM {$audit_table} WHERE event_type = 'collection_expired' AND created_at >= %s ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL
			$since
		) );

		$rows = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			$meta   = self::get_meta( $order_id );
			$branch = ( $meta && $meta->branch_id ) ? IPN_Branch::get( $meta->branch_id ) : null;

			$rows[] = (object) array(
				'order_id'      => $order_id,
				'order_number'  => $order->get_order_number(),
				'branch_name'   => $branch ? $branch->name : '',
				'customer_name' => self::customer_name( $order ),
				'ready_at'      => ( $meta && $meta->ready_at ) ? $meta->ready_at : '',
				'total'         => $order->get_total(),
				'refunded'      => 'refunded' === $order->get_status(),
			);
		}

		return $rows;
	}

	protected static function latest_order_note( $order_id ) {
		if ( ! function_exists( 'wc_get_order_notes' ) ) {
			return '';
		}
		$notes = wc_get_order_notes( array(
			'order_id' => $order_id,
			'limit'    => 1,
		) );
		return $notes ? wp_strip_all_tags( $notes[0]->content ) : '';
	}

	public function on_accepted( $order_id ) {
		$meta = self::get_meta( $order_id );
		IPN_Audit_Log::log( 'order_accepted', array(
			'order_id'  => $order_id,
			'branch_id' => $meta ? $meta->branch_id : null,
		) );
		do_action( 'ipn_order_accepted', $order_id );
	}

	public function on_preparing( $order_id ) {
		$meta = self::get_meta( $order_id );
		IPN_Audit_Log::log( 'order_preparing', array(
			'order_id'  => $order_id,
			'branch_id' => $meta ? $meta->branch_id : null,
		) );
	}

	/**
	 * Ready for collection: this is the one moment a collection code
	 * actually means something, so it's generated and emailed right here
	 * (see IPN_Notifications::send_ready_for_collection()) rather than at
	 * payment. The collection window (auto-cancel deadline) also starts
	 * counting from now, matching the spec's stage order (Ready -> 48h
	 * reminder -> window expires), not from payment.
	 */
	public function on_ready( $order_id ) {
		$meta = self::get_meta( $order_id );

		if ( ! $meta || ! $meta->branch_id ) {
			return;
		}

		$otp = IPN_OTP::generate( $order_id, $meta->branch_id );

		$branch      = IPN_Branch::get( $meta->branch_id );
		$window_days = $branch ? (int) $branch->collection_window_days : (int) get_option( 'ipn_collection_window_days', 5 );

		self::set_fields( $order_id, array(
			'ready_at'                  => gmdate( 'Y-m-d H:i:s' ),
			'collection_window_expires' => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $window_days . ' days', current_time( 'timestamp', true ) ) ),
			'reminder_sent_at'          => null,
		) );

		IPN_Audit_Log::log( 'order_ready', array(
			'order_id'  => $order_id,
			'branch_id' => $meta->branch_id,
		) );

		do_action( 'ipn_order_ready_for_collection', $order_id, $otp );
	}

	/**
	 * Collection window expired without the order being collected — the
	 * uncollected-orders cron (IPN_Uncollected_Workflow) is what drives an
	 * order into this status. Releases stock; refund is always a manual
	 * admin step (confirmed decision — no auto-refund).
	 */
	public function on_expired( $order_id ) {
		$meta = self::get_meta( $order_id );

		if ( ! $meta || ! $meta->branch_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( $order ) {
			foreach ( $order->get_items() as $item ) {
				IPN_Branch_Stock::release( $item->get_product_id(), $meta->branch_id, $item->get_quantity(), $order_id, 'collection_expired' );
			}
		}

		IPN_Audit_Log::log( 'collection_expired', array(
			'order_id'  => $order_id,
			'branch_id' => $meta->branch_id,
		) );

		do_action( 'ipn_order_cancelled', $order_id, __( 'Collection window expired without the order being collected.', 'ipn' ) );
	}

	/**
	 * Collected: permanently deduct stock and record who collected it.
	 * Reached either via OTP verification in the staff dashboard (see
	 * IPN_Staff_Dashboard) or a manual admin status change.
	 */
	public function on_completed( $order_id ) {
		$meta = self::get_meta( $order_id );

		if ( ! $meta || ! $meta->branch_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( $order ) {
			foreach ( $order->get_items() as $item ) {
				IPN_Branch_Stock::deduct_sold( $item->get_product_id(), $meta->branch_id, $item->get_quantity(), $order_id );
			}
		}

		self::set_fields( $order_id, array(
			'collected_at' => current_time( 'mysql' ),
			'collected_by' => ! empty( $meta->nominated_name ) ? 'nominee' : 'customer',
		) );

		IPN_Audit_Log::log( 'collection_completed', array(
			'order_id'  => $order_id,
			'branch_id' => $meta->branch_id,
		) );

		do_action( 'ipn_order_collected', $order_id );
	}

	public function on_disputed( $order_id ) {
		$meta = self::get_meta( $order_id );
		IPN_Audit_Log::log( 'collection_disputed', array(
			'order_id'  => $order_id,
			'branch_id' => $meta ? $meta->branch_id : null,
			'data'      => array( 'reason' => $meta ? $meta->dispute_reason : '' ),
		) );

		do_action( 'ipn_order_disputed', $order_id );
	}
}
