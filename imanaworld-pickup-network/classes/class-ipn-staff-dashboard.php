<?php
defined( 'ABSPATH' ) || exit;

/**
 * Branch staff order dashboard (Section 3.4) — a front-end, non wp-admin
 * area reached via the [ipn_staff_dashboard] shortcode. Staff only ever see
 * orders whose ipn_order_meta.branch_id matches their assigned branch
 * (IPN_Roles::get_branch_id). Order status advances go through
 * IPN_Order::advance(), which triggers IPN_Order's status hooks.
 */
class IPN_Staff_Dashboard {

	/**
	 * Display-status => [ expected current WC status, target WC status ]
	 * for the "Accept / Mark preparing / Mark ready" sticky action button.
	 *
	 * Aliased rather than restated: the vendor dashboard walks an order
	 * through the same three steps, and IPN_Order owns the one definition.
	 */
	const NEXT_STATUS = IPN_Order::NEXT_STATUS;

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'init', $this, 'register_shortcode' );
		$loader->add_filter( 'login_redirect', $this, 'redirect_staff_after_login', 10, 3 );
		$loader->add_action( 'admin_init', $this, 'keep_staff_out_of_wp_admin' );
	}

	/**
	 * Sends branch staff to their dashboard after signing in, however they
	 * signed in.
	 *
	 * The sign-in card on the dashboard page already passes a redirect, but a
	 * staff member who lands on wp-login.php by any other route — a bookmark,
	 * a password-reset link, a session timeout — would otherwise be dropped
	 * into wp-admin, which they have no business seeing and which looks
	 * exactly like the login having failed.
	 *
	 * @param string           $redirect_to
	 * @param string           $requested
	 * @param WP_User|WP_Error $user
	 */
	public function redirect_staff_after_login( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof WP_User || ! IPN_Roles::is_branch_staff( $user->ID ) ) {
			return $redirect_to;
		}

		$dashboard = IPN_Pages::staff_dashboard_url();

		return $dashboard ? $dashboard : $redirect_to;
	}

	/**
	 * Bounces branch staff out of wp-admin, enforcing the rule the rest of the
	 * plugin is written around rather than merely documenting it.
	 *
	 * Their own profile screen stays reachable so they can change their
	 * password, and AJAX is left alone because admin-ajax.php fires admin_init
	 * too — redirecting there would break any front-end request that uses it.
	 */
	public function keep_staff_out_of_wp_admin() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}

		if ( ! IPN_Roles::is_branch_staff( get_current_user_id() ) ) {
			return;
		}

		global $pagenow;

		if ( in_array( $pagenow, array( 'profile.php', 'admin-post.php' ), true ) ) {
			return;
		}

		$dashboard = IPN_Pages::staff_dashboard_url();

		if ( $dashboard ) {
			wp_safe_redirect( $dashboard );
			exit;
		}
	}

	public function register_shortcode() {
		add_shortcode( 'ipn_staff_dashboard', array( $this, 'render' ) );
	}

	/**
	 * Screens reachable via ?ipn_screen= on the page carrying the shortcode.
	 * There is no client-side router here (unlike the mockup this dashboard
	 * is built from) — each screen is a full page render.
	 */
	const SCREENS = array( 'queue', 'detail', 'stock', 'hours' );

	public function render() {
		if ( ! is_user_logged_in() || ! IPN_Roles::is_branch_staff( get_current_user_id() ) ) {
			ob_start();
			include IPN_PLUGIN_DIR . 'templates/staff/login.php';
			return ob_get_clean();
		}

		$branch_id = IPN_Roles::get_branch_id( get_current_user_id() );
		$branch    = $branch_id ? IPN_Branch::get( $branch_id ) : null;

		$screen = isset( $_GET['ipn_screen'] ) ? sanitize_key( wp_unslash( $_GET['ipn_screen'] ) ) : 'queue'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $screen, self::SCREENS, true ) ) {
			$screen = 'queue';
		}

		ob_start();

		switch ( $screen ) {
			case 'detail':
				$order_id    = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$otp_result  = null;
				$resend_sent = false;

				if ( $order_id && $branch_id ) {
					$this->maybe_handle_detail_actions( $order_id, $branch_id, $otp_result, $resend_sent );
				}

				$order = ( $order_id && $branch_id ) ? $this->get_order_detail( $order_id, $branch_id ) : null;

				include IPN_PLUGIN_DIR . 'templates/staff/order-detail.php';
				break;

			case 'stock':
				$stock_result = null;

				if ( $branch_id ) {
					$this->maybe_handle_stock_adjust( $branch_id );
					$stock_result = $this->maybe_handle_stock_row_actions( $branch_id );
				}

				// Searched and paged in SQL, same as the admin Stock screen —
				// this used to load every stock row for the branch and then
				// filter it in PHP with a wc_get_product() call per row, which
				// only works while the catalogue is pilot-sized (issue #7).
				$stock_per_page = 20;
				$stock_search   = isset( $_GET['stock_q'] ) ? sanitize_text_field( wp_unslash( $_GET['stock_q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$stock_page     = isset( $_GET['stock_page'] ) ? max( 1, absint( $_GET['stock_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$stock_args     = array(
					'branch_id' => $branch_id,
					'search'    => $stock_search,
					'per_page'  => $stock_per_page,
					'page'      => $stock_page,
				);

				$stock       = $branch_id ? IPN_Branch_Stock::query_products( $stock_args ) : array();
				$stock_total = $branch_id ? IPN_Branch_Stock::count_products( $stock_args ) : 0;

				// Only offered while searching: the branch's own vendor may
				// have a catalogue far too large to list, and staff adding a
				// line are always looking for a specific product.
				$stock_addable = ( $branch_id && '' !== $stock_search ) ? $this->searchable_products( $branch_id, $stock_search ) : array();

				include IPN_PLUGIN_DIR . 'templates/staff/stock.php';
				break;

			case 'hours':
				$hours_result = $branch_id ? $this->maybe_handle_hours_save( $branch_id ) : null;
				$hours        = $branch_id ? IPN_Branch::get_hours( $branch_id ) : array();

				include IPN_PLUGIN_DIR . 'templates/staff/hours.php';
				break;

			default:
				$orders = $branch_id ? $this->get_branch_orders( $branch_id ) : array();
				include IPN_PLUGIN_DIR . 'templates/staff/order-queue.php';
				break;
		}

		return ob_get_clean();
	}

	/**
	 * Adding a product to this branch, or removing one from it — the two
	 * stock actions the dashboard previously had no way to perform (it could
	 * only edit the total of a row that already existed).
	 *
	 * Both are branch-scoped through IPN_Access rather than trusting the
	 * branch the form posted, so a staff member cannot reach a second branch
	 * by editing a hidden field.
	 *
	 * @return string|WP_Error|null
	 */
	protected function maybe_handle_stock_row_actions( $branch_id ) {
		if ( empty( $_POST['ipn_staff_stock_action'] ) ) {
			return null;
		}

		$action = sanitize_key( wp_unslash( $_POST['ipn_staff_stock_action'] ) );

		if ( ! isset( $_POST['ipn_staff_stock_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ipn_staff_stock_nonce'] ) ), 'ipn_staff_stock_' . $action . '_' . $branch_id ) ) {
			return null;
		}

		$branch = IPN_Access::require_branch( $branch_id );

		if ( is_wp_error( $branch ) ) {
			return $branch;
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			return new WP_Error( 'ipn_stock_invalid', __( 'No product was selected.', 'ipn' ) );
		}

		if ( 'delete' === $action ) {
			$deleted = IPN_Branch_Stock::delete_row( $product_id, $branch->id );

			return is_wp_error( $deleted ) ? $deleted : __( 'Product removed from this branch.', 'ipn' );
		}

		// Adding: the product must belong to this branch's own vendor.
		if ( (int) get_post_field( 'post_author', $product_id ) !== (int) $branch->vendor_id ) {
			return new WP_Error( 'ipn_stock_forbidden', __( 'That product does not belong to this store.', 'ipn' ) );
		}

		$total = isset( $_POST['total_stock'] ) ? absint( $_POST['total_stock'] ) : 0;

		IPN_Branch_Stock::set_total( $product_id, $branch->id, $total );

		IPN_Audit_Log::log( 'stock_adjusted', array(
			'branch_id' => $branch->id,
			'data'      => array( 'product_id' => $product_id, 'new_total' => $total ),
		) );

		return __( 'Product added to this branch.', 'ipn' );
	}

	/**
	 * This branch's vendor's products matching a search, flagged with whether
	 * the branch already stocks them.
	 *
	 * @return object[]
	 */
	protected function searchable_products( $branch_id, $search ) {
		$branch = IPN_Branch::get( $branch_id );

		if ( ! $branch ) {
			return array();
		}

		$products = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'author'         => (int) $branch->vendor_id,
			's'              => $search,
			'posts_per_page' => 15,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$rows = array();

		foreach ( $products as $product ) {
			$rows[] = (object) array(
				'product_id' => (int) $product->ID,
				'name'       => $product->post_title,
				'stocked'    => (bool) IPN_Branch_Stock::get_row( $product->ID, $branch_id ),
			);
		}

		return $rows;
	}

	/**
	 * Saves this branch's weekly operating hours from the staff dashboard.
	 * Hours are the one branch setting the people actually standing in the
	 * shop are best placed to keep accurate — everything else about a branch
	 * stays with the vendor and the admin.
	 *
	 * @return string|WP_Error|null
	 */
	protected function maybe_handle_hours_save( $branch_id ) {
		if ( empty( $_POST['ipn_staff_save_hours'] ) ) {
			return null;
		}

		if ( ! isset( $_POST['ipn_staff_hours_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ipn_staff_hours_nonce'] ) ), 'ipn_staff_save_hours_' . $branch_id ) ) {
			return null;
		}

		$branch = IPN_Access::require_branch( $branch_id );

		if ( is_wp_error( $branch ) ) {
			return $branch;
		}

		$days = array();

		for ( $day = 0; $day <= 6; $day++ ) {
			$days[ $day ] = array(
				'is_closed'  => ! empty( $_POST[ 'hours_closed_' . $day ] ),
				'open_time'  => isset( $_POST[ 'hours_open_' . $day ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'hours_open_' . $day ] ) ) : '',
				'close_time' => isset( $_POST[ 'hours_close_' . $day ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'hours_close_' . $day ] ) ) : '',
			);
		}

		IPN_Branch::set_hours( $branch->id, $days );

		IPN_Audit_Log::log( 'branch_hours_updated', array( 'branch_id' => $branch->id ) );

		return __( 'Opening hours updated.', 'ipn' );
	}

	/**
	 * Handles the four POST actions on the detail screen: OTP verification,
	 * advancing to the next status, rejecting a ready-for-collection order
	 * into Disputed, and resending the collection code. Runs before the
	 * order is loaded for render so the template reflects the post-action
	 * state.
	 *
	 * @param int                $order_id
	 * @param int                $branch_id   Staff member's own branch — every action is scoped to it.
	 * @param true|WP_Error|null $otp_result  Set by reference when an OTP verification was attempted.
	 * @param bool               $resend_sent Set by reference to true when a resend just succeeded.
	 */
	protected function maybe_handle_detail_actions( $order_id, $branch_id, &$otp_result, &$resend_sent ) {
		$meta = IPN_Order::get_meta( $order_id );

		if ( ! $meta || (int) $meta->branch_id !== (int) $branch_id ) {
			return; // Not this branch's order — ignore any posted action.
		}

		if ( isset( $_POST['ipn_resend_otp'], $_POST['ipn_resend_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ipn_resend_nonce'] ) ), 'ipn_resend_otp_' . $order_id ) ) {

			$resend_sent = IPN_Notifications::resend( $order_id );
			return;
		}

		if ( isset( $_POST['ipn_otp_code'], $_POST['ipn_otp_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ipn_otp_nonce'] ) ), 'ipn_verify_otp_' . $order_id ) ) {

			$otp_result = IPN_OTP::verify(
				$order_id,
				sanitize_text_field( wp_unslash( $_POST['ipn_otp_code'] ) ),
				get_current_user_id()
			);

			if ( true === $otp_result ) {
				IPN_Order::advance( $order_id, 'ipn-ready', 'completed' );
			}

			return;
		}

		if ( isset( $_POST['ipn_advance_to'], $_POST['ipn_advance_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ipn_advance_nonce'] ) ), 'ipn_advance_status_' . $order_id ) ) {

			$to = sanitize_key( wp_unslash( $_POST['ipn_advance_to'] ) );

			foreach ( self::NEXT_STATUS as $step ) {
				if ( $step[1] === $to ) {
					IPN_Order::advance( $order_id, $step[0], $to );
					break;
				}
			}

			return;
		}

		if ( isset( $_POST['ipn_reject_reason'], $_POST['ipn_reject_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ipn_reject_nonce'] ) ), 'ipn_reject_collection_' . $order_id ) ) {

			global $wpdb;
			$wpdb->update(
				IPN_Order::table(),
				array( 'dispute_reason' => sanitize_text_field( wp_unslash( $_POST['ipn_reject_reason'] ) ) ),
				array( 'order_id' => $order_id )
			);

			IPN_Order::advance( $order_id, 'ipn-ready', 'ipn-disputed' );
		}
	}

	/**
	 * Handles the Branch Stock screen's per-row "Adjust total" form —
	 * scoped to the staff member's own branch (product_id comes from the
	 * form, but the branch is always the one they're logged into).
	 */
	protected function maybe_handle_stock_adjust( $branch_id ) {
		if ( empty( $_POST['ipn_staff_adjust_stock'] ) ) {
			return;
		}

		if ( ! isset( $_POST['ipn_staff_stock_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ipn_staff_stock_nonce'] ) ), 'ipn_staff_adjust_stock_' . $branch_id ) ) {
			return;
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$total      = isset( $_POST['total_stock'] ) && '' !== $_POST['total_stock'] ? absint( $_POST['total_stock'] ) : null;

		if ( ! $product_id || null === $total ) {
			return;
		}

		IPN_Branch_Stock::set_total( $product_id, $branch_id, $total );

		IPN_Audit_Log::log( 'stock_adjusted', array(
			'branch_id' => $branch_id,
			'data'      => array(
				'product_id' => $product_id,
				'new_total'  => $total,
			),
		) );
	}

	/**
	 * Builds a link to another dashboard screen on the current page, e.g.
	 * IPN_Staff_Dashboard::screen_url( 'stock' ) or
	 * IPN_Staff_Dashboard::screen_url( 'detail', array( 'order_id' => 123 ) ).
	 *
	 * @param string $screen One of self::SCREENS.
	 * @param array  $args   Extra query args to merge in (e.g. order_id, status).
	 * @return string Escaped URL.
	 */
	public static function screen_url( $screen, array $args = array() ) {
		$args = array_merge( array( 'ipn_screen' => $screen ), $args );
		return esc_url( add_query_arg( $args ) );
	}

	/**
	 * This branch's order queue, most recent first.
	 *
	 * @param int $branch_id
	 * @return array Objects shaped per build_order_summary().
	 */
	public function get_branch_orders( $branch_id ) {
		global $wpdb;
		$table = IPN_Order::table();

		$order_ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT order_id FROM {$table} WHERE branch_id = %d ORDER BY order_id DESC LIMIT 200", // phpcs:ignore WordPress.DB.PreparedSQL
			$branch_id
		) );

		$orders = array();

		foreach ( $order_ids as $order_id ) {
			$summary = $this->build_order_summary( (int) $order_id );
			if ( $summary ) {
				$orders[] = $summary;
			}
		}

		return $orders;
	}

	/**
	 * Full detail for a single order, scoped to $branch_id — returns null
	 * if the order doesn't belong to this branch (prevents staff guessing
	 * other branches' order IDs via the URL).
	 *
	 * @param int $order_id
	 * @param int $branch_id
	 * @return object|null
	 */
	public function get_order_detail( $order_id, $branch_id ) {
		$meta = IPN_Order::get_meta( $order_id );

		if ( ! $meta || (int) $meta->branch_id !== (int) $branch_id ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return null;
		}

		$display_status = IPN_Order::display_status( $order->get_status() );

		if ( ! $display_status ) {
			return null;
		}

		$branch = IPN_Branch::get( $branch_id );

		$detail                 = new stdClass();
		$detail->id             = $order->get_order_number();
		$detail->order_id       = $order_id;
		$detail->customer_name  = IPN_Order::customer_name( $order );
		$detail->time_label     = IPN_Order::time_label( $order );
		$detail->type           = $meta->collection_type ? $meta->collection_type : 'standard';
		$detail->surcharge      = ( 'express' === $detail->type && $branch ) ? (float) $branch->express_surcharge : 0;
		$detail->status         = $display_status;
		$detail->dispute_reason = IPN_Order::dispute_reason_label( $meta->dispute_reason );

		$detail->items = array();
		foreach ( $order->get_items() as $item ) {
			$detail->items[] = array(
				'name' => $item->get_name(),
				'qty'  => $item->get_quantity(),
			);
		}

		$detail->recipient = null;
		if ( ! empty( $meta->nominated_name ) ) {
			$detail->recipient = (object) array(
				'name'      => $meta->nominated_name,
				'phone'     => $meta->nominated_phone,
				'id_number' => $meta->nominated_id_number,
			);
		}

		$detail->audit = array();
		foreach ( IPN_Audit_Log::for_order( $order_id ) as $entry ) {
			$detail->audit[] = array(
				'text' => IPN_Audit_Log::describe_event( $entry->event_type ),
				'time' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at ) ),
			);
		}

		return $detail;
	}

	/**
	 * Builds the summary shape templates/staff/order-queue.php expects.
	 *
	 * @param int $order_id
	 * @return object|null
	 */
	protected function build_order_summary( $order_id ) {
		$meta  = IPN_Order::get_meta( $order_id );
		$order = wc_get_order( $order_id );

		if ( ! $meta || ! $order ) {
			return null;
		}

		$display_status = IPN_Order::display_status( $order->get_status() );

		if ( ! $display_status ) {
			return null;
		}

		$summary                = new stdClass();
		$summary->id            = $order->get_order_number();
		$summary->order_id      = $order_id;
		$summary->customer_name = IPN_Order::customer_name( $order );
		$summary->time_label    = IPN_Order::time_label( $order );
		$summary->type          = $meta->collection_type ? $meta->collection_type : 'standard';
		$summary->status        = $display_status;
		$summary->item_count    = $order->get_item_count();
		$summary->has_recipient = ! empty( $meta->nominated_name );

		return $summary;
	}

}
